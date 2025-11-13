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
        $summaryYear = (int) ($request->query('year') ?: $end->year);
        $yearStart = Carbon::create($summaryYear, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($summaryYear, 12, 31)->endOfDay();
        $nextJanStart = $yearStart->copy()->addYear()->startOfYear();
        $nextJanEnd = $nextJanStart->copy()->endOfMonth();

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
        // Compute totals using the same source-of-truth as Dashboard (PO/GR + MOVE, exclude ACC)
        $quotaIds = $rows->pluck('quota_id')->all();
        $totalAllocation = $rows->pluck('initial_quota')->map(fn ($v) => (float) $v)->sum();
        $totalForecast = 0.0; $totalActual = 0.0;
        if (!empty($quotaIds)) {
            try {
                // Load quota cards for pairing and bounds
                $quotaCards = Quota::whereIn('id', $quotaIds)->get();

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

                // B. DELTA overlay for any selected year.
                //    Use pivot on "next" year quotas to move qty from prev->next by matching PK label.
                $forecastFinal = $baseForecast;
                $windowStart = Carbon::create($summaryYear, 1, 1)->startOfDay();
                $windowEnd   = Carbon::create($summaryYear + 1, 12, 31)->endOfDay();
                $nextQuotas = \App\Models\Quota::query()
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

                        // If the "next" quota belongs to the selected year, add move to it
                        if ($nextYear === $summaryYear) {
                            $curr = $quotaCards->first(function($q) use ($catKey) {
                                if (empty($q->period_start)) { return false; }
                                return strtolower(trim((string) ($q->government_category ?? ''))) === $catKey;
                            });
                            if ($curr) {
                                $forecastFinal[$curr->id] = max((float) ($baseForecast[$curr->id] ?? 0.0) + $move, 0.0);
                            }
                        }

                        // If the selected year is the "prev" year of this next quota, subtract from that prev
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
            } catch (\Throwable $e) {
                $totalForecast = 0.0; $totalActual = 0.0;
            }
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

        // Build HS/PK summary (approved vs consumption based on HS mapping and quota periods)
        $hsSummary = $this->buildHsPkSummary($yearStart, $yearEnd, $nextJanStart, $nextJanEnd);

        // Business-defined KPI totals for the selected year window
        // - Allocation: sum of quota_approved from quotas overlapping the year and mapped to any non-ACC HS
        // - Forecast Consumed: sum of PO ordered qty for PO lines with HS mapped to those quotas (exclude ACC)
        // - Actual Consumed: sum of GR qty joined to those PO lines (exclude ACC)
        // - In-Transit: Forecast Consumed - Actual Consumed
        try {
            // Load quotas overlapping the year and parse PK ranges
            $quotaRows = $this->queryQuotasByDateRange($yearStart, $yearEnd)->get(['id','government_category','total_allocation']);
            $quotaRanges = [];
            foreach ($quotaRows as $q) {
                $p = PkCategoryParser::parse((string) $q->government_category);
                $quotaRanges[(int)$q->id] = [
                    'min_pk' => $p['min_pk'],
                    'max_pk' => $p['max_pk'],
                    'min_incl' => (bool)($p['min_incl'] ?? true),
                    'max_incl' => (bool)($p['max_incl'] ?? true),
                    'allocation' => (float) ($q->total_allocation ?? 0),
                ];
            }

            // Determine eligible HS rows (exclude ACC) that match any of the year quotas
            $hsMap = DB::table('hs_code_pk_mappings')
                ->select(['id','hs_code','pk_capacity'])
                ->whereRaw("COALESCE(UPPER(hs_code),'') <> 'ACC'")
                ->get();
            $eligibleHsIds = [];
            $hasMatchPerQuota = [];
            foreach ($hsMap as $hs) {
                $pk = isset($hs->pk_capacity) ? (float) $hs->pk_capacity : null;
                if ($pk === null) { continue; }
                foreach ($quotaRanges as $qid => $info) {
                    if ($this->pkMatchesQuota($pk, $info)) {
                        $eligibleHsIds[(int) $hs->id] = true;
                        $hasMatchPerQuota[(int) $qid] = true;
                    }
                }
            }

            $eligibleIds = array_keys($eligibleHsIds);
            $eligibleQuotaIds = array_keys($hasMatchPerQuota);

            // Allocation
            $bizTotalAlloc = 0.0;
            foreach ($eligibleQuotaIds as $qid) {
                $bizTotalAlloc += (float) ($quotaRanges[$qid]['allocation'] ?? 0);
            }

            // Forecast Consumed (PO ordered qty)
            $bizTotalPo = 0.0;
            if (!empty($eligibleIds)) {
                $bizTotalPo = (float) DB::table('po_lines as pl')
                    ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                    ->whereIn('pl.hs_code_id', $eligibleIds)
                    ->select(DB::raw('SUM(COALESCE(pl.qty_ordered,0)) as s'))
                    ->value('s');
            }

            // Actual Consumed (GR qty joined to PO lines)
            $bizTotalGr = 0.0;
            if (!empty($eligibleIds)) {
                $grn = DB::table('gr_receipts')
                    ->selectRaw("po_no, CAST(regexp_replace(CAST(line_no AS text),'[^0-9]','', 'g') AS INTEGER) AS ln")
                    ->selectRaw('SUM(qty) as qty')
                    ->groupBy('po_no','ln');

                $bizTotalGr = (float) DB::table('po_lines as pl')
                    ->join('po_headers as ph','pl.po_header_id','=','ph.id')
                    ->leftJoinSub($grn, 'grn', function($j){
                        $j->on('grn.po_no','=','ph.po_number')
                          ->whereRaw("grn.ln = CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','', 'g') AS INTEGER)");
                    })
                    ->whereIn('pl.hs_code_id', $eligibleIds)
                    ->select(DB::raw('SUM(COALESCE(grn.qty,0)) as s'))
                    ->value('s');
            }

            $bizInTransit = max($bizTotalPo - $bizTotalGr, 0.0);
            $bizForecastRem = max($bizTotalAlloc - $bizTotalPo, 0.0);
            $bizActualRem = max($bizTotalAlloc - $bizTotalGr, 0.0);
        } catch (\Throwable $e) {
            $bizTotalAlloc = $bizTotalPo = $bizTotalGr = $bizInTransit = $bizForecastRem = $bizActualRem = 0.0;
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
                'year' => $summaryYear,
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
                // KPIs derived from the same rules as PK Bucket (PO/GR + MOVE, exclude ACC)
                'total_allocation' => (float) $totalAllocation,
                'total_usage' => (float) $totalForecast,
                'total_remaining' => (float) max($totalAllocation - $totalForecast, 0.0),
                'total_forecast_consumed' => (float) $totalForecast,
                'total_actual_consumed' => (float) $totalActual,
                'total_in_transit' => (float) max($totalForecast - $totalActual, 0.0),
                'total_forecast_remaining' => (float) max($totalAllocation - $totalForecast, 0.0),
                'total_actual_remaining' => (float) max($totalAllocation - $totalActual, 0.0),
                'usage_label' => $primaryLabel,
                'secondary_label' => $secondaryLabel,
                'percentage_label' => $percentageLabel,
                'mode' => $mode,
                'hs_pk' => $hsSummary,
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

    /**
     * Build HS/PK summary in the form of rows keyed by HS Code + capacity bucket.
     * Columns: hs_code, capacity_label, approved, consumed_until_dec, consumed_pct,
     *          balance_until_dec, balance_pct, consumed_next_jan
     * Sums are computed from Quota.total_allocation and quota_histories (actual_decrease).
     * The HS code is chosen from hs_code_pk_mappings for each PK bucket.
     */
    private function buildHsPkSummary(Carbon $yearStart, Carbon $yearEnd, Carbon $nextJanStart, Carbon $nextJanEnd): array
    {
        // Quotas for the selected year and their PK ranges
        $quotas = $this->queryQuotasByDateRange($yearStart, $yearEnd)->get(['id','government_category','total_allocation']);
        $quotaRanges = [];
        foreach ($quotas as $q) {
            $p = PkCategoryParser::parse((string) $q->government_category);
            $quotaRanges[(int)$q->id] = [
                'min_pk' => $p['min_pk'],
                'max_pk' => $p['max_pk'],
                'min_incl' => (bool)($p['min_incl'] ?? true),
                'max_incl' => (bool)($p['max_incl'] ?? true),
                'allocation' => (float) ($q->total_allocation ?? 0),
            ];
        }

        // Eligible HS (non-ACC) that map to at least one  year quota by PK
        $hsRows = DB::table('hs_code_pk_mappings')
            ->select(['id','hs_code','pk_capacity'])
            ->whereRaw("COALESCE(UPPER(hs_code),'') <> 'ACC'")
            ->get();

        $eligibleHs = [];
        foreach ($hsRows as $r) {
            $pk = isset($r->pk_capacity) ? (float) $r->pk_capacity : null;
            if ($pk === null) { continue; }
            foreach ($quotaRanges as $qr) {
                if ($this->pkMatchesQuota($pk, $qr)) { $eligibleHs[] = $r; break; }
            }
        }

        if (empty($eligibleHs)) {
            return ['rows' => [], 'totals' => ['approved' => 0.0, 'consumed_until_dec' => 0.0]];
        }

        $eligibleHsIds = array_map(fn($o) => (int) $o->id, $eligibleHs);

        // Actual consumption (GR) grouped by HS using a simple join and normalized line_no
        $grByHs = [];
        try {
            $grByHs = DB::table('gr_receipts as gr')
                ->join('po_headers as ph', 'ph.po_number', '=', 'gr.po_no')
                ->join('po_lines as pl', function ($join) {
                    $join->on('pl.po_header_id', '=', 'ph.id');
                    // Normalize line_no so '030' matches 30
                    $join->whereRaw("regexp_replace(pl.line_no::text, '^0+', '') = regexp_replace(gr.line_no::text, '^0+', '')");
                })
                ->join('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                // Exclude ACC only; no extra filters
                ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                // Restrict GR within the selected year window
                ->where('gr.receive_date', '>=', $yearStart->toDateString())
                ->where('gr.receive_date', '<=', $yearEnd->toDateString())
                ->groupBy('pl.hs_code_id')
                ->pluck(DB::raw('SUM(gr.qty) as total'), 'pl.hs_code_id');
        } catch (\Throwable $e) { $grByHs = collect(); }

        // Forecast consumption start next January: pivot-based, distinct per PO + HS mapping
        $nextYearConsumptionByHs = collect();
        try {
            $selectedYear = (int) $yearStart->year;
            $nextYearStart = \Carbon\Carbon::create($selectedYear + 1, 1, 1)->toDateString();
            $nextYearEnd   = \Carbon\Carbon::create($selectedYear + 1, 12, 31)->toDateString();

            $nextYearQuotaIds = DB::table('quotas')
                ->whereDate('period_start', '>=', $nextYearStart)
                ->whereDate('period_end', '<=', $nextYearEnd)
                ->pluck('id')
                ->all();

            if (!empty($nextYearQuotaIds)) {
                // Map unique Purchase Order -> HS (exclude ACC); avoids multiplying by line count
                $poHsMap = DB::table('purchase_orders as po')
                    ->join('po_headers as ph', 'ph.po_number', '=', 'po.po_number')
                    ->join('po_lines as pl', 'pl.po_header_id', '=', 'ph.id')
                    ->join('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                    ->select('po.id as purchase_order_id', 'hs.id as hs_id')
                    ->groupBy('po.id', 'hs.id');

                $nextYearConsumptionByHs = DB::table('purchase_order_quota as pq')
                    ->joinSub($poHsMap, 'map', function ($join) {
                        $join->on('map.purchase_order_id', '=', 'pq.purchase_order_id');
                    })
                    ->whereIn('pq.quota_id', $nextYearQuotaIds)
                    ->groupBy('map.hs_id')
                    ->pluck(DB::raw('SUM(COALESCE(pq.allocated_qty, 0)) as qty'), 'map.hs_id');
            } else {
                $nextYearConsumptionByHs = collect();
            }
        } catch (\Throwable $e) { $nextYearConsumptionByHs = collect(); }

        // Compose rows per HS/PK
        $rows = [];
        foreach ($eligibleHs as $r) {
            $pk = isset($r->pk_capacity) ? (float) $r->pk_capacity : null;
            if ($pk === null) { continue; }
            $approved = 0.0;
            foreach ($quotaRanges as $qr) { if ($this->pkMatchesQuota($pk, $qr)) { $approved += (float) ($qr['allocation'] ?? 0); } }
            $consumed = (float) ($grByHs[$r->id] ?? 0);
            if ($approved > 0 && $consumed > $approved) { $consumed = $approved; }
            $balance = max($approved - $consumed, 0.0);
            $jan = (float) ($nextYearConsumptionByHs[$r->id] ?? 0);

            $rows[] = [
                'hs_code' => (string) $r->hs_code,
                'capacity_label' => $this->formatCapacityDisplay($this->capacityBucketKeyFromPk($pk)),
                'approved' => round($approved, 2),
                'consumed_until_dec' => round($consumed, 2),
                'consumed_pct' => $approved > 0 ? round(($consumed / $approved) * 100, 2) : 0.0,
                'balance_until_dec' => round($balance, 2),
                'balance_pct' => $approved > 0 ? round(($balance / $approved) * 100, 2) : 0.0,
                'consumed_next_jan' => round($jan, 2),
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

    private function normalizeBucketKey(string $vlabel): string
    {
        $vlabel = (string) $vlabel;
        $p = PkCategoryParser::parse($vlabel);
        $min = $p['min_pk']; $max = $p['max_pk'];
        $minI = (bool)($p['min_incl'] ?? true); $maxI = (bool)($p['max_incl'] ?? true);
        if ($min === null && $max !== null && (float)$max === 8.0 && $maxI === false) { return '<8'; }
        if ($min !== null && (float)$min === 8.0 && $minI === true && $max !== null && (float)$max === 10.0 && $maxI === true) { return '8-10'; }
        if ($max === null && $min !== null && (float)$min === 10.0 && $minI === false) { return '>10'; }
        // Loose fallback by text (avoid PHP 8-only helpers)
        $s = strtoupper(str_replace(' ', '', $vlabel));
        if (substr($s, 0, 2) === '<8') { return '<8'; }
        if (strpos($s, '8-10') !== false) { return '8-10'; }
        if (substr($s, 0, 3) === '>10') { return '>10'; }
        return $vlabel;
    }

    private function formatCapacityDisplay(string $bk): string
    {
        // Match requested display exactly: "<8 PK", "8-10 PK", ">10 PK" (avoid match expression for compatibility)
        $bk = (string) $bk;
        if ($bk === '<8') { return '<8 PK'; }
        if ($bk === '8-10') { return '8-10 PK'; }
        if ($bk === '>10') { return '>10 PK'; }
        return $bk;
    }

    private function capacityBucketKeyFromPk(float $v): string
    {
        $v = (float) $v;
        if ($v < 8) { return '<8'; }
        if ($v > 10) { return '>10'; }
        return '8-10';
    }

    

    private function resolveMode(?string $m): string
    {
        return in_array($m, ['forecast', 'actual'], true) ? $m : 'actual';
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(Request $req): array
    {
        // Prefer single-year filter when provided
        $year = $req->query('year');
        if (!empty($year) && ctype_digit((string)$year)) {
            $y = (int) $year;
            $start = Carbon::create($y, 1, 1)->startOfDay();
            $end = Carbon::create($y, 12, 31)->endOfDay();
            return [$start, $end];
        }

        $startDate = $req->query('start_date');
        $endDate = $req->query('end_date');

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
    private function pkMatchesQuota(float $pkVal, array $data): bool
    {
        $pkVal = (float) $pkVal;
        if ($data['min_pk'] !== null) {
            $minOk = $data['min_incl'] ? $pkVal >= $data['min_pk'] : $pkVal > $data['min_pk'];
            if (!$minOk) {
                return false;
            }
        }

        if ($data['max_pk'] !== null) {
            $maxOk = $data['max_incl'] ? $pkVal <= $data['max_pk'] : $pkVal < $data['max_pk'];
            if (!$maxOk) {
                return false;
            }
        }

        return true;
    }

    private function dateMatchesQuota(?string $date, ?string $startVal, ?string $endVal, ?string $fallbackVal = null): bool
    {
        if (!$startVal && !$endVal) {
            return true;
        }

        $target = $date ?? $fallbackVal;
        if (!$target) {
            return true;
        }

        $targetDate = Carbon::parse($target)->toDateString();

        if ($startVal && $targetDate < $startVal) {
            return false;
        }

        if ($endVal && $targetDate > $endVal) {
            return false;
        }

        return true;
    }
}

