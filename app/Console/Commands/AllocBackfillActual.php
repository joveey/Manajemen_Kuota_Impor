<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AllocBackfillActual extends Command
{
    protected $signature = 'alloc:backfill-actual {--from=} {--to=}';
    protected $description = 'Backfill actual_decrease histories from GR receipts by receipt_date';

    public function handle(): int
    {
        $from = (string) ($this->option('from') ?? '');
        $to = (string) ($this->option('to') ?? '');
        if (!$from || !$to) {
            $this->error('Please provide --from=YYYY-MM-DD and --to=YYYY-MM-DD');
            return Command::INVALID;
        }
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $scanned = 0; $written = 0; $skippedExisting = 0; $noQuota = 0;

        DB::table('gr_receipts as gr')
            ->join('po_headers as ph','gr.po_no','=','ph.po_number')
            ->join('po_lines as pl', function($j){
                $j->on('pl.po_header_id','=','ph.id');
                // Normalize line numbers to avoid '030' vs 30 mismatches (PostgreSQL)
                $j->whereRaw("CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','','g') AS int) = CAST(regexp_replace(CAST(gr.line_no AS text),'[^0-9]','','g') AS int)");
            })
            ->whereBetween('gr.receive_date', [$start->toDateString(), $end->toDateString()])
            ->select([
                'gr.id as gr_id',
                'gr.po_no',
                'gr.line_no',
                'gr.receive_date',
                'gr.qty',
                'gr.gr_unique',
                'pl.model_code',
            ])
            // Use chunkById on the concrete primary key column from gr_receipts to avoid ambiguous "id"
            ->chunkById(500, function($rows) use (&$scanned,&$written,&$skippedExisting,&$noQuota){
                foreach ($rows as $r) {
                    $scanned++;
                    $uk = $r->gr_unique ?? sha1($r->po_no.$r->line_no.$r->receive_date.$r->qty);
                    $exists = DB::table('quota_histories')->where('change_type','actual_decrease')->where('meta->gr_unique',$uk)->exists();
                    if ($exists) { $skippedExisting++; continue; }

                    $product = null;
                    if (!empty($r->model_code)) {
                        $product = Product::query()
                            ->whereRaw('LOWER(sap_model) = ?', [strtolower($r->model_code)])
                            ->orWhereRaw('LOWER(code) = ?', [strtolower($r->model_code)])
                            ->first();
                    }
                    if (!$product) { $noQuota++; continue; }

                    $date = (string) $r->receive_date;
                    $quota = Quota::query()
                        ->where('is_active', true)
                        ->where(function($q) use ($date){ $q->whereNull('period_start')->orWhere('period_start','<=',$date); })
                        ->where(function($q) use ($date){ $q->whereNull('period_end')->orWhere('period_end','>=',$date); })
                        ->get()
                        ->first(function($q) use ($product){ return $q->matchesProduct($product); });

                    if (!$quota) { $noQuota++; continue; }

                    $qty = (int) $r->qty;
                    $quota->decrementActual($qty, sprintf('Backfill GR %s/%s pada %s', $r->po_no, $r->line_no, $date), null, new \DateTimeImmutable($date), null, [
                        'gr_unique' => $uk,
                        'po_no' => (string)$r->po_no,
                        'line_no' => (string)$r->line_no,
                    ]);
                    $written++;
                }
            }, 'gr.id', 'gr_id');

        $this->info("receipts_scanned=$scanned written=$written skipped_existing=$skippedExisting no_quota_found=$noQuota");
        return Command::SUCCESS;
    }
}
