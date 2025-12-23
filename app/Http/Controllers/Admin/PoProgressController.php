<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeriodSyncLog;
use App\Models\PurchaseOrder;
use App\Services\PeriodSyncService;
use App\Support\PeriodRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PoProgressController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) ($request->integer('per_page') ?: 10);
        $perPage = max(5, min($perPage, 50));

        [$selectedMonth, $selectedYear] = $this->resolveSelectedPeriod($request);
        [$periodStart, $periodEnd] = PeriodRange::monthYear($selectedMonth, $selectedYear);
        $periodKey = PeriodRange::periodKey($periodStart);

        $sharedViewData = [
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'periodKey' => $periodKey,
            'periodSyncLog' => PeriodSyncLog::lastFor('gr_receipts', $periodStart),
        ];

        $headersQuery = PurchaseOrder::query()
            ->select([
                'po_doc',
                DB::raw('MIN(created_date) as po_date'),
                DB::raw('MAX(vendor_name) as supplier'),
                DB::raw('SUM(qty) as qty_ordered'),
                DB::raw('SUM(quantity_received) as qty_received'),
            ])
            ->whereNotNull('po_doc')
            ->whereBetween('created_date', [$periodStart, $periodEnd])
            ->groupBy('po_doc');

        if ($q !== '') {
            $headersQuery->where(function ($s) use ($q) {
                $s->where('po_doc', 'like', "%{$q}%")
                    ->orWhere('vendor_name', 'like', "%{$q}%");
            });
        }

        $headers = $headersQuery
            ->orderByRaw('MIN(created_date) DESC')
            ->orderBy('po_doc')
            ->paginate($perPage)
            ->appends($request->query());

        $poNumbers = collect($headers->items())->pluck('po_doc')->filter()->values()->all();

        $linesByPo = collect();
        if (!empty($poNumbers)) {
            $lines = PurchaseOrder::query()
                ->select([
                    'po_doc',
                    'line_no',
                    'item_desc',
                    'item_code',
                    DB::raw('NULL as uom'),
                    'qty',
                    'quantity_received',
                ])
                ->whereIn('po_doc', $poNumbers)
                ->whereBetween('created_date', [$periodStart, $periodEnd])
                ->orderBy('po_doc')
                ->orderBy('line_no')
                ->get();

            $linesByPo = $lines->groupBy('po_doc');
        }

        $shipByLine = [];
        $grByLine = [];
        if (!empty($poNumbers)) {
            $invoices = DB::table('invoices')
                ->select(['po_no', 'line_no', 'invoice_date', 'qty'])
                ->whereIn('po_no', $poNumbers)
                ->whereBetween('invoice_date', [$periodStart, $periodEnd])
                ->get();

            foreach ($invoices as $row) {
                $key = $row->po_no.'#'.(string) $row->line_no;
                $shipByLine[$key][] = [
                    'date' => $row->invoice_date,
                    'type' => 'shipment',
                    'qty'  => (float) $row->qty,
                ];
            }

            $gr = DB::table('gr_receipts')
                ->select(['po_no', 'line_no', 'receive_date', 'qty'])
                ->whereIn('po_no', $poNumbers)
                ->whereBetween('receive_date', [$periodStart, $periodEnd])
                ->get();

            foreach ($gr as $row) {
                $key = $row->po_no.'#'.(string) $row->line_no;
                $grByLine[$key][] = [
                    'date' => $row->receive_date,
                    'type' => 'gr',
                    'qty'  => (float) $row->qty,
                ];
            }
        }

        $poData = [];
        foreach ($headers as $header) {
            $poNo = $header->po_doc;
            $summary = [
                'ordered_total' => (float) $header->qty_ordered,
                'received_total'=> (float) $header->qty_received,
                'shipped_total' => 0.0,
                'in_transit'    => 0.0,
                'remaining'     => max((float) $header->qty_ordered - (float) $header->qty_received, 0.0),
                'status'        => $this->determineStatus((float) $header->qty_ordered, (float) $header->qty_received),
            ];

            $linesOut = [];
            $linesForPo = $linesByPo->get($poNo, collect());
            foreach ($linesForPo as $line) {
                $ordered = (float) ($line->qty ?? 0);
                $received = (float) ($line->quantity_received ?? 0);
                $key = $poNo.'#'.(string) $line->line_no;

                $events = array_merge($shipByLine[$key] ?? [], $grByLine[$key] ?? []);
                usort($events, function ($a, $b) {
                    $da = $a['date'] ?? '';
                    $db = $b['date'] ?? '';
                    if ($da === $db) {
                        if ($a['type'] === $b['type']) {
                            return 0;
                        }
                        return $a['type'] === 'shipment' ? -1 : 1;
                    }
                    return strcmp((string) $da, (string) $db);
                });

                $shippedCum = 0.0;
                $receivedCum = $received;
                $computedRows = [];
                foreach ($events as $ev) {
                    if ($ev['type'] === 'shipment') {
                        $shippedCum += (float) $ev['qty'];
                    } else {
                        $receivedCum += (float) $ev['qty'];
                    }
                    $inTransit = max($shippedCum - $receivedCum, 0.0);
                    $remaining = max($ordered - $receivedCum, 0.0);
                    $computedRows[] = [
                        'date' => $ev['date'],
                        'type' => $ev['type'],
                        'qty'  => (float) $ev['qty'],
                        'ship_sum' => $shippedCum,
                        'gr_sum'   => $receivedCum,
                        'in_transit' => $inTransit,
                        'remaining'  => $remaining,
                    ];
                }

                $shippedTotal = array_sum(array_map(fn ($e) => (float) $e['qty'], $shipByLine[$key] ?? []));
                $summary['shipped_total'] += $shippedTotal;

                $linesOut[] = [
                    'line_no' => $line->line_no,
                    'item_desc' => $line->item_desc,
                    'model_code' => $line->item_code,
                    'uom' => $line->uom,
                    'ordered' => $ordered,
                    'shipped_total' => $shippedTotal,
                    'received_total' => $received,
                    'in_transit' => max($shippedTotal - $received, 0.0),
                    'remaining' => max($ordered - $received, 0.0),
                    'events' => $computedRows,
                ];
            }

            $summary['in_transit'] = max($summary['shipped_total'] - $summary['received_total'], 0.0);

            $poDate = null;
            if (!empty($header->po_date)) {
                try {
                    $poDate = Carbon::parse($header->po_date)->toDateString();
                } catch (\Throwable $e) {
                    $poDate = (string) $header->po_date;
                }
            }

            $poData[$poNo] = [
                'summary' => $summary,
                'lines' => $linesOut,
                'meta' => [
                    'po_date' => $poDate,
                    'supplier' => $header->supplier,
                ],
            ];
        }

        return view('admin.po_progress.index', array_merge([
            'headers' => $headers,
            'poData' => $poData,
            'q' => $q,
            'perPage' => $perPage,
        ], $sharedViewData));
    }

    public function sync(Request $request, PeriodSyncService $syncService): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        [$start, $end] = PeriodRange::monthYear((int) $data['month'], (int) $data['year']);
        $syncService->syncGoodsReceipts($start, $end);

        return redirect()
            ->route('admin.po_progress.index', ['month' => $data['month'], 'year' => $data['year']])
            ->with('status', sprintf('GR sync for %s triggered.', $start->format('F Y')));
    }

    private function resolveSelectedPeriod(Request $request): array
    {
        $now = Carbon::now();
        $month = (int) $request->query('month', 0);
        $year = (int) $request->query('year', 0);

        if ((!$month || !$year) && $request->filled('period')) {
            $period = (string) $request->string('period');
            if (preg_match('/^(\\d{4})-(\\d{2})$/', $period, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
            }
        }

        if ($month < 1 || $month > 12) {
            $month = $now->month;
        }

        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }

        return [$month, $year];
    }

    private function monthOptions(): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::create(null, $m, 1)->format('F');
        }
        return $months;
    }

    private function yearOptions(): array
    {
        $current = Carbon::now()->year;
        return range($current - 2, $current + 1);
    }

    private function determineStatus(float $ordered, float $received): string
    {
        if ($received <= 0 || $ordered <= 0) {
            return 'Ordered';
        }

        if ($received < $ordered) {
            return 'In Transit';
        }

        return 'Completed';
    }
}
