<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\QuotaAllocationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AllocBackfillForecast extends Command
{
    protected $signature = 'alloc:backfill-forecast {--period=}';
    protected $description = 'Backfill forecast allocations to purchase_order_quota from PO lines for a given period (YYYY or YYYY-MM)';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?? '');
        if (!$period) {
            $this->error('Please provide --period=YYYY or YYYY-MM');
            return Command::INVALID;
        }

        // Resolve range from period
        if (preg_match('/^\d{4}$/', $period)) {
            $start = Carbon::create((int)$period, 1, 1)->startOfDay();
            $end = Carbon::create((int)$period, 12, 31)->endOfDay();
        } elseif (preg_match('/^\d{4}-\d{2}$/', $period)) {
            [$y,$m] = explode('-', $period);
            $start = Carbon::create((int)$y, (int)$m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
        } else {
            $this->error('Invalid period. Use YYYY or YYYY-MM');
            return Command::INVALID;
        }

        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        $this->info(sprintf('Backfilling forecast for %s (%s .. %s)', $period, $startStr, $endStr));

        $query = PurchaseOrder::query()
            ->whereBetween('created_date', [$startStr, $endStr])
            ->where('qty', '>', 0)
            ->whereNotNull('product_id')
            ->orderBy('id');

        $total = (int) $query->count();
        if ($total === 0) {
            $this->info('No purchase orders found for allocation.');
            return Command::SUCCESS;
        }

        $processed = 0;
        $allocatedUnits = 0;
        $leftoverUnits = 0;
        $skippedNoProduct = 0;
        $skippedZeroQty = 0;
        $skippedAlready = 0;
        $errors = 0;

        $allocService = app(QuotaAllocationService::class);

        $query->chunkById(200, function ($chunk) use (
            &$processed,
            &$allocatedUnits,
            &$leftoverUnits,
            &$skippedNoProduct,
            &$skippedZeroQty,
            &$skippedAlready,
            &$errors,
            $allocService
        ) {
            foreach ($chunk as $po) {
                $processed++;

                if (!$po->product_id) {
                    $skippedNoProduct++;
                    continue;
                }

                $targetQty = (int) round((float) ($po->quantity ?? $po->qty ?? 0));
                if ($targetQty <= 0) {
                    $skippedZeroQty++;
                    continue;
                }

                $currentAllocated = (int) DB::table('purchase_order_quota')
                    ->where('purchase_order_id', $po->id)
                    ->sum('allocated_qty');

                if ($currentAllocated >= $targetQty) {
                    $skippedAlready++;
                    continue;
                }

                $need = $targetQty - $currentAllocated;
                $orderDate = $this->resolveOrderDate($po);

                try {
                    [$allocs, $left] = $allocService->allocateForecast(
                        (int) $po->product_id,
                        $need,
                        $orderDate,
                        $po
                    );

                    $allocatedUnits += array_sum(array_map(fn ($row) => (int) $row['qty'], $allocs));
                    $leftoverUnits += (int) $left;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn(sprintf(
                        'Allocation failed for PO %s line %s: %s',
                        (string) $po->po_number,
                        (string) ($po->line_no ?? $po->line_number ?? '-'),
                        $e->getMessage()
                    ));
                }
            }
        });

        // Safety net: ensure pivot purchase_order_quota reflects forecast histories for the same period
        $hist = DB::table('quota_histories')
            ->where('change_type', \App\Models\QuotaHistory::TYPE_FORECAST_DECREASE)
            ->whereBetween('occurred_on', [$startStr, $endStr])
            ->where('reference_type', \App\Models\PurchaseOrder::class)
            ->whereNotNull('reference_id')
            ->select('reference_id', 'quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
            ->groupBy('reference_id', 'quota_id')
            ->get();

        $rows = [];
        foreach ($hist as $h) {
            $rows[] = [
                'purchase_order_id' => (int) $h->reference_id,
                'quota_id' => (int) $h->quota_id,
                'allocated_qty' => (int) round((float) $h->qty),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($rows)) {
            DB::table('purchase_order_quota')
                ->upsert($rows, ['purchase_order_id', 'quota_id'], ['allocated_qty', 'updated_at']);
        }

        $this->info(sprintf(
            'Processed: %d, Allocated units: %d, Leftover: %d, Skipped (no product: %d, zero qty: %d, already allocated: %d), Errors: %d',
            $processed,
            $allocatedUnits,
            $leftoverUnits,
            $skippedNoProduct,
            $skippedZeroQty,
            $skippedAlready,
            $errors
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function resolveOrderDate(PurchaseOrder $po): string
    {
        $orderDate = $po->order_date;
        if ($orderDate instanceof Carbon) {
            return $orderDate->toDateString();
        }

        $created = $po->created_date;
        if ($created instanceof Carbon) {
            return $created->toDateString();
        }

        if (!empty($created)) {
            try {
                return Carbon::parse((string) $created)->toDateString();
            } catch (\Throwable $e) {
            }
        }

        return now()->toDateString();
    }
}
