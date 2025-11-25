<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Support\DbExpression;

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
                // Normalize line numbers to avoid '030' vs 30 mismatches across drivers
                $j->whereRaw(DbExpression::lineNoInt('pl.line_no').' = '.DbExpression::lineNoInt('gr.line_no'));
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
                    // Do not deduplicate GR lines: each receipt row counts toward actual consumption
                    $uk = $r->gr_unique ?? sha1($r->po_no.$r->line_no.$r->receive_date.$r->qty);

                    $product = null;
                    if (!empty($r->model_code)) {
                        $product = Product::query()
                            ->whereRaw('LOWER(sap_model) = ?', [strtolower($r->model_code)])
                            ->orWhereRaw('LOWER(code) = ?', [strtolower($r->model_code)])
                            ->first();
                    }
                    // Fallback: enrich or synthesize product from PO line's HS mapping
                    if (!$product || ($product && $product->pk_capacity === null)) {
                        $hsRow = DB::table('po_lines as pl')
                            ->leftJoin('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
                            ->join('po_headers as ph','pl.po_header_id','=','ph.id')
                            ->where('ph.po_number',$r->po_no)
                            ->whereRaw(DbExpression::lineNoInt('pl.line_no').' = '.DbExpression::lineNoInt('?'), [$r->line_no])
                            ->select('hs.hs_code','hs.pk_capacity')
                            ->first();
                        if ($hsRow) {
                            $pseudo = new Product();
                            $pseudo->hs_code = $hsRow->hs_code ?? null;
                            $pseudo->pk_capacity = $hsRow->pk_capacity ?? null;
                            $product = $product ?: $pseudo;
                            if ($product && ($product->pk_capacity === null)) { $product->pk_capacity = $pseudo->pk_capacity; }
                            if ($product && (empty($product->hs_code))) { $product->hs_code = $pseudo->hs_code; }
                        }
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

                    $qty = (int) round((float) $r->qty);
                    $quota->decrementActual($qty, sprintf('Backfill GR %s/%s pada %s', $r->po_no, $r->line_no, $date), null, new \DateTimeImmutable($date), null, [
                        'gr_unique' => $uk,
                        'gr_id' => (int)$r->gr_id,
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
