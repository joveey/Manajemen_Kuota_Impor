<?php

namespace App\Console\Commands;

use App\Models\PoHeader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RebuildForecast extends Command
{
    protected $signature = 'alloc:rebuild-forecast {--period=} {--dry-run}';
    protected $description = 'Reset and rebuild forecast allocations from PO lines for a given period (YYYY or YYYY-MM)';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        if (!$period) {
            $this->error('Please provide --period=YYYY or YYYY-MM');
            return Command::INVALID;
        }

        // Resolve date range from period
        if (preg_match('/^\d{4}$/', $period)) {
            $start = Carbon::create((int)$period, 1, 1)->startOfDay();
            $end = Carbon::create((int)$period, 12, 31)->endOfDay();
        } elseif (preg_match('/^\d{4}-\d{2}$/', $period)) {
            [$y, $m] = explode('-', $period);
            $start = Carbon::create((int)$y, (int)$m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
        } else {
            $this->error('Invalid period. Use YYYY or YYYY-MM');
            return Command::INVALID;
        }

        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        // Identify impacted PO headers and lines
        $headerIds = PoHeader::whereBetween('po_date', [$startStr, $endStr])->pluck('id');
        $poHeaderCount = $headerIds->count();
        $poLineCount = (int) DB::table('po_lines')->whereIn('po_header_id', $headerIds)->count();

        // Identify purchase orders (document table)
        $poNumbers = PoHeader::whereIn('id', $headerIds)->pluck('po_number');
        $poIds = DB::table('purchase_orders')->whereIn('po_number', $poNumbers)->pluck('id');
        $pivotCount = (int) DB::table('purchase_order_quota')->whereIn('purchase_order_id', $poIds)->count();

        // Forecast histories in the period
        $histCount = (int) DB::table('quota_histories')
            ->where('change_type', 'forecast_decrease')
            ->whereBetween('occurred_on', [$startStr, $endStr])
            ->count();

        // Quotas overlapping the range
        $quotaIds = DB::table('quotas')->where(function ($q) use ($startStr) {
            $q->whereNull('period_end')->orWhere('period_end', '>=', $startStr);
        })->where(function ($q) use ($endStr) {
            $q->whereNull('period_start')->orWhere('period_start', '<=', $endStr);
        })->pluck('id');
        $quotaCount = $quotaIds->count();

        $this->info("Period: $startStr .. $endStr");
        $this->line("PO headers: $poHeaderCount, PO lines: $poLineCount");
        $this->line("Existing pivot rows: $pivotCount, Forecast histories: $histCount, Quotas overlapping: $quotaCount");

        if ($dryRun) {
            $this->warn('Dry-run mode: no changes applied.');
            return Command::SUCCESS;
        }

        // 1) Reset allocation flags on PO lines (idempotent)
        $resetLines = (int) DB::table('po_lines')->whereIn('po_header_id', $headerIds)->update([
            'forecast_allocated_at' => null,
            'updated_at' => now(),
        ]);

        // 2) Clear pivot for affected purchase orders
        $deletedPivot = (int) DB::table('purchase_order_quota')->whereIn('purchase_order_id', $poIds)->delete();

        // 3) Remove forecast histories in the period
        $deletedHist = (int) DB::table('quota_histories')
            ->where('change_type', 'forecast_decrease')
            ->whereBetween('occurred_on', [$startStr, $endStr])
            ->delete();

        // 4) Reset forecast_remaining to total_allocation for overlapping quotas
        $resetQuotas = (int) DB::table('quotas')->whereIn('id', $quotaIds)->update([
            'forecast_remaining' => DB::raw('total_allocation'),
            'updated_at' => now(),
        ]);

        $this->info("Reset lines: $resetLines, Deleted pivot: $deletedPivot, Deleted histories: $deletedHist, Reset quotas: $resetQuotas");

        // 5) Re-run backfill allocation for the period
        $exit = Artisan::call('alloc:backfill-forecast', ['--period' => $period]);
        $this->line(Artisan::output());

        if ($exit !== Command::SUCCESS) {
            $this->error('Backfill failed. Please check logs.');
            return $exit;
        }

        $this->info('Rebuild forecast completed.');
        return Command::SUCCESS;
    }
}

