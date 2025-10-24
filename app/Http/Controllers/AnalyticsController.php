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

        if ($mode === 'forecast') {
            $rows = $this->buildForecastRows($start, $end);
            $primaryLabel = 'Forecast (Consumption)';
            $secondaryLabel = 'Sisa Forecast';
            $percentageLabel = 'Penggunaan Forecast %';
        } else {
            $rows = $this->buildActualRows($start, $end);
            $primaryLabel = 'Actual (Good Receipt)';
            $secondaryLabel = 'Sisa Kuota';
            $percentageLabel = 'Pemakaian Actual %';
        }

        $rows = $rows->values();
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
    private function buildActualRows(Carbon $start, Carbon $end): Collection
    {
        $quotas = Quota::orderBy('quota_number')->get();
        if ($quotas->isEmpty()) {
            return collect();
        }

        $meta = [];
        $rows = [];

        foreach ($quotas as $quota) {
            $parsed = PkCategoryParser::parse((string) $quota->government_category);
            $meta[$quota->id] = [
                'min_pk' => $parsed['min_pk'],
                'max_pk' => $parsed['max_pk'],
                'min_incl' => $parsed['min_incl'],
                'max_incl' => $parsed['max_incl'],
                'start' => $quota->period_start?->toDateString(),
                'end' => $quota->period_end?->toDateString(),
            ];

            $rows[$quota->id] = [
                'quota_id' => $quota->id,
                'quota_number' => $quota->quota_number,
                'range_pk' => $quota->government_category,
                'initial_quota' => (float) ($quota->total_allocation ?? 0),
                'primary_value' => 0.0,
                'secondary_value' => (float) ($quota->total_allocation ?? 0),
                'percentage' => 0.0,
                'forecast_current_remaining' => (float) ($quota->forecast_remaining ?? 0),
                'actual_current_remaining' => (float) ($quota->actual_remaining ?? 0),
            ];
        }

        $grRows = DB::table('gr_receipts as gr')
            ->join('po_headers as ph', 'gr.po_no', '=', 'ph.po_number')
            ->join('po_lines as pl', function ($join) {
                $join->on('pl.po_header_id', '=', 'ph.id');
                $join->on('pl.line_no', '=', 'gr.line_no');
            })
            ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
            ->whereBetween('gr.receive_date', [$start->toDateString(), $end->toDateString()])
            ->select([
                'gr.qty',
                'gr.receive_date',
                'ph.po_date',
                'hs.pk_capacity',
            ])
            ->get();

        foreach ($grRows as $entry) {
            $pk = $entry->pk_capacity !== null ? (float) $entry->pk_capacity : null;
            if ($pk === null) {
                continue;
            }

            $receiptDate = $entry->receive_date;
            $poDate = $entry->po_date;

            foreach ($meta as $quotaId => $info) {
                if (!$this->pkMatchesQuota($pk, $info)) {
                    continue;
                }
                if (!$this->dateMatchesQuota($receiptDate, $info['start'], $info['end'], $poDate)) {
                    continue;
                }

                $rows[$quotaId]['primary_value'] += (float) $entry->qty;
                break;
            }
        }

        foreach ($rows as &$row) {
            $allocation = max(0.0, (float) $row['initial_quota']);
            $actual = (float) $row['primary_value'];
            $remaining = $allocation > 0 ? max($allocation - $actual, 0.0) : 0.0;
            $row['primary_value'] = round($actual, 2);
            $row['secondary_value'] = round($remaining, 2);
            $row['percentage'] = $allocation > 0
                ? round(($actual / $allocation) * 100, 2)
                : 0.0;
        }
        unset($row);

        return collect(array_values($rows));
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function buildForecastRows(Carbon $start, Carbon $end): Collection
    {
        $quotas = Quota::orderBy('quota_number')->get();
        if ($quotas->isEmpty()) {
            return collect();
        }

        try {
            $service = app(\App\Services\QuotaConsumptionService::class);
            $computed = $service->computeForQuotas($quotas);
        } catch (\Throwable $e) {
            $computed = [];
        }

        return $quotas->map(function (Quota $quota) use ($computed) {
            $allocation = (float) ($quota->total_allocation ?? 0);
            $data = $computed[$quota->id] ?? [
                'forecast_consumed' => 0,
                'forecast_remaining' => $allocation,
                'actual_consumed' => 0,
                'actual_remaining' => $allocation,
            ];
            $forecast = (float) ($data['forecast_consumed'] ?? 0);
            $remaining = max($allocation - $forecast, 0.0);
            $pct = $allocation > 0 ? round(($forecast / $allocation) * 100, 2) : 0;

            return [
                'quota_id' => $quota->id,
                'quota_number' => $quota->quota_number,
                'range_pk' => $quota->government_category,
                'initial_quota' => $allocation,
                'primary_value' => round($forecast, 2),
                'secondary_value' => round($remaining, 2),
                'percentage' => $pct,
                'forecast_current_remaining' => (float) ($data['forecast_remaining'] ?? $remaining),
                'actual_current_remaining' => (float) ($data['actual_remaining'] ?? max($allocation - ($data['actual_consumed'] ?? 0), 0)),
            ];
        });
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

