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
use App\Models\PoLine;

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
        $purchaseOrdersTable = $this->resolveTableName('purchase_orders');
        $poCols = $this->columnMap($purchaseOrdersTable);
        $poDocCol = $poCols['po_doc'] ?? ($poCols['po_number'] ?? null);
        $usePurchaseOrders = $purchaseOrdersTable && $poDocCol;
        if ($usePurchaseOrders) {
            try {
                $usePurchaseOrders = (bool) DB::table($purchaseOrdersTable)->limit(1)->exists();
            } catch (\Throwable $e) {
                $usePurchaseOrders = false;
            }
        }

        if ($usePurchaseOrders) {
            $quote = fn (string $name) => $this->quoteIdentifier($name);
            $poDateCol = $poCols['created_date'] ?? ($poCols['order_date'] ?? ($poCols['created_at'] ?? null));
            $poLineCol = $poCols['line_no'] ?? null;
            $poVendorNoCol = $poCols['vendor_no'] ?? ($poCols['vendor_number'] ?? null);
            $poVendorNameCol = $poCols['vendor_name'] ?? null;
            $poItemCodeCol = $poCols['item_code'] ?? ($poCols['model_code'] ?? null);
            $poItemDescCol = $poCols['item_desc'] ?? null;
            $poQtyCol = $poCols['qty'] ?? ($poCols['quantity'] ?? ($poCols['qty_ordered'] ?? null));
            $poQtyToInvoiceCol = $poCols['qty_to_invoice'] ?? null;
            $poQtyToDeliverCol = $poCols['qty_to_deliver'] ?? null;
            $poAmountCol = $poCols['amount'] ?? null;
            $poStorageCol = $poCols['storage_location'] ?? ($poCols['subinv_code'] ?? ($poCols['sloc_code'] ?? null));
            $poStorageNameCol = $poCols['subinv_name'] ?? ($poCols['sloc_name'] ?? null);
            $poSapStatusCol = $poCols['sap_order_status'] ?? null;
            $poEtaCol = $poCols['eta_date'] ?? ($poCols['delivery_date'] ?? null);

            $storageExpr = null;
            if ($poStorageCol && $poStorageNameCol) {
                $storageExpr = 'COALESCE(NULLIF('.$quote($poStorageCol).", ''), ".$quote($poStorageNameCol).') as storage_location';
            } elseif ($poStorageCol) {
                $storageExpr = $quote($poStorageCol).' as storage_location';
            } elseif ($poStorageNameCol) {
                $storageExpr = $quote($poStorageNameCol).' as storage_location';
            }

            $baseQuery = DB::table($purchaseOrdersTable)
                ->select(array_filter([
                    DB::raw($quote($poDocCol).' as po_doc'),
                    $poDateCol ? DB::raw($quote($poDateCol).' as po_date') : DB::raw('NULL as po_date'),
                    $poVendorNoCol ? DB::raw($quote($poVendorNoCol).' as vendor_number') : DB::raw('NULL as vendor_number'),
                    $poVendorNameCol ? DB::raw($quote($poVendorNameCol).' as vendor_name') : DB::raw('NULL as vendor_name'),
                    $poLineCol ? DB::raw($quote($poLineCol).' as line_no') : DB::raw('NULL as line_no'),
                    $poQtyCol ? DB::raw('COALESCE('.$quote($poQtyCol).',0) as qty_ordered') : DB::raw('0 as qty_ordered'),
                    $poQtyToInvoiceCol ? DB::raw('COALESCE('.$quote($poQtyToInvoiceCol).',0) as qty_to_invoice') : DB::raw('NULL as qty_to_invoice'),
                    $poQtyToDeliverCol ? DB::raw('COALESCE('.$quote($poQtyToDeliverCol).',0) as qty_to_deliver') : DB::raw('NULL as qty_to_deliver'),
                    $poAmountCol ? DB::raw('COALESCE('.$quote($poAmountCol).',0) as amount') : DB::raw('NULL as amount'),
                    $storageExpr ? DB::raw($storageExpr) : DB::raw('NULL as storage_location'),
                    $poSapStatusCol ? DB::raw($quote($poSapStatusCol).' as sap_order_status') : DB::raw('NULL as sap_order_status'),
                    $poEtaCol ? DB::raw($quote($poEtaCol).' as eta_date') : DB::raw('NULL as eta_date'),
                ]));

            if ($request->filled('period') && $poDateCol) {
                $period = (string) $request->string('period');
                if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                    $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                    $end   = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
                    $baseQuery->whereBetween($poDateCol, [$start->toDateString(), $end->toDateString()]);
                } elseif (preg_match('/^\d{4}$/', $period)) {
                    $start = Carbon::createFromDate((int) $period, 1, 1)->startOfYear();
                    $end   = Carbon::createFromDate((int) $period, 12, 31)->endOfYear();
                    $baseQuery->whereBetween($poDateCol, [$start->toDateString(), $end->toDateString()]);
                }
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $baseQuery->where(function ($w) use ($term, $poDocCol, $poVendorNameCol, $poVendorNoCol, $poItemCodeCol, $poItemDescCol) {
                    $hasCondition = false;
                    if ($poDocCol) {
                        $w->orWhere($poDocCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poVendorNameCol) {
                        $w->orWhere($poVendorNameCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poVendorNoCol) {
                        $w->orWhere($poVendorNoCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poItemCodeCol) {
                        $w->orWhere($poItemCodeCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poItemDescCol) {
                        $w->orWhere($poItemDescCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if (!$hasCondition) {
                        $w->whereRaw('1 = 0');
                    }
                });
            }

            $orderCol = $poDateCol ?: $poDocCol;
            $rows = $baseQuery
                ->orderByDesc($orderCol)
                ->orderBy($poDocCol)
                ->get();

            if ($rows->isEmpty()) {
                $purchaseOrders = new LengthAwarePaginator([], 0, 20, 1, [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]);

                $stats = [
                    'total_po'   => 0,
                    'ordered'    => 0,
                    'in_transit' => 0,
                    'completed'  => 0,
                ];

                return view('admin.purchase_order.index', compact('purchaseOrders', 'stats'));
            }

            $grTable = $this->resolveTableName('gr_receipts');
            $grCols = $this->columnMap($grTable);
            $grPoCol = $grCols['po_no'] ?? null;
            $grQtyCol = $grCols['qty'] ?? null;

            $poNumbers = $rows->pluck('po_doc')->filter()->unique()->values();
            $grSums = collect();
            if ($grTable && $grPoCol && $grQtyCol && $poNumbers->isNotEmpty()) {
                $grSums = DB::table($grTable)
                    ->select([
                        DB::raw($quote($grPoCol).' as po_no'),
                        DB::raw('SUM('.$quote($grQtyCol).') as qty'),
                    ])
                    ->whereIn($grPoCol, $poNumbers)
                    ->groupBy(DB::raw($quote($grPoCol)))
                    ->pluck('qty', 'po_no');
            }

            $hasQtyToInvoice = $poQtyToInvoiceCol !== null;
            $hasQtyToDeliver = $poQtyToDeliverCol !== null;
            $hasAmount = $poAmountCol !== null;
            $hasStorage = $storageExpr !== null;
            $hasSapStatus = $poSapStatusCol !== null;
            $hasLineNo = $poLineCol !== null;

            $grouped = $rows->groupBy('po_doc')->map(function ($group) use ($grSums, $hasAmount, $hasQtyToInvoice, $hasQtyToDeliver, $hasStorage, $hasSapStatus, $hasLineNo) {
                $poNumber = (string) $group->first()->po_doc;

                $firstOrderDate  = $group->min('po_date');
                $latestOrderDate = $group->max('po_date');
                $firstDelivDate  = $group->min('eta_date');
                $latestDelivDate = $group->max('eta_date');

                $vendorNames = $group->pluck('vendor_name')->filter()->unique()->values();
                $vendorNumbers = $group->pluck('vendor_number')->filter()->unique()->values();

                $totalQtyOrdered = $group->sum(fn ($row) => (float) ($row->qty_ordered ?? 0));
                $totalQtyReceived = (float) ($grSums[$poNumber] ?? 0.0);
                $totalQtyOutstanding = max($totalQtyOrdered - $totalQtyReceived, 0.0);

                $totalQtyToInvoice = $hasQtyToInvoice
                    ? $group->sum(fn ($row) => (float) ($row->qty_to_invoice ?? 0))
                    : null;
                $totalQtyToDeliver = $hasQtyToDeliver
                    ? $group->sum(fn ($row) => (float) ($row->qty_to_deliver ?? 0))
                    : null;

                $storageLocations = $hasStorage
                    ? $group->pluck('storage_location')->filter(fn ($value) => $value !== null && $value !== '')->unique()->implode(', ')
                    : null;

                $sapStatuses = $hasSapStatus
                    ? $group->pluck('sap_order_status')->filter()->unique()->implode(', ')
                    : null;

                $totalAmount = $hasAmount
                    ? $group->sum(fn ($row) => (float) ($row->amount ?? 0))
                    : null;

                $lineCount = $hasLineNo
                    ? $group->pluck('line_no')->filter(fn ($value) => $value !== null && $value !== '')->unique()->count()
                    : $group->count();

                $statusKey = PurchaseOrder::STATUS_ORDERED;
                if ($totalQtyOrdered > 0) {
                    if ($totalQtyReceived >= $totalQtyOrdered) {
                        $statusKey = PurchaseOrder::STATUS_COMPLETED;
                    } elseif ($totalQtyReceived > 0) {
                        $statusKey = PurchaseOrder::STATUS_PARTIAL;
                    }
                }

                return (object) [
                    'po_number'            => $poNumber,
                    'first_order_date'     => $firstOrderDate,
                    'latest_order_date'    => $latestOrderDate,
                    'first_deliv_date'     => $firstDelivDate,
                    'latest_deliv_date'    => $latestDelivDate,
                    'vendor_number'        => $vendorNumbers->implode(', '),
                    'vendor_name'          => $vendorNames->implode(', '),
                    'vendor_factories'     => null,
                    'header_count'         => 1,
                    'total_lines'          => $lineCount,
                    'total_qty_ordered'    => $totalQtyOrdered,
                    'total_qty_received'   => $totalQtyReceived,
                    'total_qty_outstanding'=> $totalQtyOutstanding,
                    'total_qty_to_invoice' => $totalQtyToInvoice,
                    'total_qty_to_deliver' => $totalQtyToDeliver,
                    'storage_locations'    => $storageLocations,
                    'total_amount'         => $totalAmount,
                    'sap_statuses'         => $sapStatuses,
                    'status_key'           => $statusKey,
                ];
            });

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
                    $grouped = $grouped->filter(fn ($row) => $row->status_key === $statusFilter);
                }
            }

            $sorted = $grouped->sortBy([
                ['latest_order_date', 'desc'],
                ['po_number', 'asc'],
            ])->values();

            $perPage     = 20;
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $total       = $sorted->count();
            $items       = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $purchaseOrders = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );

            $stats = [
                'total_po'   => $total,
                'ordered'    => $sorted->where('status_key', PurchaseOrder::STATUS_ORDERED)->count(),
                'in_transit' => $sorted->where('status_key', PurchaseOrder::STATUS_PARTIAL)->count(),
                'completed'  => $sorted->where('status_key', PurchaseOrder::STATUS_COMPLETED)->count(),
            ];

            return view('admin.purchase_order.index', compact('purchaseOrders', 'stats'));
        }

        $hasVendorNumber = Schema::hasColumn('po_headers', 'vendor_number');
        $hasAmount = Schema::hasColumn('po_lines', 'amount');
        $hasQtyOrdered = Schema::hasColumn('po_lines', 'qty_ordered');
        $hasSapStatus = Schema::hasColumn('po_lines', 'sap_order_status');
        $hasQtyToInvoice = Schema::hasColumn('po_lines', 'qty_to_invoice');
        $hasQtyToDeliver = Schema::hasColumn('po_lines', 'qty_to_deliver');
        $hasStorageLocation = Schema::hasColumn('po_lines', 'storage_location');

        $baseQuery = DB::table('po_headers as ph')
            ->leftJoin('po_lines as pl', 'pl.po_header_id', '=', 'ph.id')
            ->select([
                'ph.id as header_id',
                'ph.po_number',
                'ph.po_date',
                'ph.supplier',
                $hasVendorNumber ? 'ph.vendor_number' : DB::raw('NULL as vendor_number'),
                'pl.id as line_id',
                'pl.eta_date',
                'pl.voyage_factory',
                $hasStorageLocation ? 'pl.storage_location' : DB::raw('NULL as storage_location'),
                $hasSapStatus ? 'pl.sap_order_status' : DB::raw('NULL as sap_order_status'),
                $hasQtyOrdered ? 'pl.qty_ordered' : DB::raw('0 as qty_ordered'),
                $hasQtyToInvoice ? 'pl.qty_to_invoice' : DB::raw('NULL as qty_to_invoice'),
                $hasQtyToDeliver ? 'pl.qty_to_deliver' : DB::raw('NULL as qty_to_deliver'),
                $hasAmount ? 'pl.amount' : DB::raw('NULL as amount'),
            ]);

        if ($request->filled('period')) {
            $period = (string) $request->string('period');
            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                $end   = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
                $baseQuery->whereBetween('ph.po_date', [$start->toDateString(), $end->toDateString()]);
            } elseif (preg_match('/^\d{4}$/', $period)) {
                $start = Carbon::createFromDate((int) $period, 1, 1)->startOfYear();
                $end   = Carbon::createFromDate((int) $period, 12, 31)->endOfYear();
                $baseQuery->whereBetween('ph.po_date', [$start->toDateString(), $end->toDateString()]);
            }
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $baseQuery->where(function ($w) use ($term) {
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

        // Pull raw rows and aggregate per PO number in PHP to keep the query portable
        $rows = $baseQuery
            ->orderByDesc('ph.po_date')
            ->orderBy('ph.po_number')
            ->get();

        if ($rows->isEmpty()) {
            $purchaseOrders = new LengthAwarePaginator([], 0, 20, 1, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);

            $stats = [
                'total_po'   => 0,
                'ordered'    => 0,
                'in_transit' => 0,
                'completed'  => 0,
            ];

            return view('admin.purchase_order.index', compact('purchaseOrders', 'stats'));
        }

        // Use GR receipts as the single source of truth for received quantity per PO
        $poNumbers = $rows->pluck('po_number')->filter()->unique()->values();
        $grSums = DB::table('gr_receipts')
            ->select('po_no', DB::raw('SUM(qty) as qty'))
            ->whereIn('po_no', $poNumbers)
            ->groupBy('po_no')
            ->pluck('qty', 'po_no');

        $grouped = $rows->groupBy('po_number')->map(function ($group) use ($grSums, $hasAmount, $hasQtyToInvoice, $hasQtyToDeliver, $hasStorageLocation, $hasSapStatus) {
            $poNumber = (string) $group->first()->po_number;

            $firstOrderDate  = $group->min('po_date');
            $latestOrderDate = $group->max('po_date');
            $firstDelivDate  = $group->min('eta_date');
            $latestDelivDate = $group->max('eta_date');

            $vendorNames = $group->pluck('supplier')->filter()->unique()->values();
            $vendorFactories = $group->pluck('voyage_factory')->filter()->unique()->values();
            $vendorNumbers = $group->pluck('vendor_number')->filter()->unique()->values();

            $totalQtyOrdered = $group->sum(fn ($row) => (float) ($row->qty_ordered ?? 0));
            $totalQtyReceived = (float) ($grSums[$poNumber] ?? 0.0);
            $totalQtyOutstanding = max($totalQtyOrdered - $totalQtyReceived, 0.0);

            $totalQtyToInvoice = $hasQtyToInvoice
                ? $group->sum(fn ($row) => (float) ($row->qty_to_invoice ?? 0))
                : null;
            $totalQtyToDeliver = $hasQtyToDeliver
                ? $group->sum(fn ($row) => (float) ($row->qty_to_deliver ?? 0))
                : null;

            $storageLocations = $hasStorageLocation
                ? $group->pluck('storage_location')->filter()->unique()->implode(', ')
                : null;

            $sapStatuses = $hasSapStatus
                ? $group->pluck('sap_order_status')->filter()->unique()->implode(', ')
                : null;

            $totalAmount = $hasAmount
                ? $group->sum(fn ($row) => (float) ($row->amount ?? 0))
                : null;

            // Status: completed if fully received, partial if some received, otherwise ordered
            $statusKey = PurchaseOrder::STATUS_ORDERED;
            if ($totalQtyOrdered > 0) {
                if ($totalQtyReceived >= $totalQtyOrdered) {
                    $statusKey = PurchaseOrder::STATUS_COMPLETED;
                } elseif ($totalQtyReceived > 0) {
                    $statusKey = PurchaseOrder::STATUS_PARTIAL;
                }
            }

            return (object) [
                'po_number'            => $poNumber,
                'first_order_date'     => $firstOrderDate,
                'latest_order_date'    => $latestOrderDate,
                'first_deliv_date'     => $firstDelivDate,
                'latest_deliv_date'    => $latestDelivDate,
                'vendor_number'        => $vendorNumbers->implode(', '),
                'vendor_name'          => $vendorNames->implode(', '),
                'vendor_factories'     => $vendorFactories->implode(', '),
                'header_count'         => $group->pluck('header_id')->unique()->count(),
                'total_lines'          => $group->pluck('line_id')->filter()->count(),
                'total_qty_ordered'    => $totalQtyOrdered,
                'total_qty_received'   => $totalQtyReceived,
                'total_qty_outstanding'=> $totalQtyOutstanding,
                'total_qty_to_invoice' => $totalQtyToInvoice,
                'total_qty_to_deliver' => $totalQtyToDeliver,
                'storage_locations'    => $storageLocations,
                'total_amount'         => $totalAmount,
                'sap_statuses'         => $sapStatuses,
                'status_key'           => $statusKey,
            ];
        });

        // Optional status filtering on the aggregated collection
        if ($request->filled('status')) {
            $statusFilter = (string) $request->string('status');
            if ($statusFilter === PurchaseOrder::STATUS_IN_TRANSIT) {
                // Keep behaviour: treat "in_transit" filter as "partial"
                $statusFilter = PurchaseOrder::STATUS_PARTIAL;
            }

            if (in_array($statusFilter, [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_PARTIAL,
                PurchaseOrder::STATUS_COMPLETED,
            ], true)) {
                $grouped = $grouped->filter(fn ($row) => $row->status_key === $statusFilter);
            }
        }

        // Sort by latest_order_date desc, then po_number
        $sorted = $grouped->sortBy([
            ['latest_order_date', 'desc'],
            ['po_number', 'asc'],
        ])->values();

        $perPage     = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $total       = $sorted->count();
        $items       = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $purchaseOrders = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        $stats = [
            'total_po'   => $total,
            'ordered'    => $sorted->where('status_key', PurchaseOrder::STATUS_ORDERED)->count(),
            'in_transit' => $sorted->where('status_key', PurchaseOrder::STATUS_PARTIAL)->count(),
            'completed'  => $sorted->where('status_key', PurchaseOrder::STATUS_COMPLETED)->count(),
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

        $purchaseOrdersTable = $this->resolveTableName('purchase_orders');
        $poCols = $this->columnMap($purchaseOrdersTable);
        $poDocCol = $poCols['po_doc'] ?? ($poCols['po_number'] ?? null);
        $usePurchaseOrders = $purchaseOrdersTable && $poDocCol;
        if ($usePurchaseOrders) {
            try {
                $usePurchaseOrders = (bool) DB::table($purchaseOrdersTable)
                    ->where($poDocCol, $poNumber)
                    ->limit(1)
                    ->exists();
            } catch (\Throwable $e) {
                $usePurchaseOrders = false;
            }
        }

        if ($usePurchaseOrders) {
            $quote = fn (string $name) => $this->quoteIdentifier($name);
            $poDateCol = $poCols['created_date'] ?? ($poCols['order_date'] ?? ($poCols['created_at'] ?? null));
            $poLineCol = $poCols['line_no'] ?? null;
            $poVendorNoCol = $poCols['vendor_no'] ?? ($poCols['vendor_number'] ?? null);
            $poVendorNameCol = $poCols['vendor_name'] ?? null;
            $poItemCodeCol = $poCols['item_code'] ?? ($poCols['model_code'] ?? null);
            $poItemDescCol = $poCols['item_desc'] ?? null;
            $poQtyCol = $poCols['qty'] ?? ($poCols['quantity'] ?? ($poCols['qty_ordered'] ?? null));
            $poQtyToInvoiceCol = $poCols['qty_to_invoice'] ?? null;
            $poQtyToDeliverCol = $poCols['qty_to_deliver'] ?? null;
            $poAmountCol = $poCols['amount'] ?? null;
            $poStorageCol = $poCols['storage_location'] ?? ($poCols['subinv_code'] ?? ($poCols['sloc_code'] ?? null));
            $poStorageNameCol = $poCols['subinv_name'] ?? ($poCols['sloc_name'] ?? null);
            $poEtaCol = $poCols['eta_date'] ?? ($poCols['delivery_date'] ?? null);

            $storageExpr = null;
            if ($poStorageCol && $poStorageNameCol) {
                $storageExpr = 'COALESCE(NULLIF('.$quote($poStorageCol).", ''), ".$quote($poStorageNameCol).') as storage_location';
            } elseif ($poStorageCol) {
                $storageExpr = $quote($poStorageCol).' as storage_location';
            } elseif ($poStorageNameCol) {
                $storageExpr = $quote($poStorageNameCol).' as storage_location';
            }

            $orderCol = $poDateCol ?: $poDocCol;
            $rows = DB::table($purchaseOrdersTable)
                ->select(array_filter([
                    DB::raw($quote($poDocCol).' as po_number'),
                    $poDateCol ? DB::raw($quote($poDateCol).' as order_date') : DB::raw('NULL as order_date'),
                    $poEtaCol ? DB::raw($quote($poEtaCol).' as deliv_date') : DB::raw('NULL as deliv_date'),
                    $poVendorNoCol ? DB::raw($quote($poVendorNoCol).' as vendor_number') : DB::raw('NULL as vendor_number'),
                    $poVendorNameCol ? DB::raw($quote($poVendorNameCol).' as vendor_name') : DB::raw('NULL as vendor_name'),
                    $poLineCol ? DB::raw($quote($poLineCol).' as line_number') : DB::raw('NULL as line_number'),
                    $poItemCodeCol ? DB::raw($quote($poItemCodeCol).' as item_code') : DB::raw('NULL as item_code'),
                    $poItemDescCol ? DB::raw($quote($poItemDescCol).' as item_description') : DB::raw('NULL as item_description'),
                    $storageExpr ? DB::raw($storageExpr) : DB::raw('NULL as storage_location'),
                    $poQtyCol ? DB::raw('COALESCE('.$quote($poQtyCol).',0) as quantity') : DB::raw('0 as quantity'),
                    $poQtyToInvoiceCol ? DB::raw('COALESCE('.$quote($poQtyToInvoiceCol).',0) as qty_to_invoice') : DB::raw('NULL as qty_to_invoice'),
                    $poQtyToDeliverCol ? DB::raw('COALESCE('.$quote($poQtyToDeliverCol).',0) as qty_to_deliver') : DB::raw('NULL as qty_to_deliver'),
                    $poAmountCol ? DB::raw('COALESCE('.$quote($poAmountCol).',0) as amount') : DB::raw('NULL as amount'),
                ]))
                ->where($poDocCol, $poNumber)
                ->orderByDesc($orderCol)
                ->when($poLineCol, fn ($q) => $q->orderBy($poLineCol))
                ->get();

            if ($rows->isEmpty()) {
                abort(404);
            }

            $lines = $rows->map(function ($line) use ($resolveVendorNumber) {
                try {
                    $line->display_order_date = !empty($line->order_date) ? Carbon::parse($line->order_date) : null;
                } catch (\Throwable $th) {
                    $line->display_order_date = null;
                }
                $line->vendor_number = $resolveVendorNumber($line->vendor_number ?? null, $line->vendor_name ?? null);
                return $line;
            });

            $headers = collect([
                (object) [
                    'po_number' => $poNumber,
                    'po_date' => $rows->max('order_date'),
                    'supplier' => $rows->pluck('vendor_name')->filter()->unique()->implode(', '),
                    'vendor_number' => $rows->pluck('vendor_number')->filter()->unique()->implode(', '),
                ],
            ])->map(function ($header) use ($resolveVendorNumber) {
                try {
                    $displayDate = !empty($header->po_date) ? Carbon::parse($header->po_date) : null;
                } catch (\Throwable $th) {
                    $displayDate = null;
                }

                $header->display_vendor_number = $resolveVendorNumber($header->vendor_number ?? null, $header->supplier ?? null);
                $header->display_date = $displayDate;
                return $header;
            });

            $totals = [
                'quantity' => (float) $lines->sum(fn ($line) => (float) ($line->quantity ?? 0)),
                'amount' => $poAmountCol ? (float) $lines->sum(fn ($line) => (float) ($line->amount ?? 0)) : null,
                'count' => $lines->count(),
            ];

            $dateRange = null;
            $dates = $lines->pluck('display_order_date')->filter();
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

            $primaryVendorName = $lines->pluck('vendor_name')->filter()->unique()->implode(', ');
            $primaryVendorNumber = $lines->pluck('vendor_number')->filter()->unique()->implode(', ');
            $internalPO = PurchaseOrder::with(['product'])->where('po_doc', $poNumber)->first();

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

        $hasHeaderVendorNumber = Schema::hasColumn('po_headers', 'vendor_number');

        $headerSelect = [
            'id', 'po_number', 'po_date', 'supplier',
        ];
        if ($hasHeaderVendorNumber) {
            $headerSelect[] = 'vendor_number';
        }

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
                DB::raw('pl.id as id'),
                DB::raw('ph.id as header_id'),
                DB::raw('ph.po_number'),
                DB::raw('ph.po_date as order_date'),
                DB::raw('pl.eta_date as deliv_date'),
                DB::raw($hasHeaderVendorNumber ? "NULLIF(ph.vendor_number,'') as vendor_number" : 'NULL as vendor_number'),
                DB::raw('ph.supplier as vendor_name'),
                DB::raw("NULLIF(pl.voyage_factory,'') as voyage_factory"),
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
            // Append factory name to vendor for this line if voyage factory is provided manually
            $vf = is_string($line->voyage_factory ?? null) ? trim($line->voyage_factory) : '';
            if ($vf !== '') {
                $vn = is_string($line->vendor_name ?? null) ? trim($line->vendor_name) : '';
                $line->vendor_name = $vn !== '' ? ($vn.' - '.$vf) : $vf;
            }
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
        $internalPO = PurchaseOrder::with(['product'])->where('po_doc', $poNumber)->first();

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
        $purchaseOrdersTable = $this->resolveTableName('purchase_orders');
        $poCols = $this->columnMap($purchaseOrdersTable);
        $poDocCol = $poCols['po_doc'] ?? ($poCols['po_number'] ?? null);
        $usePurchaseOrders = $purchaseOrdersTable && $poDocCol;
        if ($usePurchaseOrders) {
            try {
                $usePurchaseOrders = (bool) DB::table($purchaseOrdersTable)->limit(1)->exists();
            } catch (\Throwable $e) {
                $usePurchaseOrders = false;
            }
        }

        if ($usePurchaseOrders) {
            $quote = fn (string $name) => $this->quoteIdentifier($name);
            $poDateCol = $poCols['created_date'] ?? ($poCols['order_date'] ?? ($poCols['created_at'] ?? null));
            $poVendorNoCol = $poCols['vendor_no'] ?? ($poCols['vendor_number'] ?? null);
            $poVendorNameCol = $poCols['vendor_name'] ?? null;
            $poLineCol = $poCols['line_no'] ?? null;
            $poItemCodeCol = $poCols['item_code'] ?? ($poCols['model_code'] ?? null);
            $poItemDescCol = $poCols['item_desc'] ?? null;
            $poWhCodeCol = $poCols['wh_code'] ?? ($poCols['warehouse_code'] ?? null);
            $poWhNameCol = $poCols['wh_name'] ?? ($poCols['warehouse_name'] ?? null);
            $poWhSourceCol = $poCols['wh_source'] ?? ($poCols['warehouse_source'] ?? null);
            $poSubinvCodeCol = $poCols['subinv_code'] ?? ($poCols['subinventory_code'] ?? null);
            $poSubinvNameCol = $poCols['subinv_name'] ?? ($poCols['subinventory_name'] ?? null);
            $poSubinvSourceCol = $poCols['subinv_source'] ?? ($poCols['subinventory_source'] ?? null);
            $poQtyCol = $poCols['qty'] ?? ($poCols['quantity'] ?? ($poCols['qty_ordered'] ?? null));
            $poAmountCol = $poCols['amount'] ?? null;
            $poCatCodeCol = $poCols['cat_po'] ?? ($poCols['category_code'] ?? null);
            $poCatDescCol = $poCols['cat_desc'] ?? ($poCols['category'] ?? null);
            $poMatGrpCol = $poCols['mat_grp'] ?? ($poCols['material_group'] ?? null);
            $poSapStatusCol = $poCols['sap_order_status'] ?? null;

            $query = DB::table($purchaseOrdersTable)
                ->select(array_filter([
                    DB::raw($quote($poDocCol).' as po_number'),
                    $poDateCol ? DB::raw($quote($poDateCol).' as order_date') : DB::raw('NULL as order_date'),
                    $poVendorNoCol ? DB::raw($quote($poVendorNoCol).' as vendor_number') : DB::raw('NULL as vendor_number'),
                    $poVendorNameCol ? DB::raw($quote($poVendorNameCol).' as vendor_name') : DB::raw('NULL as vendor_name'),
                    $poLineCol ? DB::raw($quote($poLineCol).' as line_number') : DB::raw('NULL as line_number'),
                    $poItemCodeCol ? DB::raw($quote($poItemCodeCol).' as item_code') : DB::raw('NULL as item_code'),
                    $poItemDescCol ? DB::raw($quote($poItemDescCol).' as item_description') : DB::raw('NULL as item_description'),
                    $poWhCodeCol ? DB::raw($quote($poWhCodeCol).' as warehouse_code') : DB::raw('NULL as warehouse_code'),
                    $poWhNameCol ? DB::raw($quote($poWhNameCol).' as warehouse_name') : DB::raw('NULL as warehouse_name'),
                    $poWhSourceCol ? DB::raw($quote($poWhSourceCol).' as warehouse_source') : DB::raw('NULL as warehouse_source'),
                    $poSubinvCodeCol ? DB::raw($quote($poSubinvCodeCol).' as subinventory_code') : DB::raw('NULL as subinventory_code'),
                    $poSubinvNameCol ? DB::raw($quote($poSubinvNameCol).' as subinventory_name') : DB::raw('NULL as subinventory_name'),
                    $poSubinvSourceCol ? DB::raw($quote($poSubinvSourceCol).' as subinventory_source') : DB::raw('NULL as subinventory_source'),
                    $poQtyCol ? DB::raw('COALESCE('.$quote($poQtyCol).',0) as quantity') : DB::raw('0 as quantity'),
                    $poAmountCol ? DB::raw('COALESCE('.$quote($poAmountCol).',0) as amount') : DB::raw('NULL as amount'),
                    $poCatCodeCol ? DB::raw($quote($poCatCodeCol).' as category_code') : DB::raw('NULL as category_code'),
                    $poCatDescCol ? DB::raw($quote($poCatDescCol).' as category') : DB::raw('NULL as category'),
                    $poMatGrpCol ? DB::raw($quote($poMatGrpCol).' as material_group') : DB::raw('NULL as material_group'),
                    $poSapStatusCol ? DB::raw($quote($poSapStatusCol).' as sap_order_status') : DB::raw('NULL as sap_order_status'),
                ]));

            if ($request->filled('period') && $poDateCol) {
                $period = (string) $request->string('period');
                if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                    $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                    $end   = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
                    $query->whereBetween($poDateCol, [$start->toDateString(), $end->toDateString()]);
                } elseif (preg_match('/^\d{4}$/', $period)) {
                    $start = Carbon::createFromDate((int) $period, 1, 1)->startOfYear();
                    $end   = Carbon::createFromDate((int) $period, 12, 31)->endOfYear();
                    $query->whereBetween($poDateCol, [$start->toDateString(), $end->toDateString()]);
                }
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($w) use ($term, $poDocCol, $poVendorNameCol, $poVendorNoCol, $poItemCodeCol, $poItemDescCol) {
                    $hasCondition = false;
                    if ($poDocCol) {
                        $w->orWhere($poDocCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poVendorNameCol) {
                        $w->orWhere($poVendorNameCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poVendorNoCol) {
                        $w->orWhere($poVendorNoCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poItemCodeCol) {
                        $w->orWhere($poItemCodeCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if ($poItemDescCol) {
                        $w->orWhere($poItemDescCol, 'like', $term);
                        $hasCondition = true;
                    }
                    if (!$hasCondition) {
                        $w->whereRaw('1 = 0');
                    }
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
                        $dateValue = null;
                        if (!empty($po->order_date)) {
                            try {
                                $dateValue = Carbon::parse($po->order_date)->format('Y-m-d');
                            } catch (\Throwable $e) {
                                $dateValue = (string) $po->order_date;
                            }
                        }
                        fputcsv($out, [
                            $po->po_number,
                            $dateValue,
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

    public function updateVoyage(Request $request, PoLine $line): RedirectResponse
    {
        $data = $request->validate([
            'voyage_bl' => ['nullable','string','max:100'],
            'voyage_etd' => ['nullable','date'],
            'voyage_eta' => ['nullable','date'],
            'voyage_factory' => ['nullable','string','max:100'],
            'voyage_status' => ['nullable','string','max:50'],
            'voyage_issue_date' => ['nullable','date'],
            'voyage_expired_date' => ['nullable','date'],
            'voyage_remark' => ['nullable','string'],
        ]);

        $line->update($data);

        return back()->with('status', 'Voyage info saved.');
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
                return back()->withErrors(['product_model' => 'Product was not found and product creation is disabled.'])->withInput();
            }

            // Create minimal product: code, name, and sap_model filled with the model; leave other columns null/DEFAULT
            $product = \App\Models\Product::create([
                'code' => $model,
                'name' => $model,
                'sap_model' => $model,
                'is_active' => true,
            ]);
        }

        // Determine which quota to use
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
                $allocationNote = 'Partially unallocated: '.number_format($left).' units. Please import quota for the next period.';
            }
        });

        $redir = redirect()->route('admin.purchase-orders.show', $po)
            ->with('status', 'PO created and forecast allocation processed.');
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
            return back()->withErrors(['target_quota_id' => 'Target quota must be different from the source quota.']);
        }

        // Validate target matches product
        if (!$target->matchesProduct($po->product)) {
            return back()->withErrors(['target_quota_id' => 'Target quota does not match the product/PK.']);
        }

        // Validate ETA within target period if provided
        if (!empty($data['eta_date'])) {
            $eta = \Illuminate\Support\Carbon::parse($data['eta_date'])->toDateString();
            if (!($target->period_start && $target->period_end && $target->period_start->toDateString() <= $eta && $target->period_end->toDateString() >= $eta)) {
                return back()->withErrors(['eta_date' => 'The new ETA does not fall within the target quota period.']);
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
            return back()->withErrors(['move_qty' => 'The quantity to move is not valid.']);
        }

        $available = (int) ($target->forecast_remaining ?? 0);
        $move = min($requested, $available);

        if ($move <= 0) {
            return back()
                ->with('warning', 'The target quota has no remaining capacity. Please create a new quota for the relevant period.')
                ->withInput();
        }

        DB::transaction(function () use ($po, $source, $target, $pivot, $move, $data) {
            $occurredOn = !empty($data['eta_date']) ? new \DateTimeImmutable($data['eta_date']) : null;
            $userId = Auth::id();

            // 1) Free forecast from source
            $source->incrementForecast(
                (int) $move,
                sprintf('Forecast reallocation: return %s units from PO %s', number_format($move), $po->po_number),
                $po,
                $occurredOn,
                $userId
            );

            // 2) Reserve forecast on target
            $target->decrementForecast(
                (int) $move,
                sprintf('Forecast reallocation: move %s units for PO %s', number_format($move), $po->po_number),
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

        $msg = sprintf('Successfully moved %s units from quota %s to quota %s.', number_format($move), $source->quota_number, $target->quota_number);
        $redir = redirect()->route('admin.purchase-orders.show', $po)->with('status', $msg);

        if ($move < $requested) {
            $redir->with('warning', sprintf('Limited capacity: only %s of %s were moved. Remaining %s units are not yet allocated in the new period. Please create a new quota.', number_format($move), number_format($requested), number_format($requested - $move)));
        }

        return $redir;
    }

    private function resolveTableName(string $table): ?string
    {
        if (Schema::hasTable($table)) {
            return $table;
        }

        try {
            $row = DB::selectOne("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE LOWER(TABLE_NAME) = LOWER(?)", [$table]);
            if ($row) {
                return $row->TABLE_NAME ?? $row->table_name ?? $table;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function columnMap(?string $table): array
    {
        if (!$table) {
            return [];
        }

        try {
            $cols = Schema::getColumnListing($table);
        } catch (\Throwable $e) {
            $cols = [];
        }

        $map = [];
        foreach ($cols as $col) {
            $map[strtolower($col)] = $col;
        }
        return $map;
    }

    private function quoteIdentifier(string $name): string
    {
        return DB::connection()->getDriverName() === 'sqlsrv'
            ? '['.$name.']'
            : '"'.$name.'"';
    }
}
