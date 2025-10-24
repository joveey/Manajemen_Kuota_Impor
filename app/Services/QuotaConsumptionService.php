<?php

namespace App\Services;

use App\Models\Quota;
use Illuminate\Support\Facades\DB;
use App\Support\PkCategoryParser;

class QuotaConsumptionService
{
    /**
     * Compute actual/forecast consumption per quota using Invoice-based forecast and GR actuals.
     * Does NOT modify mappings or persist changes.
     *
     * @param \Illuminate\Support\Collection<int,Quota>|array $quotas
     * @return array<int,array{actual_consumed:float,forecast_consumed:float,actual_remaining:float,forecast_remaining:float}>
     */
    public function computeForQuotas($quotas): array
    {
        $quotasArr = [];
        foreach ($quotas as $q) {
            $p = PkCategoryParser::parse((string) $q->government_category);
            $quotasArr[$q->id] = [
                'min_pk' => $p['min_pk'],
                'max_pk' => $p['max_pk'],
                'min_incl' => $p['min_incl'],
                'max_incl' => $p['max_incl'],
                'start' => $q->period_start ? $q->period_start->toDateString() : null,
                'end'   => $q->period_end ? $q->period_end->toDateString() : null,
                'allocation' => (float)($q->total_allocation ?? 0),
            ];
        }

        if (empty($quotasArr)) { return []; }

        // Pre-aggregate invoices and GR per (po_no,line_no)
        $inv = DB::table('invoices')
            ->select('po_no','line_no', DB::raw('SUM(qty) as qty_invoiced'))
            ->groupBy('po_no','line_no');
        $gr = DB::table('gr_receipts')
            ->select('po_no','line_no', DB::raw('SUM(qty) as qty_received'))
            ->groupBy('po_no','line_no');

        // Pull all PO lines with HS pk capacity and ordered qty, join header for date + po_number
        $lines = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
            ->leftJoinSub($inv, 'inv', function($j){ $j->on('ph.po_number','=','inv.po_no')->on('pl.line_no','=','inv.line_no'); })
            ->leftJoinSub($gr, 'gr', function($j){ $j->on('ph.po_number','=','gr.po_no')->on('pl.line_no','=','gr.line_no'); })
            ->get([
                'ph.po_number as po_no',
                'pl.line_no',
                'pl.qty_ordered as ordered',
                DB::raw('COALESCE(inv.qty_invoiced,0) as invoiced'),
                DB::raw('COALESCE(gr.qty_received,0) as received'),
                'hs.pk_capacity',
                'ph.po_date',
            ]);

        $stats = [];
        foreach ($quotas as $q) {
            $stats[$q->id] = [
                'actual_consumed' => 0.0,
                'forecast_consumed' => 0.0,
                'actual_remaining' => (float)($q->total_allocation ?? 0),
                'forecast_remaining' => (float)($q->total_allocation ?? 0),
            ];
        }

        foreach ($lines as $ln) {
            $pk = isset($ln->pk_capacity) ? (float) $ln->pk_capacity : null;
            if ($pk === null) { continue; }

            $ordered = (float) $ln->ordered;
            $invQty = (float) $ln->invoiced;
            $recQty = (float) $ln->received;
            $inTransit = max(min($invQty - $recQty, $ordered - $recQty), 0.0);

            foreach ($quotasArr as $qid => $q) {
                // Period filter: use PO date within [start,end] if defined
                if ($q['start'] && $q['end']) {
                    $d = $ln->po_date ? (string)$ln->po_date : null;
                    if (!$d || $d < $q['start'] || $d > $q['end']) { continue; }
                }
                // PK bucket filter
                $ok = true;
                if ($q['min_pk'] !== null && $q['max_pk'] !== null) {
                    $ok = $pk >= $q['min_pk'] && $pk <= $q['max_pk'];
                } elseif ($q['min_pk'] !== null && $q['max_pk'] === null) {
                    $ok = $pk > $q['min_pk'];
                } elseif ($q['min_pk'] === null && $q['max_pk'] !== null) {
                    $ok = $pk < $q['max_pk'];
                }
                if (!$ok) { continue; }

                $stats[$qid]['actual_consumed'] += $recQty;
                $stats[$qid]['forecast_consumed'] += $recQty + $inTransit;
            }
        }

        foreach ($stats as $qid => &$s) {
            $alloc = $quotasArr[$qid]['allocation'];
            $s['actual_remaining'] = max($alloc - $s['actual_consumed'], 0.0);
            $s['forecast_remaining'] = max($alloc - $s['forecast_consumed'], 0.0);
        }

        return $stats;
    }
}

