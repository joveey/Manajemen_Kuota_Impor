<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrReceipt;
use App\Models\Product;
use App\Models\PoHeader;
use App\Models\PoLine;
use App\Models\Quota;
use App\Support\PkCategoryParser;
use App\Support\DbExpression;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class FinalReportController extends Controller
{
    public function index(Request $request): View
    {
        $dataset = $this->buildDataset($request);

        return view('admin.reports.final', [
            'filters' => $dataset['filters'],
            'summary' => $dataset['summary'],
            'rows' => $dataset['rows'],
            'charts' => $dataset['charts'],
            'outstanding' => $dataset['outstanding'],
        ]);
    }

    // Combined Report detailed export: expand Voyage splits into CSV rows
    public function exportCsv(Request $request)
    {
        // Build rows to match the Voyage page (parent + splits). Read-only.
        $year = (int) ($request->query('year') ?: now()->year);
        $rows = $this->buildVoyageLikeRows($year)->all();

        // Final ordering: by PO, then Line No, then seq (base first, then splits)
        usort($rows, function (array $a, array $b) {
            $poA = (string) ($a['_sort_po'] ?? ($a['Purchasing Document'] ?? ''));
            $poB = (string) ($b['_sort_po'] ?? ($b['Purchasing Document'] ?? ''));
            if ($poA === $poB) {
                $lnA = (int) ($a['_sort_line'] ?? 0);
                $lnB = (int) ($b['_sort_line'] ?? 0);
                if ($lnA === $lnB) {
                    $sqA = (int) ($a['_sort_seq'] ?? 0);
                    $sqB = (int) ($b['_sort_seq'] ?? 0);
                    return $sqA <=> $sqB;
                }
                return $lnA <=> $lnB;
            }
            return strcmp($poA, $poB);
        });

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="combined_report_detailed.csv"',
        ];

        $columns = [
            'Month','Purchasing Doc. Type','Vendor/supplying plant','Purchasing Document','Material','Plant','Storage Location','Order Quantity','Still to be invoiced (qty)','Still to be delivered (qty)','Delivery Date','Document Date','header text','BL','ETD','ETA','Factory','Hs Code','Status','Status Quota','Quota No.','Issue Date','Expired','Remark'
        ];

        $callback = function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                // Remove sort helper keys from output
                unset($row['_sort_po'], $row['_sort_line'], $row['_sort_seq']);
                $record = [];
                foreach ($columns as $key) { $record[] = $row[$key] ?? ''; }
                fputcsv($out, $record);
            }
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    protected function buildVoyageLikeRows(int $year): \Illuminate\Support\Collection
    {
        $yearStart = Carbon::create($year, 1, 1)->toDateString();
        $yearEnd   = Carbon::create($year, 12, 31)->toDateString();

        // Pull parent + split rows (one row per vs.id, plus a parent row when no split)
        $rows = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
            ->leftJoin('po_line_voyage_splits as vs', 'vs.po_line_id', '=', 'pl.id')
            ->whereBetween('ph.po_date', [$yearStart, $yearEnd])
            ->orderBy('ph.po_number')
            ->orderBy('pl.line_no')
            ->orderBy('vs.seq_no')
            ->get([
                'pl.id as line_id',
                'ph.po_number', 'ph.po_date', 'ph.supplier', 'ph.note as header_text',
                'pl.line_no', 'pl.model_code as material', 'pl.item_desc',
                'pl.qty_ordered', 'pl.eta_date', 'pl.warehouse_code', 'pl.warehouse_name', 'pl.storage_location', 'pl.sap_order_status',
                'pl.voyage_bl', 'pl.voyage_etd', 'pl.voyage_eta', 'pl.voyage_factory', 'pl.voyage_status', 'pl.voyage_remark',
                'hs.hs_code',
                'vs.id as split_id', 'vs.seq_no', 'vs.qty as split_qty',
                'vs.voyage_bl as split_bl', 'vs.voyage_etd as split_etd', 'vs.voyage_eta as split_eta',
                'vs.voyage_factory as split_factory', 'vs.voyage_status as split_status', 'vs.voyage_remark as split_remark',
            ]);

        if ($rows->isEmpty()) {
            return collect();
        }

        // GR per line (sum across all dates) using normalized line number
        $poNumbers = $rows->pluck('po_number')->filter()->unique()->values();
        $grIndex = [];
        if ($poNumbers->isNotEmpty()) {
            $grRows = DB::table('gr_receipts as gr')
                ->whereIn('gr.po_no', $poNumbers)
                ->select(['gr.po_no', 'gr.line_no', DB::raw('SUM(gr.qty) as total_qty')])
                ->groupBy('gr.po_no', 'gr.line_no')
                ->get();
            foreach ($grRows as $g) {
                $po = (string) $g->po_no;
                $ln = preg_replace('/^0+/', '', (string) ($g->line_no ?? ''));
                $grIndex[$po][$ln] = (float) ($grIndex[$po][$ln] ?? 0) + (float) ($g->total_qty ?? 0);
            }
        }

        // Sum split qty per line and collect a representative parent row per line
        $sumSplitByLine = [];
        $parentByLine = [];
        foreach ($rows as $r) {
            $parentByLine[$r->line_id] = $r; // keep last as parent reference
            if (!empty($r->split_id)) {
                $sumSplitByLine[$r->line_id] = (float) ($sumSplitByLine[$r->line_id] ?? 0) + (float) ($r->split_qty ?? 0);
            }
        }

        $out = [];
        $linesWithSplit = [];
        foreach ($rows as $r) {
            $po = (string) ($r->po_number ?? '');
            $lineNo = (string) ($r->line_no ?? '');
            $lineNorm = preg_replace('/^0+/', '', $lineNo);
            $parentQty = (float) ($r->qty_ordered ?? 0);
            $totalGr = (float) ($grIndex[$po][$lineNorm] ?? 0);
            $hasSplit = !empty($r->split_id) || (isset($sumSplitByLine[$r->line_id]) && $sumSplitByLine[$r->line_id] > 0);

            // Build a row per split; if no split for this parent line_id at all, emit one parent row
            if (!empty($r->split_id)) {
                $sq = (float) ($r->split_qty ?? 0);
                $sumForLine = max((float) ($sumSplitByLine[$r->line_id] ?? 0), 0.00001);
                $alloc = min($sq, ($totalGr * ($sq / $sumForLine)));
                $outstanding = max($sq - $alloc, 0.0);
                $linesWithSplit[$r->line_id] = true;

                $out[] = [
                    // Month for split row is derived from split ETA/ETD (fallback parent ETA/PO date)
                    'Month' => ($r->split_eta ? Carbon::parse($r->split_eta)->format('M') : ($r->split_etd ? Carbon::parse($r->split_etd)->format('M') : ($r->eta_date ? Carbon::parse($r->eta_date)->format('M') : ($r->po_date ? Carbon::parse($r->po_date)->format('M') : '')))),
                    'Purchasing Doc. Type' => '',
                    'Vendor/supplying plant' => (string) ($r->supplier ?? ''),
                    'Purchasing Document' => $po,
                    'Material' => (string) ($r->material ?? ''),
                    'Plant' => (string) (($r->warehouse_code ?? '') !== '' ? $r->warehouse_code : ($r->warehouse_name ?? '')),
                    'Storage Location' => (string) ($r->storage_location ?? ''),
                    'Order Quantity' => $sq,
                    'Still to be invoiced (qty)' => $outstanding,
                    'Still to be delivered (qty)' => $outstanding,
                    'Delivery Date' => $r->split_eta ? Carbon::parse($r->split_eta)->toDateString() : ($r->eta_date ? Carbon::parse($r->eta_date)->toDateString() : ''),
                    'Document Date' => $r->po_date ? Carbon::parse($r->po_date)->toDateString() : '',
                    'header text' => (string) ($r->header_text ?? ''),
                    'BL' => (string) ($r->split_bl ?? ''),
                    'ETD' => $r->split_etd ? Carbon::parse($r->split_etd)->toDateString() : '',
                    'ETA' => $r->split_eta ? Carbon::parse($r->split_eta)->toDateString() : '',
                    'Factory' => (string) ($r->split_factory ?? ''),
                    'Hs Code' => (string) ($r->hs_code ?? ''),
                    'Status' => (string) (($r->sap_order_status ?? '') !== '' ? $r->sap_order_status : ($r->split_status ?? '')),
                    'Status Quota' => '',
                    'Quota No.' => '',
                    'Issue Date' => '',
                    'Expired' => '',
                    'Remark' => (string) (($r->split_remark ?? '') !== '' ? $r->split_remark : ''),
                    '_sort_po' => $po,
                    '_sort_line' => (int) ($r->line_no ?? 0),
                    '_sort_seq' => (int) ($r->seq_no ?? 0),
                ];
            } elseif (!$hasSplit) {
                // Unsplitted line: emit parent row once
                $outstanding = max($parentQty - $totalGr, 0.0);
                $out[] = [
                    // Month for base (unsplit) row follows current behavior: prefer ETA month
                    'Month' => ($r->eta_date ? Carbon::parse($r->eta_date)->format('M') : ($r->po_date ? Carbon::parse($r->po_date)->format('M') : '')),
                    'Purchasing Doc. Type' => '',
                    'Vendor/supplying plant' => (string) ($r->supplier ?? ''),
                    'Purchasing Document' => $po,
                    'Material' => (string) ($r->material ?? ''),
                    'Plant' => (string) (($r->warehouse_code ?? '') !== '' ? $r->warehouse_code : ($r->warehouse_name ?? '')),
                    'Storage Location' => (string) ($r->storage_location ?? ''),
                    'Order Quantity' => $parentQty,
                    'Still to be invoiced (qty)' => $outstanding,
                    'Still to be delivered (qty)' => $outstanding,
                    'Delivery Date' => $r->eta_date ? Carbon::parse($r->eta_date)->toDateString() : '',
                    'Document Date' => $r->po_date ? Carbon::parse($r->po_date)->toDateString() : '',
                    'header text' => (string) ($r->header_text ?? ''),
                    'BL' => (string) ($r->voyage_bl ?? ''),
                    'ETD' => $r->voyage_etd ? Carbon::parse($r->voyage_etd)->toDateString() : '',
                    'ETA' => ($r->voyage_eta ? Carbon::parse($r->voyage_eta)->toDateString() : ($r->eta_date ? Carbon::parse($r->eta_date)->toDateString() : '')),
                    'Factory' => (string) ($r->voyage_factory ?? ''),
                    'Hs Code' => (string) ($r->hs_code ?? ''),
                    'Status' => (string) ($r->sap_order_status ?? ''),
                    'Status Quota' => '',
                    'Quota No.' => '',
                    'Issue Date' => '',
                    'Expired' => '',
                    'Remark' => '',
                    '_sort_po' => $po,
                    '_sort_line' => (int) ($r->line_no ?? 0),
                    '_sort_seq' => 0,
                ];
            }
        }

        // For lines that have splits, emit a base remaining row if parent qty > sum(split qty)
        foreach ($parentByLine as $lineId => $parent) {
            if (empty($linesWithSplit[$lineId])) { continue; }
            $po = (string) ($parent->po_number ?? '');
            $baseQty = max((float) ($parent->qty_ordered ?? 0) - (float) ($sumSplitByLine[$lineId] ?? 0), 0.0);
            if ($baseQty <= 0) { continue; }
            $out[] = [
                'Month' => ($parent->eta_date ? Carbon::parse($parent->eta_date)->format('M') : ($parent->po_date ? Carbon::parse($parent->po_date)->format('M') : '')),
                'Purchasing Doc. Type' => '',
                'Vendor/supplying plant' => (string) ($parent->supplier ?? ''),
                'Purchasing Document' => $po,
                'Material' => (string) ($parent->material ?? ''),
                'Plant' => (string) (($parent->warehouse_code ?? '') !== '' ? $parent->warehouse_code : ($parent->warehouse_name ?? '')),
                'Storage Location' => (string) ($parent->storage_location ?? ''),
                'Order Quantity' => $baseQty,
                'Still to be invoiced (qty)' => $baseQty,
                'Still to be delivered (qty)' => $baseQty,
                'Delivery Date' => $parent->eta_date ? Carbon::parse($parent->eta_date)->toDateString() : '',
                'Document Date' => $parent->po_date ? Carbon::parse($parent->po_date)->toDateString() : '',
                'header text' => (string) ($parent->header_text ?? ''),
                'BL' => (string) ($parent->voyage_bl ?? ''),
                'ETD' => $parent->voyage_etd ? Carbon::parse($parent->voyage_etd)->toDateString() : '',
                'ETA' => ($parent->voyage_eta ? Carbon::parse($parent->voyage_eta)->toDateString() : ($parent->eta_date ? Carbon::parse($parent->eta_date)->toDateString() : '')),
                'Factory' => (string) ($parent->voyage_factory ?? ''),
                'Hs Code' => (string) ($parent->hs_code ?? ''),
                'Status' => (string) ($parent->sap_order_status ?? ''),
                'Status Quota' => '',
                'Quota No.' => '',
                'Issue Date' => '',
                'Expired' => '',
                'Remark' => (string) ($parent->voyage_remark ?? ''),
                '_sort_po' => $po,
                '_sort_line' => (int) ($parent->line_no ?? 0),
                '_sort_seq' => 0,
            ];
        }

        return collect($out);
    }

    /**
     * Build dataset for the final report page & export.
     *
     * @return array{
     *     filters: array{start_date:string,end_date:string,year:int},
     *     summary: array<string,float|int>,
     *     rows: array<int,array<string,mixed>>,
     *     charts: array<string,mixed>,
     *     outstanding: array<int,array<string,mixed>>
     * }
     */
    private function buildDataset(Request $request): array
    {
        [$start, $end] = $this->resolveRange($request);
        $startString = $start->toDateString();
        $endString = $end->toDateString();
        $driver = DB::connection()->getDriverName();

        $poHeaders = PoHeader::query()
            ->with(['lines' => function ($query) {
                $query->select(
                    'id',
                    'po_header_id',
                    'line_no',
                    'model_code',
                    'item_desc',
                    'qty_ordered',
                    'eta_date',
                    'sap_order_status'
                );
            }])
            ->whereBetween('po_date', [$startString, $endString])
            ->orderBy('po_date')
            ->get(['id', 'po_number', 'po_date', 'supplier']);

        $poNumbers = $poHeaders->pluck('po_number')->filter()->unique()->values();

        $poReceipts = $poNumbers->isEmpty()
            ? collect()
            : GrReceipt::query()
                ->whereIn('po_no', $poNumbers)
                ->whereBetween('receive_date', [$startString, $endString])
                ->select([
                    'po_no',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('MAX(receive_date) as last_receipt_date'),
                    DB::raw(
                        $driver === 'sqlsrv'
                            ? "COUNT(DISTINCT COALESCE(gr_unique, CONCAT(po_no,'|',line_no,'|', CONVERT(varchar(50), receive_date, 126)))) as document_count"
                            : "COUNT(DISTINCT COALESCE(gr_unique, po_no || '|' || line_no || '|' || receive_date::text)) as document_count"
                    ),
                ])
                ->groupBy('po_no')
                ->get()
                ->keyBy('po_no');

        $lineReceipts = $poNumbers->isEmpty()
            ? collect()
            : GrReceipt::query()
                ->whereIn('po_no', $poNumbers)
                ->whereBetween('receive_date', [$startString, $endString])
                ->select([
                    'po_no',
                    'line_no',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('MAX(receive_date) as last_receipt_date'),
                ])
                ->groupBy('po_no', 'line_no')
                ->get();

        $lineReceiptIndex = [];
        foreach ($lineReceipts as $row) {
            $poKey = (string) $row->po_no;
            $lineKey = (string) ($row->line_no ?? '');
            $lineReceiptIndex[$poKey][$lineKey] = [
                'qty' => (float) $row->total_qty,
                'last' => $row->last_receipt_date,
            ];
        }

        $rows = [];
        $totalOrdered = 0.0;
        $totalReceived = 0.0;
        $totalOutstanding = 0.0;
        $totalDocuments = 0;

        foreach ($poHeaders as $header) {
            $ordered = (float) $header->lines->sum(function (PoLine $line) {
                return (float) ($line->qty_ordered ?? 0);
            });

            $receipt = $poReceipts->get($header->po_number);
            $received = $receipt ? (float) $receipt->total_qty : 0.0;
            $outstanding = max($ordered - $received, 0.0);

            $poDate = $header->po_date ? Carbon::parse($header->po_date) : null;
            $lastReceiptDate = $receipt && $receipt->last_receipt_date
                ? Carbon::parse($receipt->last_receipt_date)
                : null;

            $rows[] = [
                'po_number' => $header->po_number,
                'po_date' => $poDate?->toDateString(),
                'po_date_label' => $poDate?->format('d M Y'),
                'supplier' => $header->supplier,
                'qty_ordered' => $ordered,
                'qty_received' => $received,
                'qty_outstanding' => $outstanding,
                'last_receipt_date' => $lastReceiptDate?->toDateString(),
                'last_receipt_label' => $lastReceiptDate?->format('d M Y'),
                'receipt_documents' => (int) ($receipt->document_count ?? 0),
            ];

            $totalOrdered += $ordered;
            $totalReceived += $received;
            $totalOutstanding += $outstanding;
            $totalDocuments += (int) ($receipt->document_count ?? 0);
        }

        $quotaQuery = Quota::query()
            ->select([
                'quota_number',
                'name',
                'total_allocation',
                'actual_remaining',
                'period_start',
                'period_end',
            ])
            ->orderBy('quota_number')
            ->where(function ($query) use ($startString, $endString) {
                $query->whereNull('period_start')
                    ->orWhereNull('period_end')
                    ->orWhere(function ($sub) use ($startString, $endString) {
                        $sub->whereDate('period_start', '<=', $endString)
                            ->whereDate('period_end', '>=', $startString);
                    });
            });

        $quotas = $quotaQuery->get();

        $quotaCategories = $quotas->pluck('quota_number')->all();
        $quotaAllocations = $quotas->map(fn (Quota $q) => (float) ($q->total_allocation ?? 0))->all();
        $quotaRemaining = $quotas->map(fn (Quota $q) => (float) ($q->actual_remaining ?? 0))->all();

        $quotaTotalAllocation = array_sum($quotaAllocations);
        $quotaTotalRemaining = array_sum($quotaRemaining);

        $monthlyReceipts = $this->buildMonthlyReceipts($startString, $endString);
        $poStatus = $this->buildPoStatus($poHeaders->pluck('id')->all());
        $outstandingLines = $this->buildOutstandingLines($poHeaders, $lineReceiptIndex);

        // Recompute top cards using the SAME logic as AnalyticsController summary:
        // - Forecast = PO (exclude ACC) per quota PK range/period + MOVE delta overlay
        // - Actual   = GR (exclude ACC) matched to those POs per quota PK range/period
        // - Outstanding = Forecast - Actual
        // - GR Documents = distinct (po_no, normalized line_no, receive_date) exclude ACC within the year
        try {
            $summaryYear = (int) $start->year;
            $yearStart = Carbon::create($summaryYear, 1, 1)->startOfDay()->toDateString();
            $yearEnd   = Carbon::create($summaryYear, 12, 31)->endOfDay()->toDateString();

            // For display: total distinct PO count within the year (exclude ACC)
            $poBase = DB::table('po_headers as ph')
                ->join('po_lines as pl', 'pl.po_header_id', '=', 'ph.id')
                ->join('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                ->whereBetween('ph.po_date', [$yearStart, $yearEnd])
                ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");
            $totalPoCount = (int) (clone $poBase)->distinct('ph.po_number')->count('ph.po_number');

            // Build BASE arrays per quota (PO/GR exclude ACC), limited by each quota's PK range and period
            $quotaCards = Quota::query()
                ->where(function ($q) use ($yearStart, $yearEnd) {
                    $q->where(function ($qq) use ($yearStart) {
                        $qq->whereNull('period_end')->orWhere('period_end', '>=', $yearStart);
                    })->where(function ($qq) use ($yearEnd) {
                        $qq->whereNull('period_start')->orWhere('period_start', '<=', $yearEnd);
                    });
                })
                ->orderBy('quota_number')
                ->get(['id','government_category','period_start','period_end','total_allocation']);

            $baseForecast = [];
            $baseActual = [];
            foreach ($quotaCards as $q) {
                $cat = (string) ($q->government_category ?? '');
                $bounds = PkCategoryParser::parse($cat);

                $grn = DB::table('gr_receipts')
                    ->selectRaw("po_no, ".DbExpression::lineNoInt('line_no')." AS ln")
                    ->selectRaw('SUM(qty) as qty')
                    ->groupBy('po_no','ln');

                $baseAll = DB::table('po_lines as pl')
                    ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                    ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->leftJoinSub($grn, 'grn', function($j){
                        $j->on('grn.po_no','=','ph.po_number')
                          ->whereRaw("grn.ln = ".DbExpression::lineNoInt('pl.line_no'));
                    })
                    ->whereNotNull('pl.hs_code_id');

                if ($bounds['min_pk'] !== null) {
                    $baseAll->where('hs.pk_capacity', $bounds['min_incl'] ? '>=' : '>', $bounds['min_pk']);
                }
                if ($bounds['max_pk'] !== null) {
                    $baseAll->where('hs.pk_capacity', $bounds['max_incl'] ? '<=' : '<', $bounds['max_pk']);
                }
                if (!empty($q->period_start) && !empty($q->period_end)) {
                    $baseAll->whereBetween('ph.po_date', [
                        $q->period_start->toDateString(),
                        $q->period_end->toDateString(),
                    ]);
                }

                $baseAcc = (clone $baseAll)->whereRaw("COALESCE(UPPER(hs.hs_code),'') = 'ACC'");

                $forecast_all_po = (float) (clone $baseAll)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                $forecast_acc_po = (float) (clone $baseAcc)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                $actual_all_gr   = (float) (clone $baseAll)->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');
                $actual_acc_gr   = (float) (clone $baseAcc)->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');

                $baseForecast[$q->id] = max($forecast_all_po - $forecast_acc_po, 0.0);
                $baseActual[$q->id]   = max($actual_all_gr - $actual_acc_gr, 0.0);
            }

            // MOVE delta overlay (match AnalyticsController):
            // Use next-year quotas to move qty between years by PK label, excluding ACC
            $forecastFinal = $baseForecast;
            $windowStart = Carbon::create($summaryYear, 1, 1)->startOfDay();
            $windowEnd   = Carbon::create($summaryYear + 1, 12, 31)->endOfDay();
            $nextQuotas = Quota::query()
                ->whereNotNull('period_start')
                ->whereBetween('period_start', [$windowStart->toDateString(), $windowEnd->toDateString()])
                ->get(['id','government_category','period_start']);

            $nextIds = $nextQuotas->pluck('id')->all();
            if (!empty($nextIds)) {
                $moveQtyByNextId = DB::table('purchase_order_quota as pq')
                    ->select('pq.quota_id', DB::raw('SUM(pq.allocated_qty) as qty'))
                    ->whereIn('pq.quota_id', $nextIds)
                    ->whereExists(function($q){
                        $q->select(DB::raw('1'))
                          ->from('purchase_orders as po')
                          ->join('po_headers as ph','po.po_number','=','ph.po_number')
                          ->join('po_lines as pl','pl.po_header_id','=','ph.id')
                          ->join('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
                          ->whereRaw('pq.purchase_order_id = po.id')
                          ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");
                    })
                    ->groupBy('pq.quota_id')
                    ->pluck('qty','pq.quota_id');

                foreach ($nextQuotas as $next) {
                    $catKey = strtolower(trim((string) ($next->government_category ?? '')));
                    $nextYear = Carbon::parse($next->period_start)->year;
                    $move = (float) ($moveQtyByNextId[$next->id] ?? 0.0);

                    if ($nextYear === $summaryYear) {
                        $curr = $quotaCards->first(function($q) use ($catKey) {
                            if (empty($q->period_start)) { return false; }
                            return strtolower(trim((string) ($q->government_category ?? ''))) === $catKey;
                        });
                        if ($curr) {
                            $forecastFinal[$curr->id] = max((float) ($baseForecast[$curr->id] ?? 0.0) + $move, 0.0);
                        }
                    }

                    if ($nextYear - 1 === $summaryYear) {
                        $prev = $quotaCards->first(function($q) use ($catKey) {
                            if (empty($q->period_start)) { return false; }
                            return strtolower(trim((string) ($q->government_category ?? ''))) === $catKey;
                        });
                        if ($prev) {
                            $forecastFinal[$prev->id] = max((float) ($baseForecast[$prev->id] ?? 0.0) - $move, 0.0);
                        }
                    }
                }
            }

            $totalForecast = array_sum($forecastFinal);
            $totalActual   = array_sum($baseActual);

            // GR (documents) within selected year, exclude ACC using PO line mapping
            $grBase = DB::table('gr_receipts as gr')
                ->join('po_headers as ph', 'ph.po_number', '=', 'gr.po_no')
                ->join('po_lines as pl', function($j){
                    $j->on('pl.po_header_id', '=', 'ph.id')
                      ->whereRaw(DbExpression::lineNoTrimmed('pl.line_no').' = '.DbExpression::lineNoTrimmed('gr.line_no'));
                })
                ->join('hs_code_pk_mappings as hs','pl.hs_code_id','=','hs.id')
                ->whereBetween('gr.receive_date', [$yearStart, $yearEnd])
                ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");
            $grDocs = (int) (clone $grBase)
                ->selectRaw(
                    $driver === 'sqlsrv'
                        ? "COUNT(DISTINCT CONCAT(gr.po_no,'|',".DbExpression::lineNoTrimmed('gr.line_no').",'|', CONVERT(varchar(50), gr.receive_date, 126))) as c"
                        : "COUNT(DISTINCT (gr.po_no || '|' || regexp_replace(gr.line_no::text, '^0+', '') || '|' || gr.receive_date::text)) as c"
                )
                ->value('c');

            // Allocation/Remaining displayed in the summary
            $quotaTotalAllocationYear = (float) Quota::query()
                ->whereDate('period_start', '>=', $yearStart)
                ->whereDate('period_end',   '<=', $yearEnd)
                ->sum('total_allocation');
            $quotaActualRemainingYear = max($quotaTotalAllocationYear - $totalActual, 0.0);

            $summary = [
                'po_total' => $totalPoCount,
                // Use Analytics summary logic for these totals
                'po_ordered_total' => $totalForecast, // Forecast
                'po_outstanding_total' => max($totalForecast - $totalActual, 0.0), // Outstanding
                'gr_total_qty' => $totalActual, // Actual
                'gr_document_total' => $grDocs,
                'quota_total_allocation' => $quotaTotalAllocationYear,
                'quota_total_remaining' => $quotaActualRemainingYear,
            ];
        } catch (\Throwable $e) {
            // Fallback to previous aggregate if anything fails
            $summary = [
                'po_total' => $poHeaders->count(),
                'po_ordered_total' => $totalOrdered,
                'po_outstanding_total' => $totalOutstanding,
                'gr_total_qty' => $totalReceived,
                'gr_document_total' => $totalDocuments,
                'quota_total_allocation' => $quotaTotalAllocation,
                'quota_total_remaining' => $quotaTotalRemaining,
            ];
        }

        return [
            'filters' => [
                'start_date' => $startString,
                'end_date' => $endString,
                'year' => (int) $start->year,
            ],
            'summary' => $summary,
            'rows' => $rows,
            'charts' => [
                'quota_bar' => [
                    'categories' => $quotaCategories,
                    'series' => [
                        [
                            'name' => 'Total Allocation',
                            'data' => $quotaAllocations,
                        ],
                        [
                            'name' => 'Actual Remaining',
                            'data' => $quotaRemaining,
                        ],
                    ],
                ],
                'gr_trend' => [
                    'categories' => $monthlyReceipts['categories'],
                    'series' => [
                        [
                            'name' => 'GR Qty',
                            'data' => $monthlyReceipts['series'],
                        ],
                    ],
                ],
                'po_status' => [
                    'labels' => array_keys($poStatus),
                    'series' => array_values($poStatus),
                ],
                'receipt_donut' => [
                    'labels' => ['Received', 'Outstanding'],
                    'series' => [
                        round((float) ($summary['gr_total_qty'] ?? 0), 2),
                        round((float) ($summary['po_outstanding_total'] ?? 0), 2),
                    ],
                ],
            ],
            'outstanding' => $outstandingLines,
        ];
    }

    /**
     * @param  array<int,int>  $headerIds
     * @return array<string,int>
     */
    private function buildPoStatus(array $headerIds): array
    {
        if (empty($headerIds)) {
            return [];
        }

        return PoLine::query()
            ->whereIn('po_header_id', $headerIds)
            ->selectRaw("COALESCE(NULLIF(sap_order_status, ''), 'Unknown') as status")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw("COALESCE(NULLIF(sap_order_status, ''), 'Unknown')")
            ->orderByRaw("COALESCE(NULLIF(sap_order_status, ''), 'Unknown')")
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->toArray();
    }

    /**
     * @return array{categories: array<int,string>, series: array<int,float>}
     */
    private function buildMonthlyReceipts(string $startDate, string $endDate): array
    {
        $receipts = GrReceipt::query()
            ->whereBetween('receive_date', [$startDate, $endDate])
            ->selectRaw(DbExpression::monthBucket('receive_date').' as period')
            ->selectRaw('SUM(qty) as total')
            ->groupByRaw(DbExpression::monthBucket('receive_date'))
            ->orderByRaw(DbExpression::monthBucket('receive_date'))
            ->get();

        $categories = [];
        $series = [];

        foreach ($receipts as $row) {
            $date = Carbon::parse($row->period);
            $categories[] = $date->format('M Y');
            $series[] = (float) $row->total;
        }

        return [
            'categories' => $categories,
            'series' => $series,
        ];
    }

    /**
     * @param  Collection<int,PoHeader>  $poHeaders
     * @param  array<string,array<string,array{qty:float,last:?string}>>  $lineReceipts
     * @return array<int,array<string,mixed>>
     */
    private function buildOutstandingLines(Collection $poHeaders, array $lineReceipts): array
    {
        $items = [];

        foreach ($poHeaders as $header) {
            $poNumber = (string) $header->po_number;

            foreach ($header->lines as $line) {
                $lineKey = (string) ($line->line_no ?? '');
                $receipt = $lineReceipts[$poNumber][$lineKey] ?? null;

                $ordered = (float) ($line->qty_ordered ?? 0);
                $received = $receipt ? (float) ($receipt['qty'] ?? 0) : 0.0;
                $outstanding = max($ordered - $received, 0.0);

                if ($outstanding <= 0) {
                    continue;
                }

                $lastReceipt = null;
                if (!empty($receipt['last'])) {
                    $lastReceiptCarbon = Carbon::parse($receipt['last']);
                    $lastReceipt = $lastReceiptCarbon->format('d M Y');
                }

                $items[] = [
                    'po_number' => $poNumber,
                    'line_no' => $line->line_no ?? '-',
                    'model_code' => $line->model_code,
                    'item_desc' => $line->item_desc,
                    'qty_ordered' => $ordered,
                    'qty_received' => $received,
                    'outstanding' => $outstanding,
                    'eta_date' => $line->eta_date ? Carbon::parse($line->eta_date)->format('d M Y') : null,
                    'sap_order_status' => $line->sap_order_status,
                    'last_receipt_date' => $lastReceipt,
                ];
            }
        }

        usort($items, function (array $a, array $b) {
            return $b['outstanding'] <=> $a['outstanding'];
        });

        return array_slice($items, 0, 10);
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(Request $request): array
    {
        // Prefer year-based range if provided
        $year = $request->query('year');
        if (!empty($year) && ctype_digit((string)$year)) {
            $y = (int) $year;
            $start = Carbon::create($y, 1, 1)->startOfDay();
            $end = Carbon::create($y, 12, 31)->endOfDay();
            return [$start, $end];
        }

        // Backward compatibility: accept explicit date range
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfYear();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy(), $start];
        }

        return [$start, $end];
    }
}
