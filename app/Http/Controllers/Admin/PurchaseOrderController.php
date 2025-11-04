<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\Quota;

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
        $hasVendorNumber = Schema::hasColumn('po_headers', 'vendor_number');
        $hasAmount = Schema::hasColumn('po_lines', 'amount');
        $hasQtyOrdered = Schema::hasColumn('po_lines', 'qty_ordered');
        $hasQtyReceived = Schema::hasColumn('po_lines', 'qty_received');
        $hasSapStatus = Schema::hasColumn('po_lines', 'sap_order_status');
        $hasQtyToInvoice = Schema::hasColumn('po_lines', 'qty_to_invoice');
        $hasQtyToDeliver = Schema::hasColumn('po_lines', 'qty_to_deliver');
        $hasStorageLocation = Schema::hasColumn('po_lines', 'storage_location');

        $qtyOrderedExprBase = $hasQtyOrdered ? 'COALESCE(pl.qty_ordered,0)' : '0';
        $qtyReceivedExprBase = $hasQtyReceived ? 'COALESCE(pl.qty_received,0)' : '0';

        $sumQtyOrderedExpr = "SUM($qtyOrderedExprBase)";
        $sumQtyReceivedExpr = "SUM($qtyReceivedExprBase)";
        $sumQtyToInvoiceExpr = $hasQtyToInvoice ? 'SUM(COALESCE(pl.qty_to_invoice,0))' : 'NULL';
        $sumQtyToDeliverExpr = $hasQtyToDeliver ? 'SUM(COALESCE(pl.qty_to_deliver,0))' : 'NULL';
        $storagesExpr = $hasStorageLocation ? "STRING_AGG(DISTINCT NULLIF(pl.storage_location,''), ', ')" : 'NULL';
        $sumOutstandingExpr = $hasQtyReceived
            ? "SUM(GREATEST($qtyOrderedExprBase - $qtyReceivedExprBase,0))"
            : '0';

        $statusExpr = $hasQtyReceived
            ? sprintf(
                "CASE WHEN %s >= %s AND %s > 0 THEN '%s' WHEN %s > 0 THEN '%s' ELSE '%s' END",
                $sumQtyReceivedExpr,
                $sumQtyOrderedExpr,
                $sumQtyOrderedExpr,
                PurchaseOrder::STATUS_COMPLETED,
                $sumQtyReceivedExpr,
                PurchaseOrder::STATUS_PARTIAL,
                PurchaseOrder::STATUS_ORDERED
            )
            : sprintf("'%s'", PurchaseOrder::STATUS_ORDERED);

        $select = [
            DB::raw('ph.po_number as po_number'),
            DB::raw('MIN(ph.po_date) as first_order_date'),
            DB::raw('MAX(ph.po_date) as latest_order_date'),
            DB::raw('MIN(pl.eta_date) as first_deliv_date'),
            DB::raw('MAX(pl.eta_date) as latest_deliv_date'),
            DB::raw($hasVendorNumber ? "STRING_AGG(DISTINCT NULLIF(ph.vendor_number,''), ', ') as vendor_number" : 'NULL as vendor_number'),
            DB::raw("STRING_AGG(DISTINCT ph.supplier, ', ') as vendor_name"),
            DB::raw('COUNT(DISTINCT ph.id) as header_count'),
            DB::raw('COUNT(pl.id) as total_lines'),
            DB::raw("$sumQtyOrderedExpr as total_qty_ordered"),
            DB::raw("$sumQtyReceivedExpr as total_qty_received"),
            DB::raw("$sumOutstandingExpr as total_qty_outstanding"),
            DB::raw("$sumQtyToInvoiceExpr as total_qty_to_invoice"),
            DB::raw("$sumQtyToDeliverExpr as total_qty_to_deliver"),
            DB::raw("$storagesExpr as storage_locations"),
            DB::raw("$statusExpr as status_key"),
        ];

        if ($hasAmount) {
            $select[] = DB::raw('SUM(COALESCE(pl.amount,0)) as total_amount');
        } else {
            $select[] = DB::raw('NULL as total_amount');
        }

        if ($hasSapStatus) {
            $select[] = DB::raw("STRING_AGG(DISTINCT NULLIF(pl.sap_order_status,''), ', ') as sap_statuses");
        } else {
            $select[] = DB::raw("NULL as sap_statuses");
        }

        $query = DB::table('po_headers as ph')
            ->leftJoin('po_lines as pl', 'pl.po_header_id', '=', 'ph.id')
            ->select($select)
            ->groupBy('ph.po_number');

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
                    ->orWhereExists(function ($sub) use ($term) {
                        $sub->select(DB::raw('1'))
                            ->from('po_lines as l')
                            ->whereColumn('l.po_header_id', 'ph.id')
                            ->where(function ($s) use ($term) {
                                $s->where('l.model_code', 'like', $term)
                                    ->orWhere('l.item_desc', 'like', $term);
                            });
                    });
            });
        }

        if ($request->filled('status')) {
            $statusFilter = (string) $request->string('status');
            if ($statusFilter === PurchaseOrder::STATUS_IN_TRANSIT) {
                $statusFilter = PurchaseOrder::STATUS_PARTIAL;
            }

            if (in_array($statusFilter, [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_PARTIAL,
                PurchaseOrder::STATUS_COMPLETED,
            ], true)) {
                $query->havingRaw("$statusExpr = ?", [$statusFilter]);
            }
        }

        $statsQuery = clone $query;

        $query->orderByDesc('latest_order_date')->orderBy('po_number');

        $purchaseOrders = $query->paginate(20)->withQueryString();

        $statusCountsRow = DB::query()
            ->fromSub($statsQuery, 'agg')
            ->selectRaw(
                "SUM(CASE WHEN status_key = ? THEN 1 ELSE 0 END) AS ordered,
                 SUM(CASE WHEN status_key = ? THEN 1 ELSE 0 END) AS partial,
                 SUM(CASE WHEN status_key = ? THEN 1 ELSE 0 END) AS completed",
                [
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIAL,
                    PurchaseOrder::STATUS_COMPLETED,
                ]
            )
            ->first();

        $stats = [
            'total_po' => (int) DB::table('po_headers')->distinct('po_number')->count('po_number'),
            'ordered' => (int) ($statusCountsRow->ordered ?? 0),
            'in_transit' => (int) ($statusCountsRow->partial ?? 0),
            'completed' => (int) ($statusCountsRow->completed ?? 0),
        ];

        return view('admin.purchase_order.index', compact('purchaseOrders', 'stats'));
    }

    public function show(PurchaseOrder $purchaseOrder): \Illuminate\Http\RedirectResponse
    {
        // Unify detail page to document view using PO number
        return redirect()->route('admin.purchase-orders.document', ['poNumber' => $purchaseOrder->po_number]);
    }

        public function showDocument(string $poNumber): View
    {
        $poNumber = trim($poNumber);
        if ($poNumber === '') {
            abort(404);
        }

        $hasHeaderVendorNumber = Schema::hasColumn('po_headers', 'vendor_number');

        $headerSelect = [
            'id', 'po_number', 'po_date', 'supplier',
        ];
        if ($hasHeaderVendorNumber) {
            $headerSelect[] = 'vendor_number';
        }

        $resolveVendorNumber = static function (?string $explicit, ?string $supplier): ?string {
            $explicit = is_string($explicit) ? trim($explicit) : '';
            if ($explicit !== '') {
                return $explicit;
            }
            if (!is_string($supplier)) {
                return null;
            }
            $supplier = trim($supplier);
            if ($supplier === '') {
                return null;
            }
            $parts = preg_split('/\s*-\s*/', $supplier, 2);
            if (!empty($parts)) {
                $candidate = trim((string) $parts[0]);
                if ($candidate !== '' && preg_match('/\d/', $candidate) && strlen($candidate) <= 32) {
                    return $candidate;
                }
            }
            if (preg_match('/\b([0-9]{4,})\b/', $supplier, $matches)) {
                return $matches[1];
            }
            return null;
        };

        $headers = DB::table('po_headers')
            ->select($headerSelect)
            ->where('po_number', $poNumber)
            ->orderBy('po_date')
            ->get();

        if ($headers->isEmpty()) {
            abort(404);
        }

        $headers = $headers->map(function ($header) use ($resolveVendorNumber) {
            try {
                $displayDate = !empty($header->po_date) ? Carbon::parse($header->po_date) : null;
            } catch (\Throwable $th) {
                $displayDate = null;
            }

            $header->display_vendor_number = $resolveVendorNumber($header->vendor_number ?? null, $header->supplier ?? null);
            $header->display_date = $displayDate;
            return $header;
        });

        // Optional po_lines columns
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
        $hasQtyToInvoice = Schema::hasColumn('po_lines', 'qty_to_invoice');
        $hasQtyToDeliver = Schema::hasColumn('po_lines', 'qty_to_deliver');
        $hasStorageLocation = Schema::hasColumn('po_lines', 'storage_location');

        $lineQuery = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->select([
                DB::raw('pl.id as line_id'),
                DB::raw('ph.po_number'),
                DB::raw('ph.po_date as order_date'),
                DB::raw('pl.eta_date as deliv_date'),
                DB::raw($hasHeaderVendorNumber ? "NULLIF(ph.vendor_number,'') as vendor_number" : 'NULL as vendor_number'),
                DB::raw('ph.supplier as vendor_name'),
                DB::raw("COALESCE(pl.line_no,'') as line_number"),
                DB::raw('pl.model_code as item_code'),
                DB::raw('pl.item_desc as item_description'),
                DB::raw($hasStorageLocation ? 'pl.storage_location' : 'NULL as storage_location'),
                $hasWhCode ? DB::raw('pl.warehouse_code') : DB::raw('NULL as warehouse_code'),
                $hasWhName ? DB::raw('pl.warehouse_name') : DB::raw('NULL as warehouse_name'),
                $hasWhSource ? DB::raw('pl.warehouse_source') : DB::raw('NULL as warehouse_source'),
                $hasSubinvCode ? DB::raw('pl.subinventory_code') : DB::raw('NULL as subinventory_code'),
                $hasSubinvName ? DB::raw('pl.subinventory_name') : DB::raw('NULL as subinventory_name'),
                $hasSubinvSource ? DB::raw('pl.subinventory_source') : DB::raw('NULL as subinventory_source'),
                DB::raw('pl.qty_ordered as quantity'),
                $hasQtyToInvoice ? DB::raw('pl.qty_to_invoice') : DB::raw('NULL as qty_to_invoice'),
                $hasQtyToDeliver ? DB::raw('pl.qty_to_deliver') : DB::raw('NULL as qty_to_deliver'),
                $hasAmount ? DB::raw('pl.amount as amount') : DB::raw('NULL as amount'),
                $hasCatCode ? DB::raw('pl.category_code') : DB::raw('NULL as category_code'),
                $hasCategory ? DB::raw('pl.category') : DB::raw('NULL as category'),
                $hasMatGrp ? DB::raw('pl.material_group') : DB::raw('NULL as material_group'),
                $hasSapStatus ? DB::raw('pl.sap_order_status') : DB::raw('NULL as sap_order_status'),
            ])
            ->where('ph.po_number', $poNumber)
            ->orderByDesc('ph.po_date')
            ->orderBy('pl.line_no');

        $lines = $lineQuery->get()->map(function ($line) use ($resolveVendorNumber) {
            try {
                $line->display_order_date = !empty($line->order_date) ? Carbon::parse($line->order_date) : null;
            } catch (\Throwable $th) {
                $line->display_order_date = null;
            }
            $line->vendor_number = $resolveVendorNumber($line->vendor_number ?? null, $line->vendor_name ?? null);
            return $line;
        });

        $totals = [
            'quantity' => (float) $lines->sum(fn ($line) => (float) ($line->quantity ?? 0)),
            'amount' => $hasAmount ? (float) $lines->sum(fn ($line) => (float) ($line->amount ?? 0)) : null,
            'count' => $lines->count(),
        ];

        $dateRange = null;
        $dates = $headers->pluck('display_date')->filter();
        if ($dates->isNotEmpty()) {
            /** @var \Illuminate\Support\Carbon|null $first */
            $first = $dates->min();
            /** @var \Illuminate\Support\Carbon|null $last */
            $last = $dates->max();
            if ($first && $last) {
                $dateRange = $first->equalTo($last)
                    ? $first->format('d M Y')
                    : $first->format('d M Y').' - '.$last->format('d M Y');
            }
        }

        $primaryVendorName = $headers->pluck('supplier')->filter()->unique()->implode(', ');
        $primaryVendorNumber = $headers->pluck('display_vendor_number')->filter()->unique()->implode(', ');
        $internalPO = PurchaseOrder::with(['product'])->where('po_number', $poNumber)->first();

        return view('admin.purchase_order.document', [
            'poNumber' => $poNumber,
            'headers' => $headers,
            'lines' => $lines,
            'totals' => $totals,
            'dateRange' => $dateRange,
            'primaryVendorName' => $primaryVendorName,
            'primaryVendorNumber' => $primaryVendorNumber,
            'internalPO' => $internalPO,
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        return redirect()->route('admin.purchase-orders.index')
            ->with('status', 'Purchase Order has been deleted successfully.');
    }

        public function export(Request $request)
    {
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
        $hasQtyToInvoice = Schema::hasColumn('po_lines', 'qty_to_invoice');
        $hasQtyToDeliver = Schema::hasColumn('po_lines', 'qty_to_deliver');
        $hasStorageLocation = Schema::hasColumn('po_lines', 'storage_location');

        $query = DB::table('po_lines as pl')
            ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
            ->select(array_filter([
                DB::raw('ph.po_number'), DB::raw('ph.po_date as order_date'),
                DB::raw($hasVendorNumber ? "COALESCE(NULLIF(ph.vendor_number,''), split_part(ph.supplier, ' - ', 1)) as vendor_number" : 'NULL as vendor_number'), DB::raw('ph.supplier as vendor_name'),
                DB::raw("COALESCE(pl.line_no,'') as line_number"),
                DB::raw('pl.model_code as item_code'), DB::raw('pl.item_desc as item_description'),
                $hasStorageLocation ? DB::raw('pl.storage_location') : null,
                $hasWhCode ? DB::raw('pl.warehouse_code') : null,
                $hasWhName ? DB::raw('pl.warehouse_name') : null,
                $hasWhSource ? DB::raw('pl.warehouse_source') : null,
                $hasSubinvCode ? DB::raw('pl.subinventory_code') : null,
                $hasSubinvName ? DB::raw('pl.subinventory_name') : null,
                $hasSubinvSource ? DB::raw('pl.subinventory_source') : null,
                DB::raw('pl.qty_ordered as quantity'),
                $hasQtyToInvoice ? DB::raw('pl.qty_to_invoice') : null,
                $hasQtyToDeliver ? DB::raw('pl.qty_to_deliver') : null,
                $hasAmount ? DB::raw('pl.amount as amount') : DB::raw('NULL as amount'),
                $hasCatCode ? DB::raw('pl.category_code') : null,
                $hasCategory ? DB::raw('pl.category') : null,
                $hasMatGrp ? DB::raw('pl.material_group') : null,
                $hasSapStatus ? DB::raw('pl.sap_order_status') : null,
            ]))
            ->orderByDesc('ph.po_date')->orderBy('ph.po_number')->orderBy('pl.line_no');

        if ($request->filled('period')) {
            $period = (string) $request->string('period');
            if (preg_match('/^\\d{4}-\\d{2}$/', $period)) {
                $query->whereRaw("to_char(ph.po_date, 'YYYY-MM') = ?", [$period]);
            } elseif (preg_match('/^\\d{4}$/', $period)) {
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
                'PO_DOC','CREATED_DATE','VENDOR_NO','VENDOR_NAME','LINE_NO','ITEM_CODE','ITEM_DESC',
                'WH_CODE','WH_NAME','WH_SOURCE','SUBINV_CODE','SUBINV_NAME','SUBINV_SOURCE','QTY','AMOUNT','CAT_PO','CAT_DESC','MAT_GRP','SAP_STATUS'
            ]);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $po) {
                    fputcsv($out, [
                        $po->po_number,
                        optional($po->order_date)->format('Y-m-d'),
                        $po->vendor_number,
                        $po->vendor_name,
                        $po->line_number,
                        $po->item_code,
                        $po->item_description,
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
        }, $filename, ['Content-Type' => 'text/csv']);
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
            return back()->withErrors(['product_model' => 'No active quota is available. Please import/register a quota first.'])->withInput();
        }

        $po = null;
        $allocationNote = null;

        \Illuminate\Support\Facades\DB::transaction(function () use (&$po, &$allocationNote, $data, $product, $quota) {
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

            // Allocate across periods using service
            [$allocs, $left] = app(\App\Services\QuotaAllocationService::class)
                ->allocateForecast($product->id, (int) $po->quantity, $po->order_date, $po);

            if ($left > 0) {
                $allocationNote = 'Sebagian belum teralokasi: '.number_format($left).' unit. Mohon impor kuota periode berikutnya.';
            }
        });

        $redir = redirect()->route('admin.purchase-orders.show', $po)
            ->with('status', 'PO dibuat & alokasi forecast diproses.');
        if ($allocationNote) { $redir->with('warning', $allocationNote); }
        return $redir;
    }

    public function reallocateQuota(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_quota_id' => ['required', 'integer', 'exists:quotas,id'],
            'target_quota_id' => ['required', 'integer', 'exists:quotas,id'],
            'move_qty' => ['nullable', 'integer', 'min:1'],
            'eta_date' => ['nullable', 'date'],
        ]);

        $po = $purchaseOrder->load(['product']);
        $source = Quota::lockForUpdate()->findOrFail($data['source_quota_id']);
        $target = Quota::lockForUpdate()->findOrFail($data['target_quota_id']);

        if ($source->id === $target->id) {
            return back()->withErrors(['target_quota_id' => 'Kuota tujuan harus berbeda dari kuota asal.']);
        }

        // Validate target matches product
        if (!$target->matchesProduct($po->product)) {
            return back()->withErrors(['target_quota_id' => 'Kuota tujuan tidak sesuai produk/PK.']);
        }

        // Validate ETA within target period if provided
        if (!empty($data['eta_date'])) {
            $eta = \Illuminate\Support\Carbon::parse($data['eta_date'])->toDateString();
            if (!($target->period_start && $target->period_end && $target->period_start->toDateString() <= $eta && $target->period_end->toDateString() >= $eta)) {
                return back()->withErrors(['eta_date' => 'Tanggal ETA baru tidak berada dalam periode kuota tujuan.']);
            }
        }

        // Find existing allocated qty on source pivot
        $pivot = DB::table('purchase_order_quota')
            ->where('purchase_order_id', $po->id)
            ->where('quota_id', $source->id)
            ->first();

        if (!$pivot || (int) $pivot->allocated_qty <= 0) {
            return back()->withErrors(['source_quota_id' => 'No allocation was found on the source quota for this PO.']);
        }

        $requested = isset($data['move_qty']) ? (int) $data['move_qty'] : (int) $pivot->allocated_qty;
        $requested = max(0, min($requested, (int) $pivot->allocated_qty));
        if ($requested <= 0) {
            return back()->withErrors(['move_qty' => 'Jumlah yang dipindahkan tidak valid.']);
        }

        $available = (int) ($target->forecast_remaining ?? 0);
        $move = min($requested, $available);

        if ($move <= 0) {
            return back()
                ->with('warning', 'Kuota tujuan tidak memiliki sisa kapasitas. Mohon buat kuota baru untuk periode terkait.')
                ->withInput();
        }

        DB::transaction(function () use ($po, $source, $target, $pivot, $move, $data) {
            $occurredOn = !empty($data['eta_date']) ? new \DateTimeImmutable($data['eta_date']) : null;
            $userId = Auth::id();

            // 1) Free forecast from source
            $source->incrementForecast(
                (int) $move,
                sprintf('Realokasi forecast: kembalikan %s unit dari PO %s', number_format($move), $po->po_number),
                $po,
                $occurredOn,
                $userId
            );

            // 2) Reserve forecast on target
            $target->decrementForecast(
                (int) $move,
                sprintf('Realokasi forecast: pindahkan %s unit untuk PO %s', number_format($move), $po->po_number),
                $po,
                $occurredOn,
                $userId
            );

            // 3) Update pivot allocations
            $remainingOnSource = max(0, (int) $pivot->allocated_qty - (int) $move);
            if ($remainingOnSource > 0) {
                DB::table('purchase_order_quota')
                    ->where('id', $pivot->id)
                    ->update(['allocated_qty' => $remainingOnSource, 'updated_at' => now()]);
            } else {
                DB::table('purchase_order_quota')->where('id', $pivot->id)->delete();
            }

            $existingTarget = DB::table('purchase_order_quota')
                ->where('purchase_order_id', $po->id)
                ->where('quota_id', $target->id)
                ->first();

            if ($existingTarget) {
                DB::table('purchase_order_quota')
                    ->where('id', $existingTarget->id)
                    ->update(['allocated_qty' => (int)$existingTarget->allocated_qty + (int)$move, 'updated_at' => now()]);
            } else {
                DB::table('purchase_order_quota')->insert([
                    'purchase_order_id' => $po->id,
                    'quota_id' => $target->id,
                    'allocated_qty' => (int) $move,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $msg = sprintf('Berhasil memindahkan %s unit dari kuota %s ke kuota %s.', number_format($move), $source->quota_number, $target->quota_number);
        $redir = redirect()->route('admin.purchase-orders.show', $po)->with('status', $msg);

        if ($move < $requested) {
            $redir->with('warning', sprintf('Kapasitas terbatas: hanya %s dari %s yang dipindahkan. Sisa %s unit belum teralokasi di periode baru. Mohon buat kuota baru.', number_format($move), number_format($requested), number_format($requested - $move)));
        }

        return $redir;
    }
}





