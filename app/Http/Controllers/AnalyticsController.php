<?php

namespace App\Http\Controllers;

use App\Models\Quota;
use App\Support\PkCategoryParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $year = (int) ($request->query('year', 0));
        $mode = $this->resolveMode($request->query('mode'));
        $selectedPk = trim((string) $request->query('pk', ''));

        if ($year > 0) {
            $startDate = Carbon::create($year, 1, 1)->toDateString();
            $endDate = Carbon::create($year, 12, 31)->toDateString();
        } else {
            $startDate = $start ? Carbon::parse($start)->toDateString() : now()->startOfMonth()->toDateString();
            $endDate = $end ? Carbon::parse($end)->toDateString() : now()->toDateString();
        }

        // Build PK options based on quotas within selected range (distinct government_category)
        try {
            $pkOptions = $this->queryQuotasByDateRange(\Carbon\Carbon::parse($startDate), \Carbon\Carbon::parse($endDate))
                ->whereNotNull('government_category')
                ->pluck('government_category')
                ->filter(fn ($v) => trim((string) $v) !== '')
                ->unique()
                ->sort()->values()->all();
        } catch (\Throwable $e) {
            $pkOptions = [];
        }

        return view('analytics.index', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'year' => $year > 0 ? $year : (int) now()->year,
            'mode' => $mode,
            'pk_options' => $pkOptions,
            'selected_pk' => $selectedPk,
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
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
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
            return response("\n[Missing dependency] Install Laravel-Excel first: composer require maatwebsite/excel\n", 501);
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
        ])->setPaper('a4', 'portrait');

        $mode = $dataset['mode'] ?? 'actual';

        return $pdf->download('analytics_'.$mode.'.pdf');
    }

    /**
     * @return array{
     *     mode: string,
     *     labels: array{primary:string,secondary:string,percentage:string},
     *     filters: array{start_date:string,end_date:string},
     *     bar: array<string,mixed>,
     *     donut: array<string,mixed>,
     *     table: array{rows: array<int,array<string,mixed>>},
     *     summary: array<string,mixed>
     * }
     */
    private function buildDataset(Request $request): array
    {
        [$start, $end] = $this->resolveRange($request);
        $mode = $this->resolveMode($request->query('mode'));
        $selectedPk = trim((string) $request->query('pk', ''));

        if ($mode === 'forecast') {
            $rows = $this->buildForecastRows($start, $end, $selectedPk);
            $primaryLabel = 'Forecast (Consumption)';
            $secondaryLabel = 'Sisa Forecast';
            $percentageLabel = 'Penggunaan Forecast %';
        } else {
            $rows = $this->buildActualRows($start, $end, $selectedPk);
            $primaryLabel = 'Actual (Good Receipt)';
            $secondaryLabel = 'Sisa Kuota';
            $percentageLabel = 'Pemakaian Actual %';
        }

        $rows = $rows->values();
        // Compute overall totals for both forecast and actual based on selected quotas
        $quotaIds = $rows->pluck('quota_id')->all();
        $totalAllocation = $rows->pluck('initial_quota')->map(fn ($v) => (float) $v)->sum();
        $totalForecast = 0.0; $totalActual = 0.0;
        if (!empty($quotaIds)) {
            try {
                $totalForecast = (float) DB::table('purchase_order_quota')
                    ->whereIn('quota_id', $quotaIds)
                    ->sum('allocated_qty');
            } catch (\Throwable $e) { $totalForecast = 0.0; }
            try {
                $totalActual = (float) DB::table('quota_histories')
                    ->where('change_type', 'actual_decrease')
                    ->whereIn('quota_id', $quotaIds)
                    ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                    ->select(DB::raw('SUM(ABS(quantity_change)) as qty'))
                    ->value('qty');
            } catch (\Throwable $e) { $totalActual = 0.0; }
        }
        $categories = $rows->pluck('quota_number')->all();
        $seriesQuota = $rows->pluck('initial_quota')->map(fn ($v) => (float) $v)->all();
        $seriesPrimary = $rows->pluck('primary_value')->map(fn ($v) => (float) $v)->all();

        $totalAllocation = array_sum($seriesQuota);
        $totalUsage = array_sum($seriesPrimary);
        $totalRemaining = array_sum(
            $rows->pluck('secondary_value')->map(fn ($v) => (float) $v)->all()
        );

        if ($totalRemaining === 0 && $totalAllocation > 0) {
            $totalRemaining = max($totalAllocation - $totalUsage, 0);
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
                'rows' => $rows->map(function ($row) {
                    return [
                        'quota_number' => $row['quota_number'],
                        'range_pk' => $row['range_pk'],
                        'initial_quota' => $row['initial_quota'],
                        'primary_value' => $row['primary_value'],
                        'secondary_value' => $row['secondary_value'],
                        'percentage' => $row['percentage'],
                        'forecast_current_remaining' => $row['forecast_current_remaining'] ?? null,
                        'actual_current_remaining' => $row['actual_current_remaining'] ?? null,
                    ];
                })->all(),
            ],
            'summary' => [
                'total_allocation' => $totalAllocation,
                'total_usage' => $totalUsage,
                'total_remaining' => $totalRemaining,
                // Use row values (with fallback) when mode=forecast so UI reflects current forecast even if pivot empty
                'total_forecast_consumed' => (float) ($mode === 'forecast' ? array_sum($seriesPrimary) : min($totalAllocation, $totalForecast)),
                'total_actual_consumed' => (float) min($totalAllocation, $totalActual),
                'total_in_transit' => (float) max(((float) ($mode === 'forecast' ? array_sum($seriesPrimary) : min($totalAllocation, $totalForecast))) - min($totalAllocation, $totalActual), 0.0),
                'total_forecast_remaining' => (float) ($mode === 'forecast' ? max($totalAllocation - array_sum($seriesPrimary), 0.0) : max($totalAllocation - min($totalAllocation, $totalForecast), 0.0)),
                'total_actual_remaining' => (float) max($totalAllocation - min($totalAllocation, $totalActual), 0.0),
                'usage_label' => $primaryLabel,
                'secondary_label' => $secondaryLabel,
                'percentage_label' => $percentageLabel,
                'mode' => $mode,
                ],
        ];
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function buildActualRows(Carbon $start, Carbon $end, ?string $pkLabel = null): Collection
    {
        $quotas = $this->queryQuotasByDateRange($start, $end, $pkLabel)->orderBy('quota_number')->get();
        if ($quotas->isEmpty()) {
            return collect();
        }
        $rows = [];
        foreach ($quotas as $q) {
            $rows[$q->id] = [
                'quota_id' => $q->id,
                'quota_number' => $q->quota_number,
                'range_pk' => $this->formatPkLabel($q->government_category),
                'initial_quota' => (float) ($q->total_allocation ?? 0),
                'primary_value' => 0.0,
                'secondary_value' => (float) ($q->total_allocation ?? 0),
                'percentage' => 0.0,
                'forecast_current_remaining' => (float) ($q->forecast_remaining ?? 0),
                'actual_current_remaining' => (float) ($q->actual_remaining ?? 0),
            ];
        }

        $ids = array_keys($rows);
        if (!empty($ids)) {
            $hist = DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', 'actual_decrease')
                ->whereIn('quota_id', $ids)
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');

            foreach ($hist as $qid => $qty) {
                $allocation = (float) $rows[$qid]['initial_quota'];
                $actual = min($allocation, (float) $qty);
                $rows[$qid]['primary_value'] = round($actual, 2);
                $rows[$qid]['secondary_value'] = round(max($allocation - $actual, 0.0), 2);
                $rows[$qid]['percentage'] = $allocation > 0 ? round(($actual / $allocation) * 100, 2) : 0.0;
            }
        }

        return collect(array_values($rows));
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function buildForecastRows(Carbon $start, Carbon $end, ?string $pkLabel = null): Collection
    {
        $quotas = $this->queryQuotasByDateRange($start, $end, $pkLabel)->orderBy('quota_number')->get();
        if ($quotas->isEmpty()) {
            return collect();
        }
        $ids = $quotas->pluck('id')->all();
        $forecastByQuota = [];
        if (!empty($ids)) {
            $forecastByQuota = DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', \App\Models\QuotaHistory::TYPE_FORECAST_DECREASE)
                ->whereIn('quota_id', $ids)
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');
        }

        return $quotas->map(function (Quota $quota) use ($forecastByQuota) {
            $allocation = (float) ($quota->total_allocation ?? 0);
            $forecast = min($allocation, (float) ($forecastByQuota[$quota->id] ?? 0));
            $remaining = max($allocation - $forecast, 0.0);
            $pct = $allocation > 0 ? round(($forecast / $allocation) * 100, 2) : 0.0;

            return [
                'quota_id' => $quota->id,
                'quota_number' => $quota->quota_number,
                'range_pk' => $this->formatPkLabel($quota->government_category),
                'initial_quota' => $allocation,
                'primary_value' => round($forecast, 2),
                'secondary_value' => round($remaining, 2),
                'percentage' => $pct,
                'forecast_current_remaining' => $remaining,
                'actual_current_remaining' => max($allocation - 0.0, 0.0),
            ];
        });
    }

    private function queryQuotasByDateRange(Carbon $start, Carbon $end, ?string $pkLabel = null)
    {
        $builder = Quota::query()->where(function ($q) use ($start, $end) {
            $q->where(function ($qq) use ($start) {
                $qq->whereNull('period_end')->orWhere('period_end', '>=', $start->toDateString());
            })->where(function ($qq) use ($end) {
                $qq->whereNull('period_start')->orWhere('period_start', '<=', $end->toDateString());
            });
        });

        $label = trim((string) $pkLabel);
        if ($label !== '') {
            $builder->where('government_category', $label);
        }

        return $builder;
    }

    private function formatPkLabel(?string $label): string
    {
        $v = trim((string) $label);
        if ($v === '') { return $v; }
        // If already contains 'PK' or looks non-numeric (e.g., ACC), keep as-is
        if (stripos($v, 'PK') !== false) { return $v; }
        // Append ' PK' for common numeric/range patterns
        if (preg_match('/[0-9<>-]/', $v)) { return $v.' PK'; }
        return $v;
    }

    private function resolveMode(?string $mode): string
    {
        return in_array($mode, ['forecast', 'actual'], true) ? $mode : 'actual';
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(Request $request): array
    {
        // Prefer single-year filter when provided
        $year = $request->query('year');
        if (!empty($year) && ctype_digit((string)$year)) {
            $y = (int) $year;
            $start = Carbon::create($y, 1, 1)->startOfDay();
            $end = Carbon::create($y, 12, 31)->endOfDay();
            return [$start, $end];
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy(), $start];
        }

        return [$start, $end];
    }

    /**
     * @param  array{min_pk:?float,max_pk:?float,min_incl:bool,max_incl:bool}  $info
     */
    private function pkMatchesQuota(float $pk, array $info): bool
    {
        if ($info['min_pk'] !== null) {
            $minOk = $info['min_incl'] ? $pk >= $info['min_pk'] : $pk > $info['min_pk'];
            if (!$minOk) {
                return false;
            }
        }

        if ($info['max_pk'] !== null) {
            $maxOk = $info['max_incl'] ? $pk <= $info['max_pk'] : $pk < $info['max_pk'];
            if (!$maxOk) {
                return false;
            }
        }

        return true;
    }

    private function dateMatchesQuota(?string $date, ?string $start, ?string $end, ?string $fallback = null): bool
    {
        if (!$start && !$end) {
            return true;
        }

        $target = $date ?? $fallback;
        if (!$target) {
            return true;
        }

        $targetDate = Carbon::parse($target)->toDateString();

        if ($start && $targetDate < $start) {
            return false;
        }

        if ($end && $targetDate > $end) {
            return false;
        }

        return true;
    }
}
