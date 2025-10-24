<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrReceipt;
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
        $dataset = $this->buildDataset($request);
        $rows = $dataset['rows'];

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="final_report.csv"',
        ];

        $columns = [
            'PO Number',
            'PO Date',
            'Supplier',
            'Qty Ordered',
            'Qty Received',
            'Outstanding',
            'Last Receipt',
            'GR Documents',
        ];

        $callback = function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['po_number'],
                    $row['po_date'],
                    $row['supplier'],
                    $row['qty_ordered'],
                    $row['qty_received'],
                    $row['qty_outstanding'],
                    $row['last_receipt_date'] ?? '',
                    $row['receipt_documents'],
                ]);
            }
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Build dataset for the final report page & export.
     *
     * @return array{
     *     filters: array{start_date:string,end_date:string},
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

