<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Quota;
use App\Models\SapPurchaseOrderAllocation;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SapForecastAllocationService
{
    public function syncFromSapRow(array $row): array
    {
        $poDoc = $this->normalizeString(Arr::get($row, 'po_doc'));
        $lineNoRaw = Arr::get($row, 'line_no');
        $lineNo = $this->normalizeLineNo($lineNoRaw);
        $qty = $this->normalizeQuantity(Arr::get($row, 'qty'));
        $orderDate = $this->normalizeDate(Arr::get($row, 'order_date'));
        $periodKey = $this->resolvePeriodKey($orderDate);
        $itemCode = $this->normalizeString(Arr::get($row, 'item_code'));
        $vendorNo = $this->normalizeString(Arr::get($row, 'vendor_no'));
        $vendorName = $this->normalizeString(Arr::get($row, 'vendor_name'));

        if ($poDoc === '' || $lineNo === '') {
            throw new \RuntimeException('Invalid PO_DOC or LINE_NO');
        }

        if ($itemCode === '') {
            throw new \RuntimeException('ITEM_CODE empty for '.$poDoc.'-'.$lineNo);
        }

        $product = $this->resolveProduct($itemCode);
        if (!$product) {
            throw new \RuntimeException('Product not found for '.$itemCode);
        }

        return DB::transaction(function () use (
            $poDoc,
            $lineNo,
            $lineNoRaw,
            $product,
            $qty,
            $orderDate,
            $itemCode,
            $vendorNo,
            $vendorName,
            $periodKey
        ) {
            $allocation = SapPurchaseOrderAllocation::where('po_doc', $poDoc)
                ->where('po_line_no', $lineNo)
                ->lockForUpdate()
                ->first();

            if (!$allocation) {
                $allocation = SapPurchaseOrderAllocation::create([
                    'po_doc' => $poDoc,
                    'po_line_no' => $lineNo,
                    'po_line_no_raw' => $lineNoRaw !== null ? (string) $lineNoRaw : null,
                    'item_code' => $itemCode,
                    'vendor_no' => $vendorNo,
                    'vendor_name' => $vendorName,
                    'order_date' => $orderDate,
                    'product_id' => $product->id,
                    'target_qty' => $qty,
                    'allocations' => ['forecast' => []],
                    'period_key' => $periodKey,
                    'is_active' => true,
                    'last_seen_at' => now(),
                ]);
            } else {
                $allocation->fill([
                    'po_line_no_raw' => $lineNoRaw !== null ? (string) $lineNoRaw : $allocation->po_line_no_raw,
                    'item_code' => $itemCode,
                    'vendor_no' => $vendorNo,
                    'vendor_name' => $vendorName,
                    'order_date' => $orderDate,
                    'product_id' => $product->id,
                    'target_qty' => $qty,
                    'period_key' => $periodKey,
                    'is_active' => true,
                ]);
                $allocation->last_seen_at = now();
                $allocation->save();
            }

            $allocMap = $this->getAllocationMap($allocation);
            $currentAllocated = array_sum($allocMap);
            $result = [
                'po_doc' => $poDoc,
                'po_line_no' => $lineNo,
                'target_qty' => $qty,
                'allocated_before' => $currentAllocated,
                'delta' => $qty - $currentAllocated,
            ];

            if ($qty > $currentAllocated) {
                $allocated = $this->allocateDelta($allocation, $product, $qty - $currentAllocated, $orderDate, $allocMap);
                $result['allocated_change'] = $allocated;
            } elseif ($qty < $currentAllocated) {
                $released = $this->releaseDelta($allocation, $currentAllocated - $qty, $orderDate, $allocMap, 'refund');
                $result['released_change'] = $released;
            }

            $allocation->setForecastBucket($this->cleanAllocationMap($allocMap));
            $allocation->target_qty = $qty;
            $allocation->save();

            return $result;
        });
    }

    public function releaseMissingAllocation(SapPurchaseOrderAllocation $allocation): int
    {
        return DB::transaction(function () use ($allocation) {
            if (!$allocation->is_active) {
                return 0;
            }

            $allocationMap = $this->getAllocationMap($allocation);
            $total = array_sum($allocationMap);
            $released = 0;
            if ($total > 0) {
                $released = $this->releaseDelta(
                    $allocation,
                    $total,
                    optional($allocation->order_date)?->toDateString(),
                    $allocationMap,
                    'refund_missing'
                );
            }

            $allocation->setForecastBucket($this->cleanAllocationMap($allocationMap));
            $allocation->target_qty = 0;
            $allocation->is_active = false;
            $allocation->save();

            return $released;
        });
    }

    private function allocateDelta(
        SapPurchaseOrderAllocation $allocation,
        Product $product,
        int $need,
        ?string $orderDate,
        array &$allocationMap
    ): int {
        $poDate = $orderDate ?: now()->toDateString();
        $poDateCarbon = Carbon::parse($poDate);

        $candidates = Quota::query()
            ->active()
            ->orderBy('period_start')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (Quota $quota) => $quota->matchesProduct($product))
            ->values();

        $partitioned = $candidates->partition(function (Quota $quota) use ($poDateCarbon) {
            if (!$quota->period_start || !$quota->period_end) {
                return false;
            }
            $start = $quota->period_start->toDateString();
            $end = $quota->period_end->toDateString();

            return $start <= $poDateCarbon->toDateString()
                && $end >= $poDateCarbon->toDateString();
        });

        $width = static function (Quota $quota): float {
            $min = is_null($quota->min_pk) ? null : (float) $quota->min_pk;
            $max = is_null($quota->max_pk) ? null : (float) $quota->max_pk;
            if ($min === null || $max === null) {
                return INF;
            }

            return max(0.0, $max - $min);
        };

        $queue = $partitioned[0]->sortBy($width)->values()
            ->concat(
                $partitioned[1]
                    ->filter(fn (Quota $quota) => $quota->period_start && $quota->period_start->toDateString() > $poDateCarbon->toDateString())
                    ->sortBy($width)
                    ->values()
            )
            ->values();

        $allocated = 0;
        $userId = Auth::id();
        foreach ($queue as $quota) {
            if ($need <= 0) {
                break;
            }

            $quota->refresh();
            $available = (int) ($quota->forecast_remaining ?? 0);
            if ($available <= 0) {
                continue;
            }

            $take = min($need, $available);
            if ($take <= 0) {
                continue;
            }

            $meta = $this->buildHistoryMeta($allocation, 'deduct');
            $quota->decrementForecast(
                $take,
                sprintf('Forecast allocated for SAP PO %s-%s', $allocation->po_doc, $allocation->po_line_no),
                null,
                new \DateTimeImmutable($poDateCarbon->toDateString()),
                $userId,
                $meta
            );

            $allocationMap[(string) $quota->id] = ($allocationMap[(string) $quota->id] ?? 0) + $take;

            $allocated += $take;
            $need -= $take;
        }

        return $allocated;
    }

    private function releaseDelta(
        SapPurchaseOrderAllocation $allocation,
        int $quantity,
        ?string $orderDate,
        array &$allocationMap,
        string $action = 'refund'
    ): int {
        $remaining = $quantity;
        $userId = Auth::id();
        $occurredOn = $orderDate ? new \DateTimeImmutable($orderDate) : now();

        foreach ($allocationMap as $quotaId => $allocatedQty) {
            if ($remaining <= 0) {
                break;
            }

            $allocated = (int) $allocatedQty;
            if ($allocated <= 0) {
                continue;
            }

            $release = min($allocated, $remaining);
            $quota = Quota::lockForUpdate()->find($quotaId);
            if ($quota) {
                $meta = $this->buildHistoryMeta($allocation, $action);
                $quota->incrementForecast(
                    $release,
                    sprintf('Forecast release for SAP PO %s-%s', $allocation->po_doc, $allocation->po_line_no),
                    null,
                    $occurredOn,
                    $userId,
                    $meta
                );
            }

            $newQty = $allocated - $release;
            if ($newQty > 0) {
                $allocationMap[(string) $quotaId] = $newQty;
            } else {
                unset($allocationMap[(string) $quotaId]);
            }

            $remaining -= $release;
        }

        return $quantity - $remaining;
    }

    private function normalizeLineNo($value): string
    {
        if ($value === null) {
            return '0';
        }
        if (is_numeric($value)) {
            return (string) (int) round((float) $value);
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '0';
        }
        if (is_numeric($trimmed)) {
            return (string) (int) round((float) $trimmed);
        }
        return $trimmed;
    }

    private function normalizeQuantity($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }
        if (!is_numeric($value)) {
            return 0;
        }
        return max(0, (int) round((float) $value));
    }

    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeString($value): string
    {
        if ($value === null) {
            return '';
        }
        return trim((string) $value);
    }

    private function resolveProduct(string $itemCode): ?Product
    {
        $normalized = mb_strtolower($itemCode);
        return Product::query()
            ->whereRaw('LOWER(code) = ?', [$normalized])
            ->orWhereRaw('LOWER(sap_model) = ?', [$normalized])
            ->first();
    }
    private function getAllocationMap(SapPurchaseOrderAllocation $allocation): array
    {
        $map = $allocation->getForecastBucket();
        $normalized = [];
        foreach ($map as $quotaId => $qty) {
            $normalized[(string) $quotaId] = (int) $qty;
        }
        return $normalized;
    }

    private function cleanAllocationMap(array $map): array
    {
        return collect($map)
            ->filter(fn ($qty) => (int) $qty > 0)
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    private function buildHistoryMeta(SapPurchaseOrderAllocation $allocation, string $action = 'deduct'): array
    {
        $period = $allocation->period_key ?? $this->resolvePeriodKey(optional($allocation->order_date)?->toDateString());

        return [
            'source' => 'sap_forecast',
            'po_doc' => $allocation->po_doc,
            'line_no' => $allocation->po_line_no,
            'period' => $period,
            'action' => $action,
        ];
    }

    private function resolvePeriodKey(?string $orderDate): ?string
    {
        if (empty($orderDate)) {
            return null;
        }

        try {
            return Carbon::parse($orderDate)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
