<?php

namespace App\Console\Commands;

use App\Models\SapPurchaseOrderAllocation;
use App\Services\SapForecastAllocationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AllocBackfillForecastSap extends Command
{
    protected $signature = 'alloc:backfill-forecast-sap {--period=}';

    protected $description = 'Backfill forecast allocation using SAP purchase_orders table without altering SAP schema.';

    public function handle(): int
    {
        $period = $this->option('period');
        if (!$period) {
            $this->error('Please provide --period=YYYY or YYYY-MM');
            return Command::INVALID;
        }

        [$start, $end] = $this->resolvePeriodRange($period);
        if (!$start || !$end) {
            $this->error('Invalid period. Use YYYY or YYYY-MM');
            return Command::INVALID;
        }

        $this->info(sprintf('Processing SAP purchase orders between %s and %s', $start, $end));

        $service = app(SapForecastAllocationService::class);
        $table = config('quota.sap_po_table', 'purchase_orders');

        $processed = 0;
        $skipped = 0;
        $allocatedChange = 0;
        $releasedChange = 0;
        $errors = 0;
        $activeKeys = [];
        DB::table(DB::raw($table))
            ->whereBetween(DB::raw('CAST(created_date AS DATE)'), [$start, $end])
            ->orderBy('po_doc')
            ->orderBy('line_no')
            ->chunk(500, function (Collection $rows) use (
                $service,
                &$processed,
                &$skipped,
                &$allocatedChange,
                &$releasedChange,
                &$errors,
                &$activeKeys
            ) {
                foreach ($rows as $row) {
                    $poDoc = trim((string) ($row->po_doc ?? $row->PO_DOC ?? ''));
                    $lineRaw = $row->line_no ?? $row->LINE_NO ?? null;
                    $lineNo = $this->normalizeLineNo($lineRaw);
                    $itemCode = $row->item_code ?? $row->ITEM_CODE ?? null;
                    $qty = $row->qty ?? $row->QTY ?? null;
                    $createdDate = $row->created_date ?? $row->CREATED_DATE ?? null;

                    if ($poDoc === '' || $lineNo === '' || !$itemCode) {
                        $skipped++;
                        continue;
                    }

                    $processed++;
                    $activeKeys[$poDoc.'#'.$lineNo] = true;

                    try {
                        $result = $service->syncFromSapRow([
                            'po_doc' => $poDoc,
                            'line_no' => $lineRaw,
                            'item_code' => $itemCode,
                            'qty' => $qty,
                            'order_date' => $createdDate,
                            'vendor_no' => $row->vendor_no ?? $row->VENDOR_NO ?? null,
                            'vendor_name' => $row->vendor_name ?? $row->VENDOR_NAME ?? null,
                        ]);

                        $allocatedChange += (int) ($result['allocated_change'] ?? 0);
                        $releasedChange += (int) ($result['released_change'] ?? 0);
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn(sprintf(
                            'Failed PO %s-%s: %s',
                            $poDoc,
                            $lineNo,
                            $e->getMessage()
                        ));
                    }
                }
            });

        $releasedChange += $this->releaseInactiveAllocations($service, $start, $end, $activeKeys);

        $this->table(
            ['Processed', 'Skipped', 'Allocated+', 'Released+', 'Errors'],
            [[
                $processed,
                $skipped,
                $allocatedChange,
                $releasedChange,
                $errors,
            ]]
        );

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function resolvePeriodRange(string $period): array
    {
        if (preg_match('/^\d{4}$/', $period)) {
            $start = Carbon::create((int) $period, 1, 1)->startOfDay();
            $end = Carbon::create((int) $period, 12, 31)->endOfDay();
            return [$start->toDateString(), $end->toDateString()];
        }

        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            [$y, $m] = explode('-', $period);
            $start = Carbon::create((int) $y, (int) $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            return [$start->toDateString(), $end->toDateString()];
        }

        return [null, null];
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
        return $trimmed === '' ? '0' : $trimmed;
    }

    private function releaseInactiveAllocations(
        SapForecastAllocationService $service,
        string $start,
        string $end,
        array $activeKeys
    ): int {
        $released = 0;
        $keySet = array_fill_keys(array_keys($activeKeys), true);

        SapPurchaseOrderAllocation::query()
            ->whereBetween(DB::raw('CAST(order_date AS DATE)'), [$start, $end])
            ->where('is_active', true)
            ->chunk(200, function ($chunk) use (&$released, $service, $keySet) {
                foreach ($chunk as $allocation) {
                    $key = $allocation->po_doc.'#'.$allocation->po_line_no;
                    if (isset($keySet[$key])) {
                        continue;
                    }

                    $released += $service->releaseMissingAllocation($allocation);
                }
            });

        return $released;
    }
}
