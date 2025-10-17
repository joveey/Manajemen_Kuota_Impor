<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        // Read
        $this->middleware('permission:read purchase_orders')->only(['index', 'show', 'export']);
        // Delete
        $this->middleware('permission:delete purchase_orders')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $query = PurchaseOrder::query()
            ->with(['product', 'quota'])
            ->latest('order_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('period')) {
            $query->where('period', $request->string('period'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('po_number', 'like', $term)
                    ->orWhere('vendor_number', 'like', $term)
                    ->orWhere('vendor_name', 'like', $term)
                    ->orWhere('item_code', 'like', $term)
                    ->orWhere('item_description', 'like', $term);
            });
        }

        /** @var LengthAwarePaginator $purchaseOrders */
        $purchaseOrders = $query->paginate(20)->withQueryString();

        $stats = [
            'total_po' => PurchaseOrder::count(),
            'ordered' => PurchaseOrder::where('status', PurchaseOrder::STATUS_ORDERED)->count(),
            'in_transit' => PurchaseOrder::where('status', PurchaseOrder::STATUS_IN_TRANSIT)->count(),
            'completed' => PurchaseOrder::where('status', PurchaseOrder::STATUS_COMPLETED)->count(),
        ];

        return view('admin.purchase_order.index', compact('purchaseOrders', 'stats'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['product', 'quota', 'shipments.receipts']);

        return view('admin.purchase_order.show', compact('purchaseOrder'));
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        return redirect()->route('admin.purchase-orders.index')
            ->with('status', 'Purchase Order berhasil dihapus');
    }

    public function export(Request $request)
    {
        $query = PurchaseOrder::query()
            ->with(['product', 'quota'])
            ->latest('order_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('period')) {
            $query->where('period', $request->string('period'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('po_number', 'like', $term)
                    ->orWhere('vendor_number', 'like', $term)
                    ->orWhere('vendor_name', 'like', $term)
                    ->orWhere('item_code', 'like', $term)
                    ->orWhere('item_description', 'like', $term);
            });
        }

        $filename = 'purchase_orders_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'PO_DOC',
                'CREATED_DATE',
                'VENDOR_NO',
                'VENDOR_NAME',
                'LINE_NO',
                'ITEM_CODE',
                'ITEM_DESC',
                'WH_CODE',
                'WH_NAME',
                'WH_SOURCE',
                'SUBINV_CODE',
                'SUBINV_NAME',
                'SUBINV_SOURCE',
                'QTY',
                'AMOUNT',
                'CAT_PO',
                'CAT_DESC',
                'MAT_GRP',
                'SAP_STATUS',
            ]);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $po) {
                    fputcsv($out, [
                        $po->po_number,
                        optional($po->order_date)->format('Y-m-d'),
                        $po->vendor_number,
                        $po->vendor_name,
                        $po->line_number,
                        $po->item_code ?? $po->product?->code,
                        $po->item_description ?? $po->product?->name,
                        $po->warehouse_code,
                        $po->warehouse_name,
                        $po->warehouse_source,
                        $po->subinventory_code,
                        $po->subinventory_name,
                        $po->subinventory_source,
                        $po->quantity,
                        $po->amount,
                        $po->category_code,
                        $po->category,
                        $po->material_group,
                        $po->sap_order_status,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

}
