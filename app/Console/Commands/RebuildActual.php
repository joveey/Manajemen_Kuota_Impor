<?php

namespace App\Console\Commands;

use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RebuildActual extends Command
{
    protected $signature = 'alloc:rebuild-actual {--from=} {--to=} {--dry-run}';
    protected $description = 'Rebuild actual (good receipt) allocations by removing histories in range and re-backfilling from GR receipts';

    public function handle(): int
    {
        $from = (string) ($this->option('from') ?? '');
        $to = (string) ($this->option('to') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        if (!$from || !$to) {
            $this->error('Please provide --from=YYYY-MM-DD and --to=YYYY-MM-DD');
            return Command::INVALID;
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        // Identify affected quotas by GR and by period overlap
        $qidsByGr = DB::table('gr_receipts as gr')
            ->join('po_headers as ph','gr.po_no','=','ph.po_number')
            ->join('po_lines as pl', function($j){
                $j->on('pl.po_header_id','=','ph.id');
                $j->whereRaw("CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','','g') AS int) = CAST(regexp_replace(CAST(gr.line_no AS text),'[^0-9]','','g') AS int)");
            })
            ->whereBetween('gr.receive_date', [$startStr, $endStr])
            ->distinct()
            ->pluck('ph.id'); // placeholder for existence check

        $this->info("Range: $startStr .. $endStr; impacted_po_headers=".count($qidsByGr));

        if ($dryRun) {
            $histCount = DB::table('quota_histories')
                ->where('change_type','actual_decrease')
                ->whereBetween('occurred_on', [$startStr, $endStr])
                ->count();
            $this->line("Would delete histories: $histCount; then backfill from GR.");
            return Command::SUCCESS;
        }

        // 1) Remove actual histories within range
        $deleted = DB::table('quota_histories')
            ->where('change_type','actual_decrease')
            ->whereBetween('occurred_on', [$startStr, $endStr])
            ->delete();
        $this->info("Deleted histories: $deleted");

        // 2) Backfill actual from GR receipts
        $exit = Artisan::call('alloc:backfill-actual', ['--from' => $startStr, '--to' => $endStr]);
        $this->line(Artisan::output());
        if ($exit !== Command::SUCCESS) {
            $this->error('Backfill actual failed.');
            return $exit;
        }

        // 3) Recompute quotas.actual_remaining = total_allocation - SUM(actual_decrease) (all time)
        $sums = DB::table('quota_histories')
            ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
            ->where('change_type','actual_decrease')
            ->groupBy('quota_id')
            ->pluck('qty','quota_id');

        $updated = 0;
        Quota::query()->chunkById(500, function($rows) use ($sums, &$updated) {
            foreach ($rows as $q) {
                $alloc = (int) ($q->total_allocation ?? 0);
                $used = (int) ($sums[$q->id] ?? 0);
                $new = max($alloc - $used, 0);
                if ((int)$q->actual_remaining !== $new) {
                    DB::table('quotas')->where('id', $q->id)->update(['actual_remaining' => $new, 'updated_at' => now()]);
                    $updated++;
                }
            }
        });
        $this->info("Recomputed actual_remaining for $updated quotas.");
        $this->info('Rebuild actual completed.');
        return Command::SUCCESS;
    }
}
