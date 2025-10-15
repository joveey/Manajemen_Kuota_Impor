<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service
    ) {
        // Read-only access
        $this->middleware('permission:read purchase_orders')->only(['index', 'export']);
        // Create shipment
        $this->middleware('permission:create purchase_orders')->only(['create', 'store']);
    }

    public function index(): View
    {
        $shipments = Shipment::query()
            ->with([
                'purchaseOrder.product',
                'statusLogs' => fn ($query) => $query->orderByDesc('recorded_at'),
            ])
            ->latest('ship_date')
            ->get();

        $shipments->each(function (Shipment $shipment) {
            $updated = $shipment->syncScheduledStatus('Status otomatis berdasarkan jadwal pengiriman.');
            if ($updated) {
                $shipment->load(['statusLogs' => fn ($query) => $query->orderByDesc('recorded_at')]);
            }
        });

        $summary = [
            'total' => $shipments->count(),
            'in_transit' => $shipments->whereIn('status', [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_PARTIAL])->count(),
            'delivered' => $shipments->where('status', Shipment::STATUS_DELIVERED)->count(),
            'quantity_total' => $shipments->sum('quantity_planned'),
        ];

        return view('admin.shipment.index', compact('shipments', 'summary'));
    }

    public function create(): View
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with('product')
            ->whereIn('status', [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_IN_TRANSIT,
                PurchaseOrder::STATUS_PARTIAL,
            ])
            ->orderBy('order_date', 'desc')
            ->get();

        return view('admin.shipment.create', compact('purchaseOrders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'quantity_planned' => ['required', 'integer', 'min:1'],
            'ship_date' => ['required', 'date'],
            'eta_date' => ['nullable', 'date', 'after_or_equal:ship_date'],
            'detail' => ['nullable', 'string'],
        ]);

        $purchaseOrder = PurchaseOrder::findOrFail($data['purchase_order_id']);

        if ($data['quantity_planned'] > $purchaseOrder->remaining_quantity) {
            return back()->withInput()->withErrors([
                'quantity_planned' => 'Qty pengiriman melebihi sisa kebutuhan PO.',
            ]);
        }

        $shipment = $this->service->registerShipment($purchaseOrder, $data);

        return redirect()
            ->route('admin.shipments.index')
            ->with('status', "Shipment {$shipment->shipment_number} berhasil dibuat");
    }

    public function export()
    {
        $query = Shipment::query()->with(['purchaseOrder.product']);
        $filename = 'shipments_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Shipment Number', 'PO Number', 'Product Code', 'Product Name', 'Qty Planned', 'Qty Received',
                'Ship Date', 'ETA', 'Receipt Date', 'Status',
            ]);
            $query->orderByDesc('ship_date')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [
                        $s->shipment_number,
                        $s->purchaseOrder?->po_number,
                        $s->purchaseOrder?->product?->code,
                        $s->purchaseOrder?->product?->name,
                        $s->quantity_planned,
                        $s->quantity_received,
                        optional($s->ship_date)->format('Y-m-d'),
                        optional($s->eta_date)->format('Y-m-d'),
                        optional($s->receipt_date)->format('Y-m-d'),
                        $s->status,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
