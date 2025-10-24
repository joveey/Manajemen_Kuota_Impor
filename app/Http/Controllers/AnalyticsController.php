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
        $mode = $this->resolveMode($request->query('mode'));

        $startDate = $start ? Carbon::parse($start)->toDateString() : now()->startOfMonth()->toDateString();
        $endDate = $end ? Carbon::parse($end)->toDateString() : now()->toDateString();

        return view('analytics.index', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'mode' => $mode,
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

        $mode = $dataset['mode'] ?? 'actual';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="analytics_'.$mode.'.csv"',
        ];

        $labels = $dataset['labels'] ?? [];
        $tableRows = $dataset['table']['rows'] ?? ($dataset['table'] ?? []);
        $primaryLabel = $labels['primary'] ?? 'Nilai';
        $secondaryLabel = $labels['secondary'] ?? 'Sisa';
        $percentageLabel = $labels['percentage'] ?? 'Persentase';

        $columns = ['Nomor Kuota', 'Range PK', 'Kuota Awal', $primaryLabel, $secondaryLabel, $percentageLabel];
        $rows = collect($tableRows)->map(function ($row) {
            return [
                $row['quota_number'] ?? '',
                $row['range_pk'] ?? '',
                $row['initial_quota'] ?? 0,
                $row['primary_value'] ?? 0,
                $row['secondary_value'] ?? 0,
                $row['percentage'] ?? 0,
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

        $labels = $dataset['labels'] ?? [];
        $tableRows = $dataset['table']['rows'] ?? ($dataset['table'] ?? []);

        if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return response('\n[Missing dependency] Install Laravel-Excel first: composer require maatwebsite/excel\n', 501);
        }

        $rows = collect($tableRows)->map(function ($row) use ($labels) {
            return [
                'Nomor Kuota' => $row['quota_number'] ?? '',
                'Range PK' => $row['range_pk'] ?? '',
                'Kuota Awal' => $row['initial_quota'] ?? 0,
                ($labels['primary'] ?? 'Nilai') => $row['primary_value'] ?? 0,
                ($labels['secondary'] ?? 'Sisa') => $row['secondary_value'] ?? 0,
                ($labels['percentage'] ?? 'Persentase') => $row['percentage'] ?? 0,
            ];
        })->toArray();

        $sheetTitle = ($dataset['mode'] ?? 'actual') === 'forecast'
            ? 'Analytics Forecast'
            : 'Analytics Actual';

        $export = new class($rows, $sheetTitle) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithTitle {
            private array $rows;
            private string $title;
            public function __construct(array $rows, string $title)
            {
                $this->rows = $rows;
                $this->title = $title;
            }
            public function array(): array { return $this->rows; }
            public function title(): string { return $this->title; }
        };

        $mode = $dataset['mode'] ?? 'actual';

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'analytics_'.$mode.'.xlsx');
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
            'labels' => $dataset['labels'] ?? [],
            'rows' => $dataset['table']['rows'] ?? ($dataset['table'] ?? []),
        ]);

        $mode = $dataset['mode'] ?? 'actual';

        return $pdf->download('analytics_'.$mode.'.pdf');
    }

    private function buildDataset(Request $request): array
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $mode = $this->resolveMode($request->query('mode'));

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        if ($mode === 'forecast') {
            $rows = $this->buildForecastRows($start, $end);
            $primaryLabel = 'Forecast (Purchase Orders)';
            $secondaryLabel = 'Sisa Forecast';
            $percentageLabel = 'Penggunaan Forecast %';
        } else {
            $rows = $this->buildActualRows($start, $end);
            $primaryLabel = 'Actual (Good Receipt)';
            $secondaryLabel = 'Sisa Kuota';
            $percentageLabel = 'Pemakaian Actual %';
        }

        $categories = $rows->pluck('quota_number')->all();
        $seriesQuota = $rows->pluck('initial_quota')->all();
        $seriesPrimary = $rows->pluck('primary_value')->all();
        $totalAllocation = array_sum($seriesQuota);
        $totalUsage = array_sum($seriesPrimary);
        $totalRemaining = $rows->pluck('secondary_value')->sum();
        if ($totalRemaining === 0 && $totalAllocation > 0) {
            $calculatedRemaining = $totalAllocation - $totalUsage;
            $totalRemaining = $calculatedRemaining > 0 ? $calculatedRemaining : 0;
        }

        return [
            'mode' => $mode,
            'labels' => [
                'primary' => $primaryLabel,
                'secondary' => $secondaryLabel,
                'percentage' => $percentageLabel,
            ],
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'bar' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Kuota', 'data' => $seriesQuota],
                    ['name' => $primaryLabel, 'data' => $seriesPrimary],
                ],
            ],
            'donut' => [
                'labels' => [$primaryLabel, $secondaryLabel],
                'series' => [$totalUsage, $totalRemaining],
            ],
            'table' => [
                'rows' => $rows->values()->all(),
            ],
            'summary' => [
                'total_allocation' => $totalAllocation,
                'total_usage' => $totalUsage,
                'total_remaining' => $totalRemaining,
                'usage_label' => $primaryLabel,
                'secondary_label' => $secondaryLabel,
                'percentage_label' => $percentageLabel,
                'mode' => $mode,
            ],
        ];
    }

    private function buildActualRows(Carbon $start, Carbon $end)
    {
        return Quota::query()
            ->select([
                'quotas.id',
                'quotas.quota_number',
                'quotas.government_category',
                'quotas.total_allocation',
                'quotas.forecast_remaining',
                'quotas.actual_remaining',
            ])
            ->selectRaw('COALESCE(SUM(shipment_receipts.quantity_received),0) as actual_received')
            ->leftJoin('purchase_orders', 'purchase_orders.quota_id', '=', 'quotas.id')
            ->leftJoin('shipments', 'shipments.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('shipment_receipts', function ($join) use ($start, $end) {
                $join->on('shipment_receipts.shipment_id', '=', 'shipments.id')
                    ->whereDate('shipment_receipts.receipt_date', '>=', $start->toDateString())
                    ->whereDate('shipment_receipts.receipt_date', '<=', $end->toDateString());
            })
            ->groupBy(
                'quotas.id',
                'quotas.quota_number',
                'quotas.government_category',
                'quotas.total_allocation',
                'quotas.forecast_remaining',
                'quotas.actual_remaining'
            )
            ->orderBy('quotas.quota_number')
            ->get()
            ->map(function ($row) {
                $allocation = (int) ($row->total_allocation ?? 0);
                $actual = (int) ($row->actual_received ?? 0);
                $remaining = max(0, $allocation - $actual);
                $pct = $allocation > 0 ? round(($actual / $allocation) * 100, 2) : 0;

                return [
                    'quota_id' => $row->id,
                    'quota_number' => $row->quota_number,
                    'range_pk' => $row->government_category,
                    'initial_quota' => $allocation,
                    'primary_value' => $actual,
                    'secondary_value' => $remaining,
                    'percentage' => $pct,
                    'forecast_current_remaining' => (int) ($row->forecast_remaining ?? 0),
                    'actual_current_remaining' => (int) ($row->actual_remaining ?? 0),
                ];
            });
    }

    private function buildForecastRows(Carbon $start, Carbon $end)
    {
        // Use invoice-based forecast (Actual + In-Transit) computed via QuotaConsumptionService.
        $quotas = Quota::orderBy('quota_number')->get();
        try {
            $service = app(\App\Services\QuotaConsumptionService::class);
            $derived = $service->computeForQuotas($quotas);
        } catch (\Throwable $e) {
            $derived = [];
        }

        return $quotas->map(function($q) use ($derived) {
            $alloc = (int) ($q->total_allocation ?? 0);
            $d = $derived[$q->id] ?? ['forecast_consumed'=>0,'forecast_remaining'=>$alloc];
            $forecast = (int) ($d['forecast_consumed'] ?? 0);
            $remain = (int) max($alloc - $forecast, 0);
            $pct = $alloc > 0 ? round(($forecast / $alloc) * 100, 2) : 0;
            return [
                'quota_id' => $q->id,
                'quota_number' => $q->quota_number,
                'range_pk' => $q->government_category,
                'initial_quota' => $alloc,
                'primary_value' => $forecast,
                'secondary_value' => $remain,
                'percentage' => $pct,
                'forecast_current_remaining' => (int) ($d['forecast_remaining'] ?? $remain),
            ];
        });
    }

    private function resolveMode(?string $mode): string
    {
        return in_array($mode, ['forecast', 'actual'], true) ? $mode : 'actual';
    }
}
