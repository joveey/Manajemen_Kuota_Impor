<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        // Show published Open PO data (po_headers + po_lines) instead of legacy purchase_orders table
        $hasVendorNumber = Schema::hasColumn('po_headers', 'vendor_number');
        $hasWhCode = Schema::hasColumn('po_lines', 'warehouse_code');
        $hasWhName = Schema::hasColumn('po_lines', 'warehouse_name');
        $hasWhSource = Schema::hasColumn('po_lines', 'warehouse_source');
        $hasSubinvCode = Schema::hasColumn('po_lines', 'subinventory_code');
        $hasSubinvName = Schema::hasColumn('po_lines', 'subinventory_name');
        $hasSubinvSource = Schema::hasColumn('po_lines', 'subinventory_source');
        $hasAmount = Schema::hasColumn('po_lines', 'amount');
        $hasCatCode = Schema::hasColumn('po_lines', 'category_code');
        $hasCategory = Schema::hasColumn('po_lines', 'category');
        $hasMatGrp = Schema::hasColumn('po_lines', 'material_group');
        $hasSapStatus = Schema::hasColumn('po_lines', 'sap_order_status');

        $q = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->select(array_filter([
                DB::raw('ph.po_number as po_number'),
                DB::raw('ph.po_date as order_date'),
                DB::raw($hasVendorNumber ? "COALESCE(NULLIF(ph.vendor_number,''), split_part(ph.supplier, ' - ', 1)) as vendor_number" : 'NULL as vendor_number'),
                DB::raw('ph.supplier as vendor_name'),
                DB::raw("COALESCE(pl.line_no,'') as line_number"),
                DB::raw('pl.model_code as item_code'),
                DB::raw('pl.item_desc as item_description'),
                DB::raw($hasWhCode ? 'pl.warehouse_code' : 'NULL as warehouse_code'),
                DB::raw($hasWhName ? 'pl.warehouse_name' : 'NULL as warehouse_name'),
                DB::raw($hasWhSource ? 'pl.warehouse_source' : 'NULL as warehouse_source'),
                DB::raw($hasSubinvCode ? 'pl.subinventory_code' : 'NULL as subinventory_code'),
                DB::raw($hasSubinvName ? 'pl.subinventory_name' : 'NULL as subinventory_name'),
                DB::raw($hasSubinvSource ? 'pl.subinventory_source' : 'NULL as subinventory_source'),
                DB::raw('pl.qty_ordered as quantity'),
                DB::raw($hasAmount ? 'pl.amount as amount' : 'NULL as amount'),
                DB::raw("'ordered' as status"),
                DB::raw($hasCatCode ? 'pl.category_code' : 'NULL as category_code'),
                DB::raw($hasCategory ? 'pl.category' : 'NULL as category'),
                DB::raw($hasMatGrp ? 'pl.material_group' : 'NULL as material_group'),
                DB::raw($hasSapStatus ? 'pl.sap_order_status' : 'NULL as sap_order_status'),
            ]))
            ->orderByDesc('ph.po_date')
            ->orderBy('ph.po_number')
            ->orderBy('pl.line_no');

        // Filters
        if ($request->filled('period')) {
            $period = (string) $request->string('period'); // YYYY or YYYY-MM
            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                $q->whereRaw("to_char(ph.po_date, 'YYYY-MM') = ?", [$period]);
            } elseif (preg_match('/^\d{4}$/', $period)) {
                $q->whereRaw("to_char(ph.po_date, 'YYYY') = ?", [$period]);
            }
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $q->where(function ($w) use ($term) {
                $w->where('ph.po_number', 'like', $term)
                    ->orWhere('ph.supplier', 'like', $term)
                    ->orWhere('pl.model_code', 'like', $term)
                    ->orWhere('pl.item_desc', 'like', $term);
            });
        }

        $purchaseOrders = $q->paginate(20)->withQueryString();

        // Simple stats for header tiles
        $stats = [
            'total_po' => (int) DB::table('po_headers')->count(),
            'ordered' => 0,
            'in_transit' => 0,
            'completed' => 0,
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
        $query = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->select(array_filter([
                DB::raw('ph.po_number'), DB::raw('ph.po_date as order_date'),
                DB::raw($hasVendorNumber ? "COALESCE(NULLIF(ph.vendor_number,''), split_part(ph.supplier, ' - ', 1)) as vendor_number" : 'NULL as vendor_number'), DB::raw('ph.supplier as vendor_name'),
                DB::raw("COALESCE(pl.line_no,'') as line_number"),
                DB::raw('pl.model_code as item_code'), DB::raw('pl.item_desc as item_description'),
                DB::raw($hasWhCode ? 'pl.warehouse_code' : 'NULL as warehouse_code'),
                DB::raw($hasWhName ? 'pl.warehouse_name' : 'NULL as warehouse_name'),
                DB::raw($hasWhSource ? 'pl.warehouse_source' : 'NULL as warehouse_source'),
                DB::raw($hasSubinvCode ? 'pl.subinventory_code' : 'NULL as subinventory_code'),
                DB::raw($hasSubinvName ? 'pl.subinventory_name' : 'NULL as subinventory_name'),
                DB::raw($hasSubinvSource ? 'pl.subinventory_source' : 'NULL as subinventory_source'),
                DB::raw('pl.qty_ordered as quantity'), DB::raw($hasAmount ? 'pl.amount as amount' : 'NULL as amount'),
                DB::raw($hasCatCode ? 'pl.category_code' : 'NULL as category_code'),
                DB::raw($hasCategory ? 'pl.category' : 'NULL as category'),
                DB::raw($hasMatGrp ? 'pl.material_group' : 'NULL as material_group'),
                DB::raw($hasSapStatus ? 'pl.sap_order_status' : 'NULL as sap_order_status'),
            ]))
            ->orderByDesc('ph.po_date')->orderBy('ph.po_number')->orderBy('pl.line_no');

        if ($request->filled('period')) {
            $period = (string) $request->string('period');
            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                $query->whereRaw("to_char(ph.po_date, 'YYYY-MM') = ?", [$period]);
            } elseif (preg_match('/^\d{4}$/', $period)) {
                $query->whereRaw("to_char(ph.po_date, 'YYYY') = ?", [$period]);
            }
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($w) use ($term) {
                $w->where('ph.po_number', 'like', $term)
                    ->orWhere('ph.supplier', 'like', $term)
                    ->orWhere('pl.model_code', 'like', $term)
                    ->orWhere('pl.item_desc', 'like', $term);
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

    public function createManual(): View
    {
        return view('admin.purchase_order.create_manual');
    }

    public function storeManual(\App\Http\Requests\StoreManualPORequest $request): RedirectResponse
    {
        $data = $request->validated();
        $model = trim((string) $data['product_model']);

        $product = \App\Models\Product::query()
            ->whereRaw('LOWER(sap_model) = ?', [strtolower($model)])
            ->orWhereRaw('LOWER(code) = ?', [strtolower($model)])
            ->first();

        $createProduct = (bool)($data['create_product'] ?? true);

        if (!$product) {
            if (!$createProduct) {
                return back()->withErrors(['product_model' => 'Produk tidak ditemukan, dan opsi pembuatan produk dimatikan.'])->withInput();
            }

            // Buat produk minimal: code, name, sap_model diisi dengan model; kolom lain biarkan null/DEFAULT
            $product = \App\Models\Product::create([
                'code' => $model,
                'name' => $model,
                'sap_model' => $model,
                'is_active' => true,
            ]);
        }

        // Tentukan kuota yang akan dipakai
        $quota = null;
        $mappings = \App\Models\ProductQuotaMapping::with('quota')
            ->where('product_id', $product->id)
            ->orderBy('priority')
            ->get();

        foreach ($mappings as $map) {
            if ($map->quota && $map->quota->is_active && $map->quota->matchesProduct($product)) {
                $quota = $map->quota;
                break;
            }
        }

        if (!$quota) {
            $quota = \App\Models\Quota::query()
                ->active()
                ->orderBy('period_start')
                ->get()
                ->first(fn ($q) => $q->matchesProduct($product));
        }

        if (!$quota) {
            return back()->withErrors(['product_model' => 'Tidak ada Kuota aktif yang tersedia. Silakan impor/daftarkan kuota terlebih dahulu.'])->withInput();
        }

        $po = null;
        $allocated = false;
        $allocationNote = null;

        \Illuminate\Support\Facades\DB::transaction(function () use (&$po, &$allocated, &$allocationNote, $data, $product, $quota) {
            $amount = isset($data['unit_price']) ? ((float)$data['unit_price'] * (int)$data['quantity']) : 0;

            $po = \App\Models\PurchaseOrder::create([
                'po_number' => (string) $data['po_number'],
                'order_date' => $data['order_date'],
                'product_id' => $product->id,
                'quota_id' => $quota->id,
                'quantity' => (int) $data['quantity'],
                'amount' => $amount,
                'remarks' => $data['notes'] ?? null,
                'created_by' => \Illuminate\Support\Facades\Auth::id(),
                'status' => \App\Models\PurchaseOrder::STATUS_ORDERED,
                // Defaults to satisfy not-null columns on some schemas
                'plant_name' => 'Manual',
                'plant_detail' => 'Created via Manual PO form',
            ]);

            // Simple allocation logic (fallback when no dedicated service exists)
            if ($quota->forecast_remaining >= $po->quantity) {
                $quota->decrementForecast(
                    $po->quantity,
                    'Forecast allocated for PO '.$po->po_number,
                    $po,
                    $po->order_date,
                    \Illuminate\Support\Facades\Auth::id()
                );
                $allocated = true;
            } else {
                $allocated = false;
                $allocationNote = 'Forecast tersisa: '.number_format($quota->forecast_remaining).' < kebutuhan: '.number_format($po->quantity);
            }
        });

        if ($allocated) {
            return redirect()->route('admin.purchase-orders.show', $po)
                ->with('status', 'PO dibuat & kuota teralokasi/tercatat.');
        }

        return redirect()->route('admin.purchase-orders.show', $po)
            ->with('warning', 'PO dibuat, namun alokasi kuota perlu ditindaklanjuti. '.$allocationNote);
    }
}
