<?php

namespace App\Http\Controllers;

use App\Models\Quota;
use App\Support\PkCategoryParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    /**
     * Normalize and validate mode from query string.
     * Supported values: 'actual' (default) and 'forecast'.
     */
    private function resolveMode($raw): string
    {
        $v = is_string($raw) ? strtolower(trim($raw)) : '';
        return $v === 'forecast' ? 'forecast' : 'actual';
    }
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
        $filters = $dataset['filters'] ?? [];
        $year = (int) ($filters['year'] ?? now()->year);

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

        // HS/PK summary rows (added before details)
        $hs = $dataset['summary']['hs_pk'] ?? ['rows' => [], 'totals' => []];
        $hsRows = is_array($hs) ? ($hs['rows'] ?? []) : [];
        $hsTotals = is_array($hs) ? ($hs['totals'] ?? []) : [];
        $hsColumns = ['Hs Code','Capacity','Quota Approved','Quota Consumption until Dec-'.$year,'Consumption %','Balance Quota Until Dec','Balance %','Quota Consumption Start Jan-'.($year+1)];

        $callback = function () use ($columns, $rows, $hsColumns, $hsRows, $hsTotals) {
            $out = fopen('php://output', 'w');
            // Section 1: HS/PK Summary
            fputcsv($out, ['HS/PK Summary']);
            fputcsv($out, $hsColumns);
            foreach ($hsRows as $r) {
                fputcsv($out, [
                    $r['hs_code'] ?? '-',
                    $r['capacity_label'] ?? '',
                    $r['approved'] ?? 0,
                    $r['consumed_until_dec'] ?? 0,
                    $r['consumed_pct'] ?? 0,
                    $r['balance_until_dec'] ?? 0,
                    $r['balance_pct'] ?? 0,
                    $r['consumed_next_jan'] ?? 0,
                ]);
            }
            if (!empty($hsRows)) {
                fputcsv($out, ['Total', '', $hsTotals['approved'] ?? 0, $hsTotals['consumed_until_dec'] ?? 0, '', '', '', '']);
            }

            // Spacer
            fputcsv($out, []);
            // Section 2: Details per Quota
            fputcsv($out, ['Details per Quota']);
            fputcsv($out, $columns);
            foreach ($rows as $r) { fputcsv($out, $r); }
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportXlsx(Request $request)
    {
        // Build dataset to extract HS/PK summary
        $dataset = $this->buildDataset($request);

        $filters = $dataset['filters'] ?? [];
        $year = (int) ($filters['year'] ?? now()->year);
        $nextYear = $year + 1;
        $hs = $dataset['summary']['hs_pk'] ?? ['rows' => [], 'totals' => []];
        $rows = is_array($hs) ? ($hs['rows'] ?? []) : [];
        $totals = is_array($hs) ? ($hs['totals'] ?? []) : [];

        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return response("\n[Missing dependency] Install phpoffice/phpspreadsheet first: composer require phpoffice/phpspreadsheet\n", 501);
        }

        $sheetTitle = 'Analytics '.($dataset['mode'] === 'forecast' ? 'Forecast' : 'Actual');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        // Global font: Arial for all text
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        // Header labels (wrapped like the screenshot)
        $yearShort = substr((string) $year, -2);
        $nextShort = substr((string) $nextYear, -2);
        $headers = [
            'Hs Code',
            'Capacity',
            'Quota Approved',
            "Quota Consumption\nuntil Dec-{$yearShort}",
            "Balance Quota\nUntil Dec",
            "Quota Consumption\nStart Jan-{$nextShort}",
        ];

        // Start table from B2
        $baseCol = 2; // column B
        $headerRow = 2; // row 2

        // Write header (use column letters for broad compatibility)
        $c = 0;
        foreach ($headers as $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($baseCol + $c);
            $sheet->setCellValue($col.$headerRow, $h);
            $c++;
        }

        // Column widths (for B..G)
        $widths = [12, 14, 16, 28, 24, 28];
        foreach ($widths as $i => $w) { $sheet->getColumnDimensionByColumn($baseCol + $i)->setWidth($w); }

        // Header styles
        $headerRange = 'B2:G2';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        // Light grey header to better match the sample screenshot
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE7E6E6');
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setARGB('FF000000');
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        // Auto height for header row with wrapped text
        $sheet->getRowDimension($headerRow)->setRowHeight(-1);

        // Body rows
        $r = $headerRow + 1; // start from row 3
        foreach ($rows as $row) {
            $sheet->setCellValue("B{$r}", (string) ($row['hs_code'] ?? '-'));
            // Capacity label displayed in PK wording
            $capLabel = (string) ($row['capacity_label'] ?? '');
            $capBucket = $this->normalizeBucketKey($capLabel);
            $sheet->setCellValue("C{$r}", (string) $this->formatCapacityDisplay($capBucket));
            $sheet->setCellValueExplicit("D{$r}", (float) ($row['approved'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            // Consumption until Dec with percentage on new line (red)
            $consumed = (float) ($row['consumed_until_dec'] ?? 0);
            $pct = (float) ($row['consumed_pct'] ?? 0);
            $rt = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            // Use Indonesian-style formatting: thousands '.' and decimals ','
            $rt->createText((string) number_format($consumed, 0, ',', '.'));
            $rt->createText("\n");
            $run = $rt->createTextRun(number_format($pct, 2, ',', '.').'%');
            // Consumption percentage: red and bold
            $run->getFont()->getColor()->setARGB('FFC00000');
            $run->getFont()->setBold(true);
            $sheet->getCell("E{$r}")->setValue($rt);
            $sheet->getStyle("E{$r}")->getAlignment()->setWrapText(true);

            // Balance until Dec with percentage (grey)
            $balance = (float) ($row['balance_until_dec'] ?? 0);
            $balancePct = isset($row['balance_pct']) ? (float) $row['balance_pct'] : null;
            if ($balancePct !== null) {
                $rt2 = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $rt2->createText((string) number_format($balance, 0, ',', '.'));
                $rt2->createText("\n");
                $run2 = $rt2->createTextRun(number_format($balancePct, 2, ',', '.').'%');
                // Balance percentage: keep black and regular weight (not bold)
                $run2->getFont()->getColor()->setARGB('FF000000');
                $sheet->getCell("F{$r}")->setValue($rt2);
                $sheet->getStyle("F{$r}")->getAlignment()->setWrapText(true);
            } else {
                $sheet->setCellValue("F{$r}", (string) number_format($balance, 0, ',', '.'));
            }

            $sheet->setCellValueExplicit("G{$r}", (float) ($row['consumed_next_jan'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            $r++;
        }

        // Totals row
        $sheet->setCellValue("B{$r}", 'Total');
        $sheet->getStyle("B{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("D{$r}", (float) ($totals['approved'] ?? 0));
        $sheet->setCellValue("E{$r}", (float) ($totals['consumed_until_dec'] ?? 0));
        $sheet->getStyle("B{$r}:G{$r}")->getFont()->setBold(true);
        $sheet->getStyle("B{$r}:G{$r}")->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
            ->getColor()->setARGB('FF999999');

        // Apply thin borders to the whole table (header + body + totals)
        $sheet->getStyle("B2:G{$r}")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setARGB('FF000000');

        // Apply number formats to numeric columns (locale-specific separators will render in Excel)
        $sheet->getStyle("D3:D{$r}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("G3:G{$r}")->getNumberFormat()->setFormatCode('#,##0');

        // Alignments
        $sheet->getStyle("D3:D{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G3:G{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("B3:C{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // Ensure wrapped rows expand to fit both lines
        for ($ri = 3; $ri <= $r; $ri++) {
            $sheet->getRowDimension($ri)->setRowHeight(-1);
        }

        // Output
        $filename = 'analytics_'.$dataset['mode'].'_'.$year.'.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
    // XLSX and PDF exports removed by request; keep CSV only.

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
        $pkFilter = (strcasecmp($selectedPk, 'all') === 0) ? '' : $selectedPk;
        $summaryYear = (int) ($request->query('year') ?: $end->year);
        $yearStart = Carbon::create($summaryYear, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($summaryYear, 12, 31)->endOfDay();
        $nextJanStart = $yearStart->copy()->addYear()->startOfYear();
        $nextJanEnd = $nextJanStart->copy()->endOfMonth();

        // Resolve quotas for the selected year + PK filter
        $quotaQuery = $this->queryQuotasByDateRange($yearStart, $yearEnd);
        if ($pkFilter !== '') { $quotaQuery->where('government_category', $pkFilter); }
        $quotaSet = $quotaQuery->orderBy('quota_number')->get(['id','quota_number','government_category','total_allocation','period_start','period_end']);
        $quotaIds = $quotaSet->pluck('id')->all();
        $totalAllocationSummary = (float) $quotaSet->sum('total_allocation');

        if ($mode === 'forecast') {
            $rows = $this->buildForecastRows($start, $end, $pkFilter);
            $primaryLabel = 'Forecast (Consumption)';
            $secondaryLabel = 'Sisa Forecast';
            $percentageLabel = 'Penggunaan Forecast %';
        } else {
            $rows = $this->buildActualRows($start, $end, $pkFilter);
            $primaryLabel = 'Actual (Good Receipt)';
            $secondaryLabel = 'Sisa Kuota';
            $percentageLabel = 'Pemakaian Actual %';
        }

        $rows = $rows->values();
        // Compute totals using PO/GR + MOVE overlay; allocation from quotas only
        $quotaIds = !empty($quotaIds) ? $quotaIds : $rows->pluck('quota_id')->all();
        $totalAllocation = $totalAllocationSummary;
        $totalForecast = 0.0; $totalActual = 0.0;
        if (!empty($quotaIds)) {
            try {
                // Load quota cards for pairing and bounds
                $quotaCards = $quotaSet->isNotEmpty() ? $quotaSet : Quota::whereIn('id', $quotaIds)->get();

                // A. Build base arrays per quota from PO/GR, excluding ACC via subtraction
                $baseForecast = [];
                $baseActual = [];
                foreach ($quotaCards as $q) {
                    $cat = (string) ($q->government_category ?? '');
                    $bounds = PkCategoryParser::parse($cat);

                    $grn = DB::table('gr_receipts')
                        ->selectRaw("po_no, CAST(regexp_replace(CAST(line_no AS text),'[^0-9]','', 'g') AS INTEGER) AS ln")
                        ->selectRaw('SUM(qty) as qty')
                        ->groupBy('po_no','ln');

                    $baseAll = DB::table('po_lines as pl')
                        ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                        ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                        ->leftJoinSub($grn, 'grn', function($j){
                            $j->on('grn.po_no','=','ph.po_number')
                              ->whereRaw("grn.ln = CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','', 'g') AS INTEGER)");
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
                // Recompute base forecast excluding any PO that has pivot, add pivot overlay, compute totals
                // Base forecast (no pivot) per quota
                $baseForecastNoPivot = [];
                foreach ($quotaCards as $q) {
                    $cat = (string) ($q->government_category ?? '');
                    $bounds = PkCategoryParser::parse($cat);

                    $grn = DB::table('gr_receipts')
                        ->selectRaw("po_no, CAST(regexp_replace(CAST(line_no AS text),'[^0-9]','', 'g') AS INTEGER) AS ln")
                        ->selectRaw('SUM(qty) as qty')
                        ->groupBy('po_no','ln');

                    $baseJoin = DB::table('po_lines as pl')
                        ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                        ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                        ->leftJoinSub($grn, 'grn', function($j){
                            $j->on('grn.po_no','=','ph.po_number')
                              ->whereRaw("grn.ln = CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','', 'g') AS INTEGER)");
                        })
                        ->whereNotNull('pl.hs_code_id');

                    if ($bounds['min_pk'] !== null) {
                        $baseJoin->where('hs.pk_capacity', $bounds['min_incl'] ? '>=' : '>', $bounds['min_pk']);
                    }
                    if ($bounds['max_pk'] !== null) {
                        $baseJoin->where('hs.pk_capacity', $bounds['max_incl'] ? '<=' : '<', $bounds['max_pk']);
                    }
                    if (!empty($q->period_start) && !empty($q->period_end)) {
                        $baseJoin->whereBetween('ph.po_date', [
                            $q->period_start->toDateString(),
                            $q->period_end->toDateString(),
                        ]);
                    }

                    // Exclude any PO that has a pivot (any quota)
                    $bf = (clone $baseJoin)
                        ->join('purchase_orders as po','po.po_number','=','ph.po_number')
                        ->whereNotExists(function($qq){
                            $qq->select(DB::raw('1'))
                               ->from('purchase_order_quota as pq')
                               ->whereColumn('pq.purchase_order_id', 'po.id');
                        });
                    $bfAcc = (clone $bf)->whereRaw("COALESCE(UPPER(hs.hs_code),'') = 'ACC'");
                    $forecast_all_po = (float) (clone $bf)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                    $forecast_acc_po = (float) (clone $bfAcc)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                    $baseForecastNoPivot[$q->id] = max($forecast_all_po - $forecast_acc_po, 0.0);
                }

                // Pivot overlay (moved POs)
                $forecastFromPivot = DB::table('purchase_order_quota as pq')
                    ->select('pq.quota_id', DB::raw('SUM(COALESCE(pq.allocated_qty,0)) as qty'))
                    ->whereIn('pq.quota_id', $quotaIds)
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

                $forecastFinal = [];
                foreach ($quotaCards as $q) {
                    $forecastFinal[$q->id] = (float) ($baseForecastNoPivot[$q->id] ?? 0.0) + (float) ($forecastFromPivot[$q->id] ?? 0.0);
                }

                $totalForecast = array_sum($forecastFinal);
                $totalActual   = array_sum($baseActual);

            } catch (\Throwable $e) {
                $totalForecast = 0.0; $totalActual = 0.0;
            }
        }

        // Build HS/PK summary using the same quota set
        $hsSummary = $this->buildHsPkSummaryForQuotas($yearStart, $yearEnd, $quotaSet);

        // Debug totals
        try {
            Log::info('Analytics Totals', [
                'year' => $summaryYear,
                'quota_ids' => $quotaIds,
                'alloc' => $totalAllocationSummary,
                'forecast' => $totalForecast,
                'actual' => $totalActual,
            ]);
        } catch (\Throwable $e) {}

        // Compose chart data from per-quota rows
        $categories = $rows->pluck('quota_number')->all();
        $seriesQuota = $rows->pluck('initial_quota')->map(fn ($v) => (float) $v)->all();
        $seriesPrimary = $rows->pluck('primary_value')->map(fn ($v) => (float) $v)->all();

        // Donut uses current mode values
        $donutSeries = $mode === 'forecast'
            ? [ (float) $totalForecast, max(0.0, (float) $totalAllocation - (float) $totalForecast) ]
            : [ (float) $totalActual,   max(0.0, (float) $totalAllocation - (float) $totalActual) ];

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
                'year' => $summaryYear,
            ],
            'bar' => [
                'categories' => $categories,
                'series' => [
                    [ 'name' => 'Total Allocation', 'data' => $seriesQuota ],
                    [ 'name' => $primaryLabel, 'data' => $seriesPrimary ],
                ],
            ],
            'donut' => [ 'labels' => [$primaryLabel, $secondaryLabel], 'series' => $donutSeries ],
            'table' => [ 'rows' => $rows->all() ],
            'summary' => [
                'total_allocation' => (float) $totalAllocationSummary,
                'total_usage' => (float) $totalForecast,
                'total_remaining' => (float) max($totalAllocation - $totalForecast, 0.0),
                'total_forecast_consumed' => (float) $totalForecast,
                'total_actual_consumed' => (float) $totalActual,
                'total_in_transit' => (float) max($totalForecast - $totalActual, 0.0),
                'total_forecast_remaining' => (float) max($totalAllocationSummary - $totalForecast, 0.0),
                'total_actual_remaining' => (float) max($totalAllocationSummary - $totalActual, 0.0),
                'usage_label' => $primaryLabel,
                'secondary_label' => $secondaryLabel,
                'percentage_label' => $percentageLabel,
                'mode' => $mode,
                'hs_pk' => $hsSummary,
            ],
        ];
    }

    /**
     * Resolve date range from request.
     * - If 'year' is provided, use Jan 1..Dec 31 of that year
     * - Else use explicit start_date/end_date when provided
     * - Else default to current month start .. today
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $year = (int) ($request->query('year', 0));
        if ($year > 0) {
            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        try {
            $start = $request->query('start_date');
            $end = $request->query('end_date');
            $startC = $start ? Carbon::parse($start)->startOfDay() : now()->startOfMonth();
            $endC = $end ? Carbon::parse($end)->endOfDay() : now()->endOfDay();
            return [$startC, $endC];
        } catch (\Throwable $e) {
            return [now()->startOfMonth(), now()->endOfDay()];
        }
    }

    /**
     * Return an Eloquent query builder for quotas whose period overlaps the given range.
     */
    private function queryQuotasByDateRange(Carbon $start, Carbon $end)
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();
        return Quota::query()
            ->where(function ($q) use ($endStr) {
                $q->whereNull('period_start')
                  ->orWhere('period_start', '<=', $endStr);
            })
            ->where(function ($q) use ($startStr) {
                $q->whereNull('period_end')
                  ->orWhere('period_end', '>=', $startStr);
            })
            ->orderBy('quota_number');
    }

    /**
     * Build per-quota rows for Forecast mode using Quota fields.
     */
    private function buildForecastRows(Carbon $start, Carbon $end, string $selectedPk = ''): Collection
    {
        $q = $this->queryQuotasByDateRange($start, $end);
        if ($selectedPk !== '') {
            $q->where('government_category', $selectedPk);
        }
        $quotas = $q->get();

        return collect($quotas)->map(function ($quota) {
            $alloc = (float) ($quota->total_allocation ?? 0);
            $remaining = (float) ($quota->forecast_remaining ?? 0);
            $consumed = max($alloc - $remaining, 0);
            $pct = $alloc > 0 ? ($consumed / $alloc * 100) : 0.0;
            return [
                'quota_id' => (int) $quota->id,
                'quota_number' => (string) ($quota->display_number ?? $quota->quota_number ?? ''),
                'range_pk' => (string) ($quota->government_category ?? ''),
                'initial_quota' => $alloc,
                'primary_value' => $consumed,
                'secondary_value' => max($remaining, 0),
                'percentage' => $pct,
            ];
        });
    }

    /**
     * Build per-quota rows for Actual mode using Quota fields.
     */
    private function buildActualRows(Carbon $start, Carbon $end, string $selectedPk = ''): Collection
    {
        $q = $this->queryQuotasByDateRange($start, $end);
        if ($selectedPk !== '') {
            $q->where('government_category', $selectedPk);
        }
        $quotas = $q->get();

        return collect($quotas)->map(function ($quota) {
            $alloc = (float) ($quota->total_allocation ?? 0);
            $remaining = (float) ($quota->actual_remaining ?? 0);
            $consumed = max($alloc - $remaining, 0);
            $pct = $alloc > 0 ? ($consumed / $alloc * 100) : 0.0;
            return [
                'quota_id' => (int) $quota->id,
                'quota_number' => (string) ($quota->display_number ?? $quota->quota_number ?? ''),
                'range_pk' => (string) ($quota->government_category ?? ''),
                'initial_quota' => $alloc,
                'primary_value' => $consumed,
                'secondary_value' => max($remaining, 0),
                'percentage' => $pct,
            ];
        });
    }

    /**
     * Capacity helpers used by XLSX export. Keep conservative defaults.
     */
    private function normalizeBucketKey(?string $label): string
    {
        $label = trim((string) $label);
        if ($label === '') return '';
        try {
            $parsed = PkCategoryParser::parse($label);
            $min = $parsed['min_pk']; $max = $parsed['max_pk'];
            if ($min !== null && $max !== null && $min === $max) {
                return (string) $min;
            }
            if ($min !== null && $max !== null) {
                return $min.'-'.$max;
            }
            if ($min !== null) { return '>'.$min; }
            if ($max !== null) { return '<'.$max; }
        } catch (\Throwable $e) {}
        return $label;
    }

    private function formatCapacityDisplay(?string $bucket): string
    {
        $bucket = trim((string) $bucket);
        if ($bucket === '') return '';
        // Prefix with 'PK ' if not already present for friendliness
        $up = strtoupper($bucket);
        if (!str_starts_with($up, 'PK')) {
            return 'PK '.$bucket;
        }
        return $bucket;
    }

    /**
     * Build HS/PK summary using the provided quota set (year + PK filter).
     * Returns ['rows' => [...], 'totals' => [...]]
     */
    private function buildHsPkSummaryForQuotas(Carbon $yearStart, Carbon $yearEnd, Collection $quotaSet): array
    {
        if ($quotaSet->isEmpty()) {
            return ['rows' => [], 'totals' => []];
        }

        // Build PK bounds per selected quota
        $quotaRanges = [];
        $selectedCategories = [];
        foreach ($quotaSet as $q) {
            $p = PkCategoryParser::parse((string) ($q->government_category ?? ''));
            $quotaRanges[] = [
                'min_pk' => $p['min_pk'],
                'max_pk' => $p['max_pk'],
                'min_incl' => (bool)($p['min_incl'] ?? true),
                'max_incl' => (bool)($p['max_incl'] ?? true),
                'allocation' => (float) ($q->total_allocation ?? 0),
            ];
            if (!empty($q->government_category)) {
                $selectedCategories[(string)$q->government_category] = true;
            }
        }
        $selectedCategories = array_keys($selectedCategories);

        // Eligible HS codes excluding ACC
        $hsRows = DB::table('hs_code_pk_mappings')
            ->select(['id','hs_code','pk_capacity'])
            ->whereRaw("COALESCE(UPPER(hs_code),'') <> 'ACC'")
            ->get();

        $eligible = [];
        foreach ($hsRows as $r) {
            $pk = isset($r->pk_capacity) ? (float) $r->pk_capacity : null;
            if ($pk === null) { continue; }
            $ok = false;
            foreach ($quotaRanges as $qr) {
                $min = $qr['min_pk']; $max = $qr['max_pk'];
                $minI = $qr['min_incl']; $maxI = $qr['max_incl'];
                if (($min === null || ($minI ? $pk >= $min : $pk > $min))
                    && ($max === null || ($maxI ? $pk <= $max : $pk < $max))) { $ok = true; break; }
            }
            if ($ok) { $eligible[] = $r; }
        }

        if (empty($eligible)) { return ['rows' => [], 'totals' => []]; }

        // Actual consumption (GR) within the year per HS
        $grByHs = DB::table('gr_receipts as gr')
            ->join('po_headers as ph', 'ph.po_number', '=', 'gr.po_no')
            ->join('po_lines as pl', function ($join) {
                $join->on('pl.po_header_id', '=', 'ph.id')
                    ->whereRaw("regexp_replace(pl.line_no::text, '^0+', '') = regexp_replace(gr.line_no::text, '^0+', '')");
            })
            ->join('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
            ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
            ->whereBetween('gr.receive_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->groupBy('pl.hs_code_id')
            ->pluck(DB::raw('SUM(gr.qty) as total'), 'pl.hs_code_id');

        // Next-year consumption via pivot MOVE (purchase_order_quota → next-year quotas), grouped per HS
        $selectedYear = (int) $yearStart->year;
        $nextYear = $selectedYear + 1;
        $nextYearQuotaIds = [];
        try {
            $qy = Quota::query()
                ->whereYear('period_start', $nextYear)
                ->whereYear('period_end', $nextYear);
            if (!empty($selectedCategories)) {
                $qy->whereIn('government_category', $selectedCategories);
            }
            $nextYearQuotaIds = $qy->pluck('id')->all();
        } catch (\Throwable $e) { $nextYearQuotaIds = []; }

        $nextYearByHs = collect();
        try {
            if (!empty($nextYearQuotaIds)) {
                // Unique map Purchase Order → HS (exclude ACC) to avoid line-multiplication
                $poHsMap = DB::table('purchase_orders as po')
                    ->join('po_headers as ph', 'ph.po_number', '=', 'po.po_number')
                    ->join('po_lines as pl', 'pl.po_header_id', '=', 'ph.id')
                    ->join('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                    ->select('po.id as purchase_order_id', 'hs.id as hs_id')
                    ->groupBy('po.id', 'hs.id');

                $nextYearByHs = DB::table('purchase_order_quota as pq')
                    ->joinSub($poHsMap, 'map', function ($join) {
                        $join->on('map.purchase_order_id', '=', 'pq.purchase_order_id');
                    })
                    ->whereIn('pq.quota_id', $nextYearQuotaIds)
                    ->groupBy('map.hs_id')
                    ->pluck(DB::raw('SUM(COALESCE(pq.allocated_qty, 0)) as qty'), 'map.hs_id');
            }
        } catch (\Throwable $e) { $nextYearByHs = collect(); }

        // Diagnostic log (temporary)
        try {
            \Illuminate\Support\Facades\Log::info('HS PK Summary next year', [
                'year' => $selectedYear,
                'next_year_by_hs' => $nextYearByHs,
            ]);
        } catch (\Throwable $e) {}

        $rows = [];
        foreach ($eligible as $r) {
            $pk = isset($r->pk_capacity) ? (float) $r->pk_capacity : null;
            if ($pk === null) { continue; }
            $approved = 0.0;
            foreach ($quotaRanges as $qr) {
                $min = $qr['min_pk']; $max = $qr['max_pk'];
                $minI = $qr['min_incl']; $maxI = $qr['max_incl'];
                $match = ($min === null || ($minI ? $pk >= $min : $pk > $min))
                      && ($max === null || ($maxI ? $pk <= $max : $pk < $max));
                if ($match) { $approved += (float) ($qr['allocation'] ?? 0); }
            }
            $consumed = (float) ($grByHs[$r->id] ?? 0);
            if ($approved > 0 && $consumed > $approved) { $consumed = $approved; }
            $balance = max($approved - $consumed, 0.0);

            $consumedNextJan = (float) ($nextYearByHs[$r->id] ?? 0);

            $rows[] = [
                'hs_code' => (string) $r->hs_code,
                'capacity_label' => $this->formatCapacityDisplay($this->normalizeBucketKey((string) $r->pk_capacity)),
                'approved' => round($approved, 2),
                'consumed_until_dec' => round($consumed, 2),
                'consumed_pct' => $approved > 0 ? round(($consumed / $approved) * 100, 2) : 0.0,
                'balance_until_dec' => round($balance, 2),
                'balance_pct' => $approved > 0 ? round(($balance / $approved) * 100, 2) : 0.0,
                'consumed_next_jan' => round($consumedNextJan, 2),
            ];
        }

        $totApproved = array_sum(array_map(fn($r) => (float) $r['approved'], $rows));
        $totConsumed = array_sum(array_map(fn($r) => (float) $r['consumed_until_dec'], $rows));

        return [
            'rows' => $rows,
            'totals' => [
                'approved' => round($totApproved, 2),
                'consumed_until_dec' => round($totConsumed, 2),
            ],
        ];
    }

}
