<?php

namespace App\Http\Controllers;

use App\Models\Quota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $startDate = $start ? Carbon::parse($start)->toDateString() : now()->startOfMonth()->toDateString();
        $endDate = $end ? Carbon::parse($end)->toDateString() : now()->toDateString();

        return view('analytics.index', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function data(Request $request)
    {
        $dataset = $this->buildDataset($request);
        return response()->json($dataset);
    }

    public function exportCsv(Request $request)
    {
        $dataset = $this->buildDataset($request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="analytics_actual.csv"',
        ];

        $columns = ['Nomor Kuota', 'Range PK', 'Kuota Awal', 'Forecast (opsional)', 'Actual (Good Receipt)', 'Pemakaian Actual %'];
        $rows = collect($dataset['table'] ?? [])->map(function ($row) {
            return [
                $row['quota_number'] ?? '',
                $row['range_pk'] ?? '',
                $row['initial_quota'] ?? 0,
                $row['forecast'] ?? 0,
                $row['actual'] ?? 0,
                $row['actual_pct'] ?? 0,
            ];
        })->toArray();

        $callback = function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $r) { fputcsv($out, $r); }
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportXlsx(Request $request)
    {
        $dataset = $this->buildDataset($request);

        if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return response('\n[Missing dependency] Install Laravel-Excel first: composer require maatwebsite/excel\n', 501);
        }

        $rows = collect($dataset['table'] ?? [])->map(function ($row) {
            return [
                'Nomor Kuota' => $row['quota_number'] ?? '',
                'Range PK' => $row['range_pk'] ?? '',
                'Kuota Awal' => $row['initial_quota'] ?? 0,
                'Forecast (opsional)' => $row['forecast'] ?? 0,
                'Actual (Good Receipt)' => $row['actual'] ?? 0,
                'Pemakaian Actual %' => $row['actual_pct'] ?? 0,
            ];
        })->toArray();

        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithTitle {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function array(): array { return $this->rows; }
            public function title(): string { return 'Analytics Actual'; }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'analytics_actual.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $dataset = $this->buildDataset($request);

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response("\n[Missing dependency] Install dompdf first: composer require barryvdh/laravel-dompdf\n", 501);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('analytics.pdf', [
            'filters' => $dataset['filters'] ?? [],
            'summary' => $dataset['summary'] ?? [],
            'rows' => $dataset['table'] ?? [],
        ]);

        return $pdf->download('analytics_actual.pdf');
    }

    private function buildDataset(Request $request): array
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // Build aggregated rows per quota using actual receipts only
        $rows = Quota::query()
            ->select([
                'quotas.id',
                'quotas.quota_number',
                'quotas.government_category',
                'quotas.total_allocation',
                'quotas.forecast_remaining',
            ])
            ->selectRaw('COALESCE(SUM(shipment_receipts.quantity_received),0) as actual_received')
            ->leftJoin('purchase_orders', 'purchase_orders.quota_id', '=', 'quotas.id')
            ->leftJoin('shipments', 'shipments.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('shipment_receipts', function ($join) use ($start, $end) {
                $join->on('shipment_receipts.shipment_id', '=', 'shipments.id')
                    ->whereDate('shipment_receipts.receipt_date', '>=', $start->toDateString())
                    ->whereDate('shipment_receipts.receipt_date', '<=', $end->toDateString());
            })
            ->groupBy('quotas.id', 'quotas.quota_number', 'quotas.government_category', 'quotas.total_allocation', 'quotas.forecast_remaining')
            ->orderBy('quotas.quota_number')
            ->get()
            ->map(function ($q) {
                $allocation = (int) ($q->total_allocation ?? 0);
                $actual = (int) ($q->actual_received ?? 0);
                $pct = $allocation > 0 ? round(($actual / $allocation) * 100, 2) : 0;
                return [
                    'quota_id' => $q->id,
                    'quota_number' => $q->quota_number,
                    'range_pk' => $q->government_category,
                    'initial_quota' => $allocation,
                    'forecast' => $q->forecast_remaining,
                    'actual' => $actual,
                    'actual_pct' => $pct,
                ];
            });

        $categories = $rows->pluck('quota_number')->all();
        $seriesQuota = $rows->pluck('initial_quota')->all();
        $seriesActual = $rows->pluck('actual')->all();

        $totalAllocation = array_sum($seriesQuota);
        $totalActual = array_sum($seriesActual);
        $totalRemaining = max(0, $totalAllocation - $totalActual);

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'bar' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Kuota', 'data' => $seriesQuota],
                    ['name' => 'Actual', 'data' => $seriesActual],
                ],
            ],
            'donut' => [
                'labels' => ['Actual', 'Remaining'],
                'series' => [$totalActual, $totalRemaining],
            ],
            'table' => $rows->values()->all(),
            'summary' => [
                'total_allocation' => $totalAllocation,
                'total_actual' => $totalActual,
                'total_remaining' => $totalRemaining,
            ],
        ];
    }
}

