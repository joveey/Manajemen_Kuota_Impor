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

        $processed = 0; $allocated = 0; $leftover = 0; $skipped = 0; $unmapped = 0;

        PoHeader::whereBetween('po_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->chunkById(100, function ($headers) use (&$processed, &$allocated, &$leftover, &$skipped, &$unmapped) {
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
                            if (!$product) { $unmapped++; $skipped++; continue; }

                        // Ensure product has HS/PK mapping; prefer non-ACC when dual
                        $hsRow = DB::table('po_lines as pl')
                            ->leftJoin('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
                            ->where('pl.id', $ln->id)
                            ->select('hs.hs_code','hs.pk_capacity')
                            ->first();
                        $hsFromLine = $hsRow ? strtoupper((string)($hsRow->hs_code ?? '')) : '';
                        $prodHs = strtoupper((string) ($product->hs_code ?? ''));
                        $useHs = $prodHs;
                        $usePk = $product->pk_capacity;
                        if ($hsFromLine !== '') {
                            if ($prodHs === '' || $prodHs === 'ACC') {
                                $useHs = $hsFromLine !== '' ? $hsFromLine : $prodHs;
                                $usePk = $hsRow->pk_capacity ?? $usePk;
                            }
                            if ($prodHs !== 'ACC' && $prodHs !== '') {
                                $useHs = $prodHs; // keep non-ACC
                            } elseif ($hsFromLine !== 'ACC' && $hsFromLine !== '') {
                                $useHs = $hsFromLine;
                                $usePk = $hsRow->pk_capacity ?? $usePk;
                            }
                        }
                        // Persist if product missing HS or better mapping available (non-ACC)
                        if ($useHs !== $prodHs || ($product->pk_capacity === null && $usePk !== null)) {
                            $product->hs_code = $useHs ?: $product->hs_code;
                            if ($usePk !== null) { $product->pk_capacity = $usePk; }
                            try { $product->save(); } catch (\Throwable $e) {}
                        }

                        $delivery = $ln->eta_date ? $ln->eta_date->toDateString() : ($hdr->po_date?->toDateString() ?? now()->toDateString());
                        $po = PurchaseOrder::updateOrCreate([
                            'po_number' => (string) $hdr->po_number,
                        ], [
                            'product_id' => $product->id,
                            'quantity' => (int) ($ln->qty_ordered ?? 0),
                            'order_date' => $delivery,
                            'vendor_name' => (string) $hdr->supplier,
                            'status' => \App\Models\PurchaseOrder::STATUS_ORDERED,
                            'plant_name' => 'Backfill',
                            'plant_detail' => 'Backfill Forecast Allocation',
                        ]);

                        $need = (int) ($ln->qty_ordered ?? 0);
                        if ($need <= 0) { $skipped++; continue; }

                        [$allocs, $left] = app(QuotaAllocationService::class)
                            ->allocateForecast($product->id, $need, $delivery, $po);
                        $allocated += array_sum(array_map(fn($a) => (int)$a['qty'], $allocs));
                        $leftover += (int) $left;

                        // mark processed
                        DB::table('po_lines')->where('id', $ln->id)->update(['forecast_allocated_at' => now()]);
                    }
                }
            });

        // Safety net: ensure pivot purchase_order_quota reflects forecast histories for the same period
        $hist = \Illuminate\Support\Facades\DB::table('quota_histories')
            ->where('change_type', \App\Models\QuotaHistory::TYPE_FORECAST_DECREASE)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->where('reference_type', \App\Models\PurchaseOrder::class)
            ->whereNotNull('reference_id')
            ->select('reference_id as purchase_order_id', 'quota_id', \Illuminate\Support\Facades\DB::raw('SUM(ABS(quantity_change)) as qty'))
            ->groupBy('purchase_order_id', 'quota_id')
            ->get();

        $rows = [];
        foreach ($hist as $h) {
            $rows[] = [
                'purchase_order_id' => (int) $h->purchase_order_id,
                'quota_id' => (int) $h->quota_id,
                'allocated_qty' => (int) round((float) $h->qty),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($rows)) {
            \Illuminate\Support\Facades\DB::table('purchase_order_quota')
                ->upsert($rows, ['purchase_order_id','quota_id'], ['allocated_qty','updated_at']);
        }

        $this->info("Processed: $processed, Allocated: $allocated, Leftover: $leftover, Skipped: $skipped, Unmapped model: $unmapped");
        return Command::SUCCESS;
    }
}

