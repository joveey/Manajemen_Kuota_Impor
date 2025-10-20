<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\Shipment;
use App\Models\ShipmentReceipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class FinalReportController extends Controller
{
    public function index(Request $request): View
    {
        $dataset = $this->buildDataset($request);
        $topShipments = $this->buildOutstandingShipments($request);

        return view('admin.reports.final', [
            'filters' => $dataset['filters'],
            'summary' => $dataset['summary'],
            'rows' => $dataset['rows'],
            'charts' => $dataset['charts'],
            'topShipments' => $topShipments,
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
            'Quota Number',
            'Quota Name',
            'Range PK',
            'Total Allocation',
            'Forecast Remaining',
            'Actual Remaining',
            'PO Count',
            'PO Qty',
            'PO Received',
            'PO Outstanding',
            'Shipment Count',
            'Shipment Planned',
            'Shipment Received',
            'Shipment Outstanding',
            'Latest Receipt',
        ];

        $callback = function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['quota_number'],
                    $row['quota_name'],
                    $row['range_pk'],
                    $row['total_allocation'],
                    $row['forecast_remaining'],
                    $row['actual_remaining'],
                    $row['po_count'],
                    $row['po_quantity'],
                    $row['po_received'],
                    $row['po_outstanding'],
                    $row['shipment_count'],
                    $row['shipment_planned'],
                    $row['shipment_received'],
                    $row['shipment_outstanding'],
                    $row['last_receipt_date'] ?? '',
                ]);
            }
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * @return array{
     *     filters: array<string,string>,
     *     summary: array<string,int>,
     *     rows: array<int,array<string,mixed>>
     * }
     */
    private function buildDataset(Request $request): array
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfYear();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy(), $start];
        }

        $startString = $start->toDateString();
        $endString = $end->toDateString();

        $quotas = Quota::query()
            ->select([
                'quotas.id',
                'quotas.quota_number',
                'quotas.name',
                'quotas.government_category',
                'quotas.total_allocation',
                'quotas.forecast_remaining',
                'quotas.actual_remaining',
            ])
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('purchase_orders')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('quota_id', 'quotas.id')
                    ->whereBetween('order_date', [$startString, $endString]);
            }, 'po_count')
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('purchase_orders')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('quota_id', 'quotas.id')
                    ->whereBetween('order_date', [$startString, $endString]);
            }, 'po_quantity')
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('purchase_orders')
                    ->selectRaw('COALESCE(SUM(quantity_received), 0)')
                    ->whereColumn('quota_id', 'quotas.id')
                    ->whereBetween('order_date', [$startString, $endString]);
            }, 'po_received')
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('shipments')
                    ->selectRaw('COUNT(*)')
                    ->join('purchase_orders as po2', 'po2.id', '=', 'shipments.purchase_order_id')
                    ->whereColumn('po2.quota_id', 'quotas.id')
                    ->whereBetween('po2.order_date', [$startString, $endString]);
            }, 'shipment_count')
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('shipments')
                    ->selectRaw('COALESCE(SUM(shipments.quantity_planned), 0)')
                    ->join('purchase_orders as po2', 'po2.id', '=', 'shipments.purchase_order_id')
                    ->whereColumn('po2.quota_id', 'quotas.id')
                    ->whereBetween('po2.order_date', [$startString, $endString]);
            }, 'shipment_planned')
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('shipments')
                    ->selectRaw('COALESCE(SUM(shipments.quantity_received), 0)')
                    ->join('purchase_orders as po2', 'po2.id', '=', 'shipments.purchase_order_id')
                    ->whereColumn('po2.quota_id', 'quotas.id')
                    ->whereBetween('po2.order_date', [$startString, $endString]);
            }, 'shipment_received')
            ->selectSub(function ($query) use ($startString, $endString) {
                $query->from('shipment_receipts as sr')
                    ->selectRaw('MAX(sr.receipt_date)')
                    ->join('shipments as s', 's.id', '=', 'sr.shipment_id')
                    ->join('purchase_orders as po2', 'po2.id', '=', 's.purchase_order_id')
                    ->whereColumn('po2.quota_id', 'quotas.id')
                    ->whereBetween('po2.order_date', [$startString, $endString])
                    ->whereBetween('sr.receipt_date', [$startString, $endString]);
            }, 'last_receipt_date')
            ->orderBy('quotas.quota_number')
            ->get();

        $rows = $quotas->map(function ($item) {
            $poQuantity = (int) $item->po_quantity;
            $poReceived = (int) $item->po_received;
            $shipmentPlanned = (int) $item->shipment_planned;
            $shipmentReceived = (int) $item->shipment_received;
            $lastReceipt = $item->last_receipt_date ? Carbon::parse($item->last_receipt_date)->format('d M Y') : null;

            return [
                'quota_number' => $item->quota_number,
                'quota_name' => $item->name,
                'range_pk' => $item->government_category,
                'total_allocation' => (int) $item->total_allocation,
                'forecast_remaining' => (int) $item->forecast_remaining,
                'actual_remaining' => (int) $item->actual_remaining,
                'po_count' => (int) $item->po_count,
                'po_quantity' => $poQuantity,
                'po_received' => $poReceived,
                'po_outstanding' => max(0, $poQuantity - $poReceived),
                'shipment_count' => (int) $item->shipment_count,
                'shipment_planned' => $shipmentPlanned,
                'shipment_received' => $shipmentReceived,
                'shipment_outstanding' => max(0, $shipmentPlanned - $shipmentReceived),
                'last_receipt_date' => $lastReceipt,
            ];
        })->values()->all();

        $rowCollection = collect($rows);

        $summary = [
            'po_count' => (int) $rowCollection->sum('po_count'),
            'po_quantity' => (int) $rowCollection->sum('po_quantity'),
            'po_received' => (int) $rowCollection->sum('po_received'),
            'po_outstanding' => max(0, (int) $rowCollection->sum('po_quantity') - (int) $rowCollection->sum('po_received')),
            'shipment_count' => (int) $rowCollection->sum('shipment_count'),
            'shipment_planned' => (int) $rowCollection->sum('shipment_planned'),
            'shipment_received' => (int) $rowCollection->sum('shipment_received'),
            'shipment_outstanding' => max(0, (int) $rowCollection->sum('shipment_planned') - (int) $rowCollection->sum('shipment_received')),
            'total_allocation' => (int) $rowCollection->sum('total_allocation'),
            'total_actual_consumed' => (int) $rowCollection->sum('shipment_received'),
            'total_actual_remaining' => (int) $rowCollection->sum('actual_remaining'),
        ];

        $shipmentPlannedTotal = $summary['shipment_planned'];
        $shipmentReceivedTotal = $summary['shipment_received'];
        $shipmentOutstandingTotal = $summary['shipment_outstanding'];

        $highlights = $rowCollection
            ->map(function (array $row) {
                $allocation = max(1, (int) $row['total_allocation']);
                $received = (int) $row['shipment_received'];
                return $row + [
                    'actual_pct' => round(($received / $allocation) * 100, 1),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['actual_pct'])
            ->take(3)
            ->values()
            ->map(fn (array $row) => [
                'quota_number' => $row['quota_number'],
                'quota_name' => $row['quota_name'],
                'range_pk' => $row['range_pk'],
                'actual_pct' => $row['actual_pct'],
                'shipment_received' => $row['shipment_received'],
                'shipment_outstanding' => $row['shipment_outstanding'],
            ])
            ->all();

        $poStatus = $this->buildPoStatus($startString, $endString);
        $shipmentStatus = $this->buildShipmentStatus($startString, $endString);
        $monthlyActual = $this->buildMonthlyActual($startString, $endString);

        return [
            'filters' => [
                'start_date' => $startString,
                'end_date' => $endString,
            ],
            'summary' => $summary,
            'rows' => $rows,
            'highlights' => $highlights,
            'po_status' => $poStatus,
            'shipment_status' => $shipmentStatus,
            'charts' => [
                'quota_bar' => [
                    'categories' => $rowCollection->pluck('quota_number')->all(),
                    'series' => [
                        [
                            'name' => 'Shipment Received',
                            'data' => $rowCollection->pluck('shipment_received')->map(fn ($v) => (int) $v)->all(),
                        ],
                        [
                            'name' => 'Outstanding',
                            'data' => $rowCollection->pluck('shipment_outstanding')->map(fn ($v) => (int) $v)->all(),
                        ],
                    ],
                ],
                'monthly_line' => [
                    'categories' => $monthlyActual['categories'],
                    'series' => [
                        [
                            'name' => 'Actual Received',
                            'data' => $monthlyActual['series'],
                        ],
                    ],
                ],
                'po_status' => [
                    'labels' => array_keys($poStatus),
                    'series' => array_values($poStatus),
                ],
                'shipment_donut' => [
                    'labels' => ['Received', 'Outstanding'],
                    'series' => [$shipmentReceivedTotal, $shipmentOutstandingTotal],
                ],
            ],
        ];
    }

    private function buildPoStatus(string $startDate, string $endDate): array
    {
        return PurchaseOrder::query()
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->toArray();
    }

    private function buildShipmentStatus(string $startDate, string $endDate): array
    {
        return Shipment::query()
            ->join('purchase_orders as po', 'shipments.purchase_order_id', '=', 'po.id')
            ->whereDate('po.order_date', '>=', $startDate)
            ->whereDate('po.order_date', '<=', $endDate)
            ->selectRaw('shipments.status, COUNT(*) as total')
            ->groupBy('shipments.status')
            ->pluck('total', 'shipments.status')
            ->map(fn ($value) => (int) $value)
            ->toArray();
    }

    private function buildMonthlyActual(string $startDate, string $endDate): array
    {
        $receipts = ShipmentReceipt::query()
            ->whereDate('receipt_date', '>=', $startDate)
            ->whereDate('receipt_date', '<=', $endDate)
            ->selectRaw("DATE_TRUNC('month', receipt_date) as period")
            ->selectRaw('SUM(quantity_received) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $categories = [];
        $series = [];

        foreach ($receipts as $row) {
            $date = Carbon::parse($row->period);
            $categories[] = $date->format('M Y');
            $series[] = (int) $row->total;
        }

        return [
            'categories' => $categories,
            'series' => $series,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildOutstandingShipments(Request $request): array
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfYear();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $shipments = Shipment::query()
            ->select([
                'shipments.id',
                'shipments.shipment_number',
                'shipments.quantity_planned',
                'shipments.quantity_received',
                'shipments.status',
                'shipments.ship_date',
                'shipments.eta_date',
                'po.po_number',
                'po.order_date',
                'products.code as product_code',
                'products.name as product_name',
            ])
            ->join('purchase_orders as po', 'shipments.purchase_order_id', '=', 'po.id')
            ->join('products', 'po.product_id', '=', 'products.id')
            ->whereDate('po.order_date', '>=', $start->toDateString())
            ->whereDate('po.order_date', '<=', $end->toDateString())
            ->orderByRaw('(COALESCE(shipments.quantity_planned,0) - COALESCE(shipments.quantity_received,0)) DESC')
            ->limit(10)
            ->get()
            ->map(function ($shipment) {
                $planned = (int) $shipment->quantity_planned;
                $received = (int) $shipment->quantity_received;
                $outstanding = max(0, $planned - $received);

                return [
                    'shipment_number' => $shipment->shipment_number,
                    'po_number' => $shipment->po_number,
                    'product_code' => $shipment->product_code,
                    'product_name' => $shipment->product_name,
                    'ship_date' => optional($shipment->ship_date)->format('d M Y'),
                    'eta_date' => optional($shipment->eta_date)->format('d M Y'),
                    'status' => $shipment->status,
                    'quantity_planned' => $planned,
                    'quantity_received' => $received,
                    'outstanding' => $outstanding,
                ];
            })
            ->filter(fn ($item) => $item['outstanding'] > 0)
            ->values()
            ->all();

        return $shipments;
    }
}
