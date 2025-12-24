<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrReceipt;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Support\DbExpression;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $hasGrUnique = Schema::hasColumn('gr_receipts', 'gr_unique');
        $lineNoExpr = DbExpression::lineNoTrimmed('line_no');
        $grDocExpr = $driver === 'sqlsrv'
            ? (
                $hasGrUnique
                    ? "COUNT(DISTINCT COALESCE(gr_unique, CONCAT(po_no,'|',{$lineNoExpr},'|', CONVERT(varchar(50), receive_date, 126))))"
                    : "COUNT(DISTINCT CONCAT(po_no,'|',{$lineNoExpr},'|', CONVERT(varchar(50), receive_date, 126)))"
            )
            : (
                $hasGrUnique
                    ? "COUNT(DISTINCT COALESCE(gr_unique, po_no || '|' || {$lineNoExpr} || '|' || receive_date::text))"
                    : "COUNT(DISTINCT (po_no || '|' || {$lineNoExpr} || '|' || receive_date::text))"
            );

        $purchaseOrders = PurchaseOrder::query()
            ->whereNotNull('po_doc')
            ->whereBetween('created_date', [$startString, $endString])
            ->orderBy('po_doc')
            ->orderBy('line_no')
            ->get();

        $poGroups = $purchaseOrders
            ->groupBy(fn (PurchaseOrder $po) => (string) $po->po_doc)
            ->filter(fn ($group, $doc) => $doc !== '');
        $poNumbers = $poGroups->keys()->values();

        $poReceipts = $poNumbers->isEmpty()
            ? collect()
            : GrReceipt::query()
                ->whereIn('po_no', $poNumbers)
                ->whereBetween('receive_date', [$startString, $endString])
                ->select([
                    'po_no',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('MAX(receive_date) as last_receipt_date'),
                    DB::raw($grDocExpr.' as document_count'),
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
        $totalDocuments = 0;
        $totalReceivedQty = 0.0;
        $totalOutstandingQty = 0.0;

        foreach ($poGroups as $poNumber => $lines) {
            $ordered = (float) $lines->sum(fn (PurchaseOrder $line) => (float) ($line->qty ?? 0));
            $receivedFromLines = (float) $lines->sum(fn (PurchaseOrder $line) => (float) ($line->quantity_received ?? 0));
            $receipt = $poReceipts->get($poNumber);
            $received = $receipt ? (float) $receipt->total_qty : $receivedFromLines;
            $outstanding = max($ordered - $received, 0.0);

            $poDateValue = $lines->min('created_date');
            $poDate = $poDateValue ? Carbon::parse($poDateValue) : null;
            $lastReceiptDate = $receipt && $receipt->last_receipt_date
                ? Carbon::parse($receipt->last_receipt_date)
                : null;

            $rows[] = [
                'po_number' => $poNumber,
                'po_date' => $poDate?->toDateString(),
                'po_date_label' => $poDate?->format('d M Y'),
                'supplier' => $lines->max('vendor_name'),
                'vendor_number' => $lines->max('vendor_no'),
                'qty_ordered' => $ordered,
                'qty_received' => $received,
                'qty_outstanding' => $outstanding,
                'last_receipt_date' => $lastReceiptDate?->toDateString(),
                'last_receipt_label' => $lastReceiptDate?->format('d M Y'),
                'receipt_documents' => (int) ($receipt->document_count ?? 0),
            ];

            $totalDocuments += (int) ($receipt->document_count ?? 0);
            $totalReceivedQty += $received;
            $totalOutstandingQty += $outstanding;
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
        $quotaCategories = [];
        $quotaAllocations = [];
        $quotaRemaining = [];

        foreach ($quotas as $quota) {
            $quotaCategories[] = (string) ($quota->quota_number ?? '');
            $quotaAllocations[] = (float) ($quota->total_allocation ?? 0);
            $quotaRemaining[] = (float) ($quota->actual_remaining ?? 0);
        }

        $quotaTotalAllocation = array_sum($quotaAllocations);
        $quotaTotalRemaining = array_sum($quotaRemaining);

        $monthlyReceipts = $this->buildMonthlyReceipts($startString, $endString);
        $poStatus = $this->buildPoStatus($poGroups);
        $outstandingLines = $this->buildOutstandingLines($purchaseOrders, $lineReceiptIndex);

        $summary = [
            'po_total' => $poGroups->count(),
            'po_ordered_total' => $poStatus['Ordered'] ?? 0,
            'po_outstanding_total' => $poStatus['In Transit'] ?? 0,
            'gr_total_qty' => $totalReceivedQty,
            'gr_document_total' => $totalDocuments,
            'quota_total_allocation' => $quotaTotalAllocation,
            'quota_total_remaining' => $quotaTotalRemaining,
        ];

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
                    'series' => array_map('intval', array_values($poStatus)),
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
     * @param  Collection<string,\Illuminate\Support\Collection<int,PurchaseOrder>>  $poGroups
     * @return array<string,int>
     */
    private function buildPoStatus(Collection $poGroups): array
    {
        if ($poGroups->isEmpty()) {
            return [];
        }

        $counts = [
            'Ordered' => 0,
            'In Transit' => 0,
            'Completed' => 0,
        ];

        foreach ($poGroups as $lines) {
            $ordered = max((float) $lines->sum(fn (PurchaseOrder $line) => (float) ($line->qty ?? 0)), 0.0);
            $received = max((float) $lines->sum(fn (PurchaseOrder $line) => (float) ($line->quantity_received ?? 0)), 0.0);

            if ($received <= 0 || $ordered <= 0) {
                $counts['Ordered']++;
            } elseif ($received >= $ordered) {
                $counts['Completed']++;
            } else {
                $counts['In Transit']++;
            }
        }

        return array_filter($counts, fn ($value) => $value > 0);
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
     * @param  Collection<int,PurchaseOrder>  $purchaseOrders
     * @param  array<string,array<string,array{qty:float,last:?string}>>  $lineReceipts
     * @return array<int,array<string,mixed>>
     */
    private function buildOutstandingLines(Collection $purchaseOrders, array $lineReceipts): array
    {
        $items = [];

        foreach ($purchaseOrders as $line) {
            $poNumber = (string) $line->po_doc;
            if ($poNumber === '') {
                continue;
            }

            $lineKey = (string) ($line->line_no ?? '');
            $receipt = $lineReceipts[$poNumber][$lineKey] ?? null;

            $ordered = (float) ($line->qty ?? 0);
            $received = $receipt
                ? (float) ($receipt['qty'] ?? 0)
                : (float) ($line->quantity_received ?? 0);
            $outstanding = max($ordered - $received, 0.0);

            if ($outstanding <= 0) {
                continue;
            }

            $lastReceipt = null;
            if (!empty($receipt['last'])) {
                $lastReceipt = Carbon::parse($receipt['last'])->format('d M Y');
            }

            $items[] = [
                'po_number' => $poNumber,
                'line_no' => $line->line_no ?? '-',
                'model_code' => $line->item_code,
                'item_desc' => $line->item_desc,
                'qty_ordered' => $ordered,
                'qty_received' => $received,
                'outstanding' => $outstanding,
                'eta_date' => $line->voyage_eta ? Carbon::parse($line->voyage_eta)->format('d M Y') : null,
                'sap_order_status' => $line->sap_order_status,
                'last_receipt_date' => $lastReceipt,
            ];
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
