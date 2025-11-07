<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuotaExportController extends Controller
{
    public function export(Request $request)
    {
        $year = (int) $request->query('year', (int) now()->year);
        if ($year < 1900 || $year > 9999) { $year = (int) now()->year; }
        $periodStart = Carbon::create($year, 1, 1)->toDateString();
        $periodEnd   = Carbon::create($year, 12, 31)->toDateString();

        $actualMonths = [2,3,4,5,7,8,9,10];
        $actualLabels = ['Feb','Mar','Apr','May','Jul','Aug','Sep','Oct'];

        // Build HS master from hs_code_pk_mappings (repo schema)
        // TODO: If hs_codes/pk_ranges exist, adapt mapping to those tables.
        $hsMaster = DB::table('hs_code_pk_mappings')
            ->select(['id','hs_code', DB::raw('COALESCE(pk_capacity,0) as pk_capacity')])
            ->get();
        $hsLabel = [];
        $hsBucket = [];
        foreach ($hsMaster as $m) {
            $hsLabel[(int) $m->id] = (string) ($m->hs_code ?? 'N/A');
            $cap = (float) $m->pk_capacity;
            // Simple bucketization; TODO: unify with pk_ranges if table exists
            $hsBucket[(int) $m->id] = ($cap < 8.0) ? '<8PK' : (($cap <= 10.0) ? '8PK - 10PK' : '>10PK');
        }

        // Quota Approved per PK bucket (overlapping the year)
        // TODO: When quotas normalized by HS+PK, change to group by hs_code_id, pk_range_id
        $quotaRows = DB::table('quotas')
            ->select([
                DB::raw('government_category'),
                DB::raw('SUM(COALESCE(total_allocation, 0))::bigint as qty'),
            ])
            ->where(function($q) use ($periodEnd) {
                $q->whereNull('period_start')->orWhere('period_start','<=',$periodEnd);
            })
            ->where(function($q) use ($periodStart) {
                $q->whereNull('period_end')->orWhere('period_end','>=',$periodStart);
            })
            ->groupBy('government_category')
            ->get();
        $quotaApprovedByBucket = [];
        foreach ($quotaRows as $r) {
            // Parse PK bucket from government_category label
            $cat = (string) ($r->government_category ?? '');
            $bucket = '8PK - 10PK';
            try {
                $p = \App\Support\PkCategoryParser::parse($cat);
                $min = $p['min_pk']; $max = $p['max_pk'];
                if ($max !== null && $max < 8) { $bucket = '<8PK'; }
                elseif ($min !== null && $min > 10) { $bucket = '>10PK'; }
                else { $bucket = '8PK - 10PK'; }
            } catch (\Throwable $e) { $bucket = '8PK - 10PK'; }
            $quotaApprovedByBucket[$bucket] = (int) ($quotaApprovedByBucket[$bucket] ?? 0) + (int) $r->qty;
        }

        // Actual per month from GR (normalized line number join)
        $gr = DB::table('gr_receipts as gr')
            ->join('po_headers as ph','ph.po_number','=','gr.po_no')
            ->join('po_lines as pl', function($j){
                $j->on('pl.po_header_id','=','ph.id')
                  ->whereRaw("CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','','g') AS INTEGER) = CAST(regexp_replace(CAST(gr.line_no AS text),'[^0-9]','','g') AS INTEGER)");
            })
            ->whereBetween('gr.receive_date', [$periodStart, $periodEnd])
            ->select([
                DB::raw('pl.hs_code_id'),
                DB::raw('EXTRACT(MONTH FROM gr.receive_date)::int AS m'),
                DB::raw('SUM(gr.qty)::bigint AS qty'),
            ])
            ->groupBy('pl.hs_code_id', DB::raw('EXTRACT(MONTH FROM gr.receive_date)'))
            ->get();
        $actualMap = [];
        foreach ($gr as $r) {
            $m = (int) $r->m; if (!in_array($m, $actualMonths, true)) { continue; }
            $hsId = (int) $r->hs_code_id; $bucket = $hsBucket[$hsId] ?? '8PK - 10PK';
            $key = $hsId.'|'.$bucket;
            if (!isset($actualMap[$key])) { $actualMap[$key] = []; }
            $actualMap[$key][$m] = (int) $r->qty;
        }

        // Planning per month from PO (COALESCE(eta_date, po_date))
        $planDateExpr = "COALESCE(pl.eta_date, ph.po_date)";
        $pln = DB::table('po_lines as pl')
            ->join('po_headers as ph','pl.po_header_id','=','ph.id')
            ->whereBetween(DB::raw($planDateExpr), [$periodStart, $periodEnd])
            ->select([
                DB::raw('pl.hs_code_id'),
                DB::raw("EXTRACT(MONTH FROM $planDateExpr)::int AS m"),
                DB::raw('SUM(COALESCE(pl.qty_ordered, 0))::bigint AS qty'),
            ])
            ->groupBy('pl.hs_code_id', DB::raw("EXTRACT(MONTH FROM $planDateExpr)"))
            ->get();
        $planMap = [];
        foreach ($pln as $r) {
            $m = (int) $r->m; if ($m < 1 || $m > 12) { continue; }
            $hsId = (int) $r->hs_code_id; $bucket = $hsBucket[$hsId] ?? '8PK - 10PK';
            $key = $hsId.'|'.$bucket;
            if (!isset($planMap[$key])) { $planMap[$key] = []; }
            $planMap[$key][$m] = (int) $r->qty;
        }

        // Labels
        $hsMap = $hsLabel; // id => hs code

        // Build universe keys
        $keys = [];
        foreach ([$actualMap, $planMap] as $maps) {
            foreach ($maps as $k => $_) { $keys[$k] = true; }
        }
        ksort($keys);

        $filename = 'quotas_'.$year.'_matrix.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($keys, $quotaApprovedByBucket, $actualMap, $planMap, $hsMap, $actualMonths, $actualLabels) {
            $out = fopen('php://output', 'w');
            // Header columns (ASCII hyphen for portability)
            $header = array_merge(
                ['HS Code','Capacity (PK bucket)','Quota Approved'],
                array_map(fn($l) => 'Actual '.$l, $actualLabels),
                ['Planning Total Feb-Oct','Planning Nov','Planning Dec','Total Feb-Dec']
            );
            fputcsv($out, $header);

            $grand = array_fill_keys(['qa','a2','a3','a4','a5','a7','a8','a9','a10','pfeboct','pnov','pdec','ptotal'], 0);

            foreach (array_keys($keys) as $key) {
                [$hsIdStr, $bucketLabel] = explode('|', $key, 2);
                $hsId = (int) $hsIdStr;
                $hs = $hsMap[$hsId] ?? 'N/A';
                $pk = (string) $bucketLabel;

                // Quota Approved by bucket (cannot split per HS with current schema)
                $qa = (int) ($quotaApprovedByBucket[$pk] ?? 0);
                $aMap = $actualMap[$key] ?? [];
                $aVals = [];
                foreach ($actualMonths as $m) { $aVals[$m] = (int) ($aMap[$m] ?? 0); }

                $pMap = $planMap[$key] ?? [];
                $pFebOct = 0; for ($m=2; $m<=10; $m++) { $pFebOct += (int) ($pMap[$m] ?? 0); }
                $pNov = (int) ($pMap[11] ?? 0);
                $pDec = (int) ($pMap[12] ?? 0);
                $pTotal = 0; for ($m=2; $m<=12; $m++) { $pTotal += (int) ($pMap[$m] ?? 0); }

                fputcsv($out, [
                    $hs,
                    $pk,
                    $qa,
                    $aVals[2], $aVals[3], $aVals[4], $aVals[5], $aVals[7], $aVals[8], $aVals[9], $aVals[10],
                    $pFebOct, $pNov, $pDec, $pTotal,
                ]);

                $grand['qa'] += $qa;
                $grand['a2'] += $aVals[2];
                $grand['a3'] += $aVals[3];
                $grand['a4'] += $aVals[4];
                $grand['a5'] += $aVals[5];
                $grand['a7'] += $aVals[7];
                $grand['a8'] += $aVals[8];
                $grand['a9'] += $aVals[9];
                $grand['a10'] += $aVals[10];
                $grand['pfeboct'] += $pFebOct;
                $grand['pnov'] += $pNov;
                $grand['pdec'] += $pDec;
                $grand['ptotal'] += $pTotal;
            }

            // Grand Total row
            fputcsv($out, [
                'Grand Total','',
                $grand['qa'],
                $grand['a2'],$grand['a3'],$grand['a4'],$grand['a5'],$grand['a7'],$grand['a8'],$grand['a9'],$grand['a10'],
                $grand['pfeboct'],$grand['pnov'],$grand['pdec'],$grand['ptotal'],
            ]);

            fclose($out);
        }, $filename, $headers);
    }
}
