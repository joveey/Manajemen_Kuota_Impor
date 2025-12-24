<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\QuotaAllocationService;
use App\Support\PeriodRange;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AllocBackfillForecastV2 extends Command
{
    protected $signature = 'alloc:backfill-forecast-v2 {--period=}';

    protected $description = 'Backfill forecast allocation for purchase_orders based on period without modifying schema';

    public function handle(): int
    {
        $period = $this->option('period');
        if (empty($period) || !preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Option --period=YYYY-MM is required.');
            return Command::INVALID;
        }

        [$start, $end] = PeriodRange::monthYear((int) substr($period, 5, 2), (int) substr($period, 0, 4));
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $this->info(sprintf('Backfilling forecast for %s (%s .. %s)', $period, $startDate, $endDate));

        $query = PurchaseOrder::query()
            ->whereBetween('created_date', [$startDate, $endDate])
            ->whereNotNull('product_id')
            ->where('qty', '>', 0)
            ->whereNull('deleted_at')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('purchase_order_quota as poq')
                    ->whereColumn('poq.purchase_order_id', '=', 'purchase_orders.id');
            })
            ->orderBy('po_doc')
            ->orderBy('line_no');

        $total = $query->count();
        if ($total === 0) {
            $this->info('No purchase orders found for allocation.');
            return Command::SUCCESS;
        }

        $allocated = 0;
        $skippedNoProduct = 0;
        $skippedAllocated = 0;
        $errors = 0;

        $allocService = app(QuotaAllocationService::class);

        $query->chunkById(200, function ($chunk) use ($allocService, &$allocated, &$skippedNoProduct, &$skippedAllocated, &$errors) {
            foreach ($chunk as $po) {
                if (!$po->product_id) {
                    $skippedNoProduct++;
                    continue;
                }

                try {
                    $allocService->allocateForecast((int) $po->product_id, (int) $po->qty, $po->created_date ?? Carbon::now()->toDateString(), $po);
                    $allocated++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        });

        $this->table(['Total scanned','Allocated','Skipped (no product)','Skipped (already allocated)','Errors'], [
            [$total, $allocated, $skippedNoProduct, $skippedAllocated, $errors]
        ]);

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
