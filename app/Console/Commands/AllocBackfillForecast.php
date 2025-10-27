<?php

namespace App\Console\Commands;

use App\Models\PoHeader;
use App\Models\PoLine;
use App\Models\Product;
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

        $processed = 0; $allocated = 0; $leftover = 0; $skipped = 0;

        PoHeader::whereBetween('po_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->chunkById(100, function ($headers) use (&$processed, &$allocated, &$leftover, &$skipped) {
                foreach ($headers as $hdr) {
                    $lines = PoLine::where('po_header_id', $hdr->id)
                        ->whereNull('forecast_allocated_at')
                        ->orderBy('id')->get();
                    foreach ($lines as $ln) {
                        $processed++;
                        $model = (string) $ln->model_code;
                        $product = Product::query()
                            ->whereRaw('LOWER(sap_model) = ?', [strtolower($model)])
                            ->orWhereRaw('LOWER(code) = ?', [strtolower($model)])
                            ->first();
                        if (!$product) {
                            $product = Product::create([
                                'code' => $model,
                                'name' => $model,
                                'sap_model' => $model,
                                'is_active' => true,
                            ]);
                        }

                        $po = PurchaseOrder::updateOrCreate([
                            'po_number' => (string) $hdr->po_number,
                        ], [
                            'product_id' => $product->id,
                            'quantity' => (int) ($ln->qty_ordered ?? 0),
                            'order_date' => $hdr->po_date?->toDateString() ?? now()->toDateString(),
                            'vendor_name' => (string) $hdr->supplier,
                            'status' => \App\Models\PurchaseOrder::STATUS_ORDERED,
                            'plant_name' => 'Backfill',
                            'plant_detail' => 'Backfill Forecast Allocation',
                        ]);

                        $need = (int) ($ln->qty_ordered ?? 0);
                        if ($need <= 0) { $skipped++; continue; }

                        [$allocs, $left] = app(QuotaAllocationService::class)
                            ->allocateForecast($product->id, $need, $po->order_date, $po);
                        $allocated += array_sum(array_map(fn($a) => (int)$a['qty'], $allocs));
                        $leftover += (int) $left;

                        // mark processed
                        DB::table('po_lines')->where('id', $ln->id)->update(['forecast_allocated_at' => now()]);
                    }
                }
            });

        $this->info("Processed: $processed, Allocated: $allocated, Leftover: $leftover, Skipped: $skipped");
        return Command::SUCCESS;
    }
}
