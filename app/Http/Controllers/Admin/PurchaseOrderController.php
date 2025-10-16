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

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'po_number' => ['required', 'string', 'max:100', 'unique:purchase_orders,po_number'],
            'created_date' => ['required', 'date'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'vendor_number' => ['nullable', 'string', 'max:50'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'line_number' => ['nullable', 'string', 'max:30'],
            'item_code' => ['nullable', 'string', 'max:100'],
            'item_description' => ['nullable', 'string'],
            'warehouse_code' => ['nullable', 'string', 'max:50'],
            'warehouse_name' => ['nullable', 'string', 'max:255'],
            'warehouse_source' => ['nullable', 'string', 'max:255'],
            'subinventory_code' => ['nullable', 'string', 'max:50'],
            'subinventory_name' => ['nullable', 'string', 'max:255'],
            'subinventory_source' => ['nullable', 'string', 'max:255'],
            'category_code' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:255'],
            'material_group' => ['nullable', 'string', 'max:100'],
            'sap_order_status' => ['nullable', 'string', 'max:100'],
        ]);

        $data['quantity'] = (int) $data['quantity'];
        if (array_key_exists('amount', $data) && $data['amount'] !== null) {
            $data['amount'] = (float) $data['amount'];
        }

        $data['order_date'] = Carbon::parse($data['created_date'])->toDateString();
        unset($data['created_date']);

        return $data;
    }
}
