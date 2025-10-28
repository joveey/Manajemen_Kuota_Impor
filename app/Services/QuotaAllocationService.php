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
        $product = Product::findOrFail($productId);

        // Candidate quotas: period includes PO date first, then future periods
        $candidates = Quota::query()
            ->where('is_active', true)
            ->orderBy('period_start')
            ->get()
            ->filter(function (Quota $q) use ($product) {
                return $q->matchesProduct($product);
            });

        $ordered = $candidates->partition(function (Quota $q) use ($poDateStr) {
            return $q->period_start && $q->period_end && $q->period_start->toDateString() <= $poDateStr && $q->period_end->toDateString() >= $poDateStr;
        });

        $width = function (Quota $q) {
            $min = is_null($q->min_pk) ? null : (float)$q->min_pk;
            $max = is_null($q->max_pk) ? null : (float)$q->max_pk;
            if ($min === null || $max === null) { return INF; }
            return max(0.0, $max - $min);
        };

        $current = $ordered[0]->sortBy($width)->values();
        $future = $ordered[1]->filter(fn (Quota $q) => $q->period_start && $q->period_start->toDateString() > $poDateStr)->sortBy($width)->values();

        $queue = $current->concat($future)->values();

        $left = (int) $poQty;
        $allocs = [];

        DB::transaction(function () use (&$left, &$allocs, $queue, $po, $poDateStr) {
            foreach ($queue as $quota) {
                if ($left <= 0) { break; }
                $avail = (int) ($quota->forecast_remaining ?? 0);
                if ($avail <= 0) { continue; }
                $take = min($left, $avail);
                if ($take <= 0) { continue; }

                // Decrease forecast and log
                $quota->decrementForecast($take, 'Forecast allocated for PO '.$po->po_number, $po, new \DateTimeImmutable($poDateStr), Auth::id());

                // Attach/Upsert pivot
                $existing = DB::table('purchase_order_quota')
                    ->where('purchase_order_id', $po->id)
                    ->where('quota_id', $quota->id)
                    ->first();
                if ($existing) {
                    DB::table('purchase_order_quota')
                        ->where('id', $existing->id)
                        ->update(['allocated_qty' => (int)$existing->allocated_qty + $take, 'updated_at' => now()]);
                } else {
                    DB::table('purchase_order_quota')->insert([
                        'purchase_order_id' => $po->id,
                        'quota_id' => $quota->id,
                        'allocated_qty' => $take,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $allocs[] = ['quota_id' => $quota->id, 'qty' => $take];
                $left -= $take;
            }
        });

        return [$allocs, $left];
    }
}
