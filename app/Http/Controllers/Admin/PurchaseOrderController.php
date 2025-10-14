<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Services\Exceptions\InsufficientQuotaException;
use App\Services\PurchaseOrderService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly AuthFactory $auth
    ) {
        // Read
        $this->middleware('permission:read purchase_orders')->only(['index', 'show', 'export']);
        // Create
        $this->middleware('permission:create purchase_orders')->only(['create', 'store']);
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
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('pgi_branch', 'like', $term)
                    ->orWhere('pic_name', 'like', $term);
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

    public function create(): View
    {
        $products = Product::active()
            ->with(['quotaMappings.quota' => function ($query) {
                $query->orderBy('quota_number');
            }])
            ->orderBy('name')
            ->get();
        $quotas = Quota::active()->orderBy('quota_number')->get();

        return view('admin.purchase_order.create', compact('products', 'quotas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        try {
            $user = $this->auth->guard()->user();
            $po = $this->service->create($data, $user);
        } catch (InsufficientQuotaException $e) {
            return back()
                ->withInput()
                ->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-orders.show', $po)
            ->with('status', 'Purchase Order berhasil dibuat.');
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
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('pgi_branch', 'like', $term)
                    ->orWhere('pic_name', 'like', $term);
            });
        }

        $filename = 'purchase_orders_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Period', 'PO Number', 'Order Date', 'Status', 'Status Display', 'Product Code', 'Product Name',
                'Qty', 'Qty Shipped', 'Qty Received', 'Customer', 'PGI Branch', 'PIC', 'Truck', 'MOQ', 'Category',
                'Plant Name', 'Plant Detail', 'Quota Number', 'Remarks',
            ]);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $po) {
                    fputcsv($out, [
                        $po->period,
                        $po->po_number,
                        optional($po->order_date)->format('Y-m-d'),
                        $po->status,
                        $po->status_po_display,
                        $po->product?->code,
                        $po->product?->name,
                        $po->quantity,
                        $po->quantity_shipped,
                        $po->quantity_received,
                        $po->customer_name,
                        $po->pgi_branch,
                        $po->pic_name,
                        $po->truck,
                        $po->moq,
                        $po->category,
                        $po->plant_name,
                        $po->plant_detail,
                        $po->quota?->quota_number,
                        $po->remarks,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'sequence_number' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'string', 'max:7'],
            'po_number' => ['required', 'string', 'max:100', 'unique:purchase_orders,po_number'],
            'sap_reference' => ['nullable', 'string', 'max:100'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'order_date' => ['required', 'date'],
            'pgi_branch' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'status_po_display' => ['nullable', 'string', 'max:255'],
            'truck' => ['nullable', 'string', 'max:255'],
            'moq' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'plant_name' => ['required', 'string', 'max:255'],
            'plant_detail' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $data['quantity'] = (int) $data['quantity'];
        $data['period'] = $data['period'] ?? Carbon::parse($data['order_date'])->format('Y-m');

        return $data;
    }
}
