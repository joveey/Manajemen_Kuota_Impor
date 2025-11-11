<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrReceipt;
use App\Models\Product;
use App\Models\PoHeader;
use App\Models\PoLine;
use App\Models\Quota;
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

    public function exportCsv(Request $request)
    {
        // Build an enriched per-line export covering the requested columns.
        // We keep the logic fully read-only and re-use existing tables only.
        [$start, $end] = $this->resolveRange($request);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        // Default to ETA basis so export matches common PO LISTED templates
        $basis = strtolower((string) $request->query('basis', 'eta'));
        $basisField = $basis === 'eta' ? 'po_lines.eta_date' : 'ph.po_date';

        // Preload quotas overlapping the chosen range to evaluate status/quota number per line
        $quotaPool = Quota::query()
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereNull('period_start')
                    ->orWhereNull('period_end')
                    ->orWhere(function ($qq) use ($startDate, $endDate) {
                        $qq->where('period_start', '<=', $endDate)
                           ->where('period_end', '>=', $startDate);
                    });
            })
            ->orderBy('quota_number')
            ->get();

        // Fetch PO lines within the header/date range and enrich with HS + voyage fields
        $records = PoLine::query()
            ->join('po_headers as ph', 'po_lines.po_header_id', '=', 'ph.id')
            ->leftJoin('hs_code_pk_mappings as hs', 'po_lines.hs_code_id', '=', 'hs.id')
            ->whereBetween(DB::raw($basisField), [$startDate, $endDate])
            ->orderBy('ph.po_number')
            ->orderBy('po_lines.line_no')
            ->get([
                'po_lines.id as line_id',
                'ph.po_number',
                'ph.po_date',
                'ph.supplier',
                'ph.note as header_text',
                'po_lines.line_no',
                'po_lines.model_code as material',
                'po_lines.item_desc',
                'po_lines.qty_ordered',
                'po_lines.qty_to_invoice',
                'po_lines.qty_to_deliver',
                'po_lines.eta_date',
                'po_lines.warehouse_code',
                'po_lines.warehouse_name',
                'po_lines.storage_location',
                'po_lines.sap_order_status',
                'po_lines.voyage_bl',
                'po_lines.voyage_etd',
                'po_lines.voyage_eta',
                'po_lines.voyage_factory',
                'po_lines.voyage_status',
                'po_lines.voyage_issue_date',
                'po_lines.voyage_expired_date',
                'po_lines.voyage_remark',
                'hs.hs_code',
                'hs.pk_capacity',
            ]);

        // Build line-level GR index within the selected range (by PO + line no)
        // No GR in export per new request; skip computing GR aggregates

        // Prefetch voyage splits for all included lines
        $lineIds = $records->pluck('line_id')->filter()->unique()->values();
        $splitsByLine = [];
        if ($lineIds->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('po_line_voyage_splits')) {
            $splitRows = DB::table('po_line_voyage_splits')
                ->whereIn('po_line_id', $lineIds)
                ->orderBy('po_line_id')->orderBy('seq_no')->orderBy('id')
                ->get();
            foreach ($splitRows as $sr) { $splitsByLine[$sr->po_line_id][] = $sr; }
        }

        // Prepare export rows
        $rows = [];
        foreach ($records as $r) {
            // Determine quota match for HS/PK within the period of the PO document date
            $quotaNo = '';
            $quotaStatus = '';
            $quotaIssue = '';
            $quotaExpired = '';
            if (!empty($quotaPool)) {
                $pseudo = new Product();
                $pseudo->hs_code = $r->hs_code;
                $pseudo->pk_capacity = $r->pk_capacity;
                $q = $quotaPool->first(function (Quota $q) use ($pseudo, $r) {
                    // Period guard per document date
                    $doc = $r->po_date ? Carbon::parse($r->po_date)->toDateString() : null;
                    if ($doc) {
                        if ($q->period_start && $doc < $q->period_start->toDateString()) { return false; }
                        if ($q->period_end && $doc > $q->period_end->toDateString()) { return false; }
                    }
                    return $q->matchesProduct($pseudo);
                });
                if ($q) {
                    $quotaNo = (string) ($q->quota_number ?? '');
                    // For export, display a timeline-oriented label instead of inventory status
                    $quotaStatus = 'Current';
                    $quotaIssue = $q->period_start ? Carbon::parse($q->period_start)->toDateString() : '';
                    $quotaExpired = $q->period_end ? Carbon::parse($q->period_end)->toDateString() : '';
                }
            }

            // Computations with safe fallbacks
            $ordered = (float) ($r->qty_ordered ?? 0);
            $toInvoice = isset($r->qty_to_invoice) ? (float) $r->qty_to_invoice : 0.0;
            $toDeliverLine = isset($r->qty_to_deliver) ? (float) $r->qty_to_deliver : null;

            $lineSplits = $splitsByLine[$r->line_id] ?? null;
            if (!empty($lineSplits)) {
                // Add base line first
                $rows[] = [
                    'Month' => ($basis === 'eta')
                        ? ($r->eta_date ? Carbon::parse($r->eta_date)->format('M') : '')
                        : ($r->po_date ? Carbon::parse($r->po_date)->format('M') : ''),
                    'Purchasing Doc. Type' => '',
                    'Vendor/supplying plant' => (string) ($r->supplier ?? ''),
                    'Purchasing Document' => (string) ($r->po_number ?? ''),
                    'Material' => (string) ($r->material ?? ''),
                    'Plant' => (string) ($r->warehouse_code ?? $r->warehouse_name ?? ''),
                    'Storage Location' => (string) ($r->storage_location ?? ''),
                    'Order Quantity' => $ordered,
                    'Still to be invoiced (qty)' => $toInvoice,
                    'Still to be delivered (qty)' => $toDeliverLine ?? '',
                    'Delivery Date' => $r->eta_date ? Carbon::parse($r->eta_date)->toDateString() : '',
                    'Document Date' => $r->po_date ? Carbon::parse($r->po_date)->toDateString() : '',
                    'header text' => (string) ($r->header_text ?? ''),
                    'BL' => (string) ($r->voyage_bl ?? ''),
                    'ETD' => $r->voyage_etd ? Carbon::parse($r->voyage_etd)->toDateString() : '',
                    'ETA' => $r->voyage_eta ? Carbon::parse($r->voyage_eta)->toDateString() : '',
                    'Factory' => (string) ($r->voyage_factory ?? ''),
                    'Hs Code' => (string) ($r->hs_code ?? ''),
                    'Status' => (string) (($r->sap_order_status ?? '') !== '' ? $r->sap_order_status : ($r->voyage_status ?? '')),
                    'Status Quota' => $quotaStatus,
                    'Quota No.' => $quotaNo,
                    'Issue Date' => $quotaIssue,
                    'Expired' => $quotaExpired,
                    'Remark' => (string) ($r->voyage_remark ?? ''),
                    '_sort_po' => (string) ($r->po_number ?? ''),
                    '_sort_line' => (int) ($r->line_no ?? 0),
                    '_sort_seq' => 0,
                ];

                $seq = 1;
                foreach ($lineSplits as $sp) {
                    $sq = (float) ($sp->qty ?? 0);
                    $rows[] = [
                        'Month' => ($basis === 'eta')
                            ? (($sp->voyage_eta ?? $r->eta_date) ? Carbon::parse($sp->voyage_eta ?? $r->eta_date)->format('M') : '')
                            : ($r->po_date ? Carbon::parse($r->po_date)->format('M') : ''),
                        'Purchasing Doc. Type' => '',
                        'Vendor/supplying plant' => (string) ($r->supplier ?? ''),
                        'Purchasing Document' => (string) ($r->po_number ?? ''),
                        'Material' => (string) ($r->material ?? ''),
                        'Plant' => (string) ($r->warehouse_code ?? $r->warehouse_name ?? ''),
                        'Storage Location' => (string) ($r->storage_location ?? ''),
                        'Order Quantity' => $sq,
                        'Still to be invoiced (qty)' => '',
                        'Still to be delivered (qty)' => '',
                        'Delivery Date' => ($sp->voyage_eta ?? $r->eta_date) ? Carbon::parse($sp->voyage_eta ?? $r->eta_date)->toDateString() : '',
                        'Document Date' => $r->po_date ? Carbon::parse($r->po_date)->toDateString() : '',
                        'header text' => (string) ($r->header_text ?? ''),
                        'BL' => (string) ($sp->voyage_bl ?? $r->voyage_bl ?? ''),
                        'ETD' => ($sp->voyage_etd ?? $r->voyage_etd) ? Carbon::parse($sp->voyage_etd ?? $r->voyage_etd)->toDateString() : '',
                        'ETA' => ($sp->voyage_eta ?? $r->voyage_eta) ? Carbon::parse($sp->voyage_eta ?? $r->voyage_eta)->toDateString() : '',
                        'Factory' => (string) ($sp->voyage_factory ?? $r->voyage_factory ?? ''),
                        'Hs Code' => (string) ($r->hs_code ?? ''),
                        'Status' => (string) (($r->sap_order_status ?? '') !== '' ? $r->sap_order_status : (($sp->voyage_status ?? $r->voyage_status) ?? '')),
                        'Status Quota' => $quotaStatus,
                        'Quota No.' => $quotaNo,
                        'Issue Date' => $quotaIssue,
                        'Expired' => $quotaExpired,
                        'Remark' => (string) (($sp->voyage_remark ?? '') !== '' ? $sp->voyage_remark : ($r->voyage_remark ?? '')),
                        '_sort_po' => (string) ($r->po_number ?? ''),
                        '_sort_line' => (int) ($r->line_no ?? 0),
                        '_sort_seq' => $seq++,
                    ];
                }
            } else {
                $toDeliver = $toDeliverLine ?? '';
                $rows[] = [
                    'Month' => ($basis === 'eta')
                        ? ($r->eta_date ? Carbon::parse($r->eta_date)->format('M') : '')
                        : ($r->po_date ? Carbon::parse($r->po_date)->format('M') : ''),
                    'Purchasing Doc. Type' => '',
                    'Vendor/supplying plant' => (string) ($r->supplier ?? ''),
                    'Purchasing Document' => (string) ($r->po_number ?? ''),
                    'Material' => (string) ($r->material ?? ''),
                    'Plant' => (string) ($r->warehouse_code ?? $r->warehouse_name ?? ''),
                    'Storage Location' => (string) ($r->storage_location ?? ''),
                    'Order Quantity' => $ordered,
                    'Still to be invoiced (qty)' => $toInvoice,
                    'Still to be delivered (qty)' => $toDeliver,
                    'Delivery Date' => $r->eta_date ? Carbon::parse($r->eta_date)->toDateString() : '',
                    'Document Date' => $r->po_date ? Carbon::parse($r->po_date)->toDateString() : '',
                    'header text' => (string) ($r->header_text ?? ''),
                    'BL' => (string) ($r->voyage_bl ?? ''),
                    'ETD' => $r->voyage_etd ? Carbon::parse($r->voyage_etd)->toDateString() : '',
                    'ETA' => $r->voyage_eta ? Carbon::parse($r->voyage_eta)->toDateString() : '',
                    'Factory' => (string) ($r->voyage_factory ?? ''),
                    'Hs Code' => (string) ($r->hs_code ?? ''),
                    'Status' => (string) (($r->sap_order_status ?? '') !== '' ? $r->sap_order_status : ($r->voyage_status ?? '')),
                    'Status Quota' => $quotaStatus,
                    'Quota No.' => $quotaNo,
                    'Issue Date' => $quotaIssue,
                    'Expired' => $quotaExpired,
                    'Remark' => (string) ($r->voyage_remark ?? ''),
                    '_sort_po' => (string) ($r->po_number ?? ''),
                    '_sort_line' => (int) ($r->line_no ?? 0),
                    '_sort_seq' => 0,
                ];
            }
        }

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
                    DB::raw("COUNT(DISTINCT COALESCE(gr_unique, po_no || '|' || line_no || '|' || receive_date::text)) as document_count"),
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

        $summary = [
            'po_total' => $poHeaders->count(),
            'po_ordered_total' => $totalOrdered,
            'po_outstanding_total' => $totalOutstanding,
            'gr_total_qty' => $totalReceived,
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
                    'series' => array_values($poStatus),
                ],
                'receipt_donut' => [
                    'labels' => ['Received', 'Outstanding'],
                    'series' => [
                        round($totalReceived, 2),
                        round(max($totalOrdered - $totalReceived, 0.0), 2),
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
            ->selectRaw("DATE_TRUNC('month', receive_date) as period")
            ->selectRaw('SUM(qty) as total')
            ->groupBy('period')
            ->orderBy('period')
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
