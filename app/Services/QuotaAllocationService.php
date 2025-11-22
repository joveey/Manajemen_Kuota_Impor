<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Quota;
use App\Models\PurchaseOrder;
use App\Models\QuotaHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotaAllocationService
{
    /**
     * Allocate forecast across periods based on PO date then subsequent periods.
     * Returns [allocations, leftover]. allocations: array of [quota_id, qty].
     */
    public function allocateForecast(int $productId, int $poQty, \DateTimeInterface|string $poDate, PurchaseOrder $po): array
    {
        $poDateStr = $poDate instanceof \DateTimeInterface ? $poDate->format('Y-m-d') : (string) $poDate;
        $product   = Product::findOrFail($productId);

        return DB::transaction(function () use ($product, $poQty, $poDateStr, $po) {
            // Lock all active quotas in a stable order before computing allocations
            $candidates = Quota::query()
                ->where('is_active', true)
                ->orderBy('period_start')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(function (Quota $q) use ($product) {
                    return $q->matchesProduct($product);
                })
                ->values();

            // Partition by whether PO date falls inside the quota period
            $ordered = $candidates->partition(function (Quota $q) use ($poDateStr) {
                if (!$q->period_start || !$q->period_end) {
                    return false;
                }
                $start = $q->period_start->toDateString();
                $end   = $q->period_end->toDateString();

                return $start <= $poDateStr && $end >= $poDateStr;
            });

            $width = static function (Quota $q): float {
                $min = is_null($q->min_pk) ? null : (float) $q->min_pk;
                $max = is_null($q->max_pk) ? null : (float) $q->max_pk;
                if ($min === null || $max === null) {
                    return INF;
                }

                return max(0.0, $max - $min);
            };

            $current = $ordered[0]->sortBy($width)->values();
            $future  = $ordered[1]
                ->filter(fn (Quota $q) => $q->period_start && $q->period_start->toDateString() > $poDateStr)
                ->sortBy($width)
                ->values();

            $queue = $current->concat($future)->values();

            $left   = (int) $poQty;
            $allocs = [];

            foreach ($queue as $quota) {
                if ($left <= 0) {
                    break;
                }

                // Ensure we always work with the latest locked value inside this transaction
                $quota->refresh();

                $available = (int) ($quota->forecast_remaining ?? 0);
                if ($available <= 0) {
                    continue;
                }

                $take = min($left, $available);
                if ($take <= 0) {
                    continue;
                }

                // Decrease forecast and log history
                $quota->decrementForecast(
                    $take,
                    'Forecast allocated for PO '.$po->po_number,
                    $po,
                    new \DateTimeImmutable($poDateStr),
                    Auth::id()
                );

                // Attach / upsert pivot row
                $existing = DB::table('purchase_order_quota')
                    ->where('purchase_order_id', $po->id)
                    ->where('quota_id', $quota->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    DB::table('purchase_order_quota')
                        ->where('id', $existing->id)
                        ->update([
                            'allocated_qty' => (int) $existing->allocated_qty + $take,
                            'updated_at'    => now(),
                        ]);
                } else {
                    DB::table('purchase_order_quota')->insert([
                        'purchase_order_id' => $po->id,
                        'quota_id'          => $quota->id,
                        'allocated_qty'     => $take,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }

                $allocs[] = [
                    'quota_id' => $quota->id,
                    'qty'      => $take,
                ];

                $left -= $take;
            }

            return [$allocs, $left];
        });
    }
}
