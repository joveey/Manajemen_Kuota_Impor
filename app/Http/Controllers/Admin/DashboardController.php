<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;
use App\Models\Quota;
use App\Models\Role;
use App\Models\PoHeader;
use App\Models\PoLine;
use App\Models\GrReceipt;
use App\Models\User;
use Carbon\Carbon;
use App\Support\DbExpression;

class DashboardController extends Controller
{
    public function index()
    {
        $driver = DB::connection()->getDriverName();
        $hasImportCreatedAt = Schema::hasColumn('imports', 'created_at');
        $hasPoCreatedAt = Schema::hasColumn('po_headers', 'created_at');
        $resolveTableName = function (string $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
            try {
                $row = DB::selectOne("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE LOWER(TABLE_NAME) = LOWER(?)", [$table]);
                if ($row) {
                    return $row->TABLE_NAME ?? $row->table_name ?? $table;
                }
            } catch (\Throwable $e) {}
            return null;
        };
        $purchaseOrdersTable = $resolveTableName('purchase_orders');
        $grReceiptsTable = $resolveTableName('gr_receipts');
        $quote = function (string $name) use ($driver) {
            return $driver === 'sqlsrv' ? '['.$name.']' : '"'.$name.'"';
        };
        $columnMap = function (?string $table) use ($quote) {
            if (!$table) { return []; }
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
        };
        $poCols = $columnMap($purchaseOrdersTable);
        $grCols = $columnMap($grReceiptsTable);

        $purchaseOrderDocCol = $poCols['po_doc'] ?? ($poCols['po_number'] ?? null);
        $purchaseOrderQtyCol = $poCols['qty'] ?? ($poCols['quantity'] ?? null);
        $purchaseOrderDateCol = $poCols['created_date'] ?? ($poCols['order_date'] ?? ($poCols['created_at'] ?? null));
        $purchaseOrderQtyReceivedCol = $poCols['quantity_received'] ?? null;
        $purchaseOrderVendorCol = $poCols['vendor_name'] ?? null;
        $purchaseOrderSapStatusCol = $poCols['sap_order_status'] ?? null;
        $purchaseOrderLineCol = $poCols['line_no'] ?? null;
        $hasPurchaseOrderDoc = $purchaseOrderDocCol !== null && $purchaseOrdersTable !== null;

        $grIdCol = $grCols['id'] ?? null;
        $grPoCol = $grCols['po_no'] ?? null;
        $grLineCol = $grCols['line_no'] ?? null;
        $grReceiveCol = $grCols['receive_date'] ?? null;
        $grQtyCol = $grCols['qty'] ?? null;
        $grItemCol = $grCols['item_name'] ?? null;
        $grCatDescCol = $grCols['cat_desc'] ?? null;
        $grCatPoDescCol = $grCols['cat_po_desc'] ?? null;
        $grCatPoCol = $grCols['cat_po'] ?? null;
        $grMatDocCol = $grCols['mat_doc'] ?? null;
        $grVendorCol = $grCols['vendor_name'] ?? null;
        $grWhNameCol = $grCols['wh_name'] ?? null;
        $hasGrId = $grIdCol !== null;

        $poHeaderCount = 0;
        $poLineCount = 0;
        try { $poHeaderCount = PoHeader::count(); } catch (\Throwable $e) {}
        try { $poLineCount = PoLine::count(); } catch (\Throwable $e) {}
        $purchaseOrdersHasRows = false;
        if ($purchaseOrdersTable) {
            try {
                $purchaseOrdersHasRows = (bool) DB::table($purchaseOrdersTable)->limit(1)->exists();
            } catch (\Throwable $e) {}
        }
        $usePurchaseOrders = $hasPurchaseOrderDoc && $purchaseOrdersHasRows;
        $quotaUseTrashed = false;
        $quotaTotal = 0;
        try { $quotaTotal = Quota::count(); } catch (\Throwable $e) {}
        if ($quotaTotal === 0) {
            try {
                $withTrashedTotal = Quota::withTrashed()->count();
                if ($withTrashedTotal > 0) {
                    $quotaUseTrashed = true;
                    $quotaTotal = $withTrashedTotal;
                }
            } catch (\Throwable $e) {}
        }
        $quotaQuery = fn () => $quotaUseTrashed ? Quota::withTrashed() : Quota::query();

        // Pipeline & KPI calculations (lightweight, no mapping changes)
        $metrics = [];
        try {
            // Products mapping coverage (using HS→PK resolver for current year)
            $periodKey = now()->format('Y');
            $resolver = app(\App\Services\HsCodeResolver::class);

            $mapped = 0; $unmapped = 0;
            $rows = \App\Models\Product::query()->get(['id','hs_code','pk_capacity','code','name','sap_model']);
            foreach ($rows as $p) {
                $hs = $p->hs_code ?? null;
                if ($hs === null || $hs === '') { $unmapped++; continue; }
                $pk = $resolver->resolveForProduct($p, $periodKey);
                if ($pk === null) { $unmapped++; } else { $mapped++; }
            }
            $metrics['mapped'] = $mapped;
            $metrics['unmapped'] = $unmapped;
        } catch (\Throwable $e) {
            $metrics['mapped'] = $metrics['unmapped'] = 0; // tolerate if table pruned
        }

        // Global aggregates for Quota Pipeline (mapped HS only, exclude ACC)
        $totalPo = 0.0; $totalGr = 0.0; $totalAlloc = 0.0;
        $usePoLinesForQuota = false;
        try {
            $usePoLinesForQuota = Schema::hasTable('po_lines')
                && Schema::hasTable('po_headers')
                && Schema::hasTable('hs_code_pk_mappings')
                && (bool) DB::table('po_lines')->limit(1)->exists();
        } catch (\Throwable $e) {}

        if ($usePoLinesForQuota) {
            try {
                // Base: PO lines joined with headers and HS mapping
                $totalPo = (float) DB::table('po_lines as pl')
                    ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                    ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->whereNotNull('pl.hs_code_id')
                    ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                    ->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                $metrics['open_po_qty'] = $totalPo; // as per new rule: show total PO
            } catch (\Throwable $e) { $metrics['open_po_qty'] = 0; }

            try {
                // Compute total GR (normalized by PO+line) with the same HS filters
                $grn = DB::table('gr_receipts')
                    ->selectRaw("po_no, ".DbExpression::lineNoInt('line_no')." AS ln")
                    ->selectRaw('SUM(qty) as qty')
                    ->groupBy('po_no', DB::raw(DbExpression::lineNoInt('line_no')));
                $totalGr = (float) DB::table('po_lines as pl')
                    ->join('po_headers as ph','pl.po_header_id','=','ph.id')
                    ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->leftJoinSub($grn, 'grn', function($j){
                        $j->on('grn.po_no','=','ph.po_number')
                          ->whereRaw("grn.ln = ".DbExpression::lineNoInt('pl.line_no'));
                    })
                    ->whereNotNull('pl.hs_code_id')
                    ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                    ->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');
                $metrics['gr_qty'] = $totalGr; // total GR all time under same filters
            } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }
        } else {
            try {
                $metrics['open_po_qty'] = ($usePurchaseOrders && $purchaseOrdersTable && $purchaseOrderQtyCol)
                    ? (float) DB::table($purchaseOrdersTable)->sum($purchaseOrderQtyCol)
                    : 0;
            } catch (\Throwable $e) { $metrics['open_po_qty'] = 0; }

            try {
                $metrics['gr_qty'] = ($grReceiptsTable && $grQtyCol)
                    ? (float) DB::table($grReceiptsTable)->sum($grQtyCol)
                    : 0;
            } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }
        }

        // In-Transit = total_po - total_gr (clamped >= 0)
        $metrics['in_transit_qty'] = max(($metrics['open_po_qty'] ?? 0) - ($metrics['gr_qty'] ?? 0), 0);

        try {
            // GR qty (last 30 days)
            $since = now()->subDays(30)->toDateString();
            if ($grReceiptsTable && $grReceiveCol && $grQtyCol) {
                $metrics['gr_qty'] = (float) DB::table($grReceiptsTable)
                    ->where($grReceiveCol, '>=', $since)
                    ->sum($grQtyCol);
            } else {
                $metrics['gr_qty'] = 0;
            }
        } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }

        // Derived per-quota KPI (quota_id-based, clamped to allocation)
        $quotaCards = collect();
        $quotaBatches = [];
        $totalForecastConsumed = 0.0;
        $totalActualConsumed = 0.0;
        try {
            // Show all quotas (not limited by current period/year)
            $quotas = $quotaQuery()
                ->orderBy('quota_number')
                ->get();

            $ids = $quotas->pluck('id')->filter()->values()->all();
            $forecastByQuota = collect();
            $actualByQuota = collect();

            if (!empty($ids) && Schema::hasTable('purchase_order_quota')) {
                $forecastByQuota = DB::table('purchase_order_quota')
                    ->select('quota_id', DB::raw('SUM(allocated_qty) as qty'))
                    ->whereIn('quota_id', $ids)
                    ->groupBy('quota_id')
                    ->pluck('qty', 'quota_id');
            }
            if (!empty($ids) && Schema::hasTable('quota_histories')) {
                $actualByQuota = DB::table('quota_histories')
                    ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                    ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
                    ->whereIn('quota_id', $ids)
                    ->groupBy('quota_id')
                    ->pluck('qty', 'quota_id');
            }

            foreach ($quotas as $q) {
                $alloc = (float) ($q->total_allocation ?? 0);

                // Forecast: allocation already reserved to this quota (base PO allocation + net moves)
                $forecastConsumed = $forecastByQuota->has($q->id)
                    ? min($alloc, (float) $forecastByQuota[$q->id])
                    : max(0.0, $alloc - min((float) ($q->forecast_remaining ?? $alloc), $alloc));
                $q->setAttribute('forecast_consumed', $forecastConsumed);

                $actualConsumed = $actualByQuota->has($q->id)
                    ? min($alloc, (float) $actualByQuota[$q->id])
                    : max(0.0, $alloc - min((float) ($q->actual_remaining ?? $alloc), $alloc));
                $q->setAttribute('actual_consumed', $actualConsumed);
                $q->setAttribute('forecast_remaining', max($alloc - $forecastConsumed, 0));
                $q->setAttribute('actual_remaining', max($alloc - $actualConsumed, 0));

                // In-Transit = forecast reserved minus actual received (never negative)
                $q->setAttribute('in_transit', max($forecastConsumed - $actualConsumed, 0));

                $totalForecastConsumed += $forecastConsumed;
                $totalActualConsumed += $actualConsumed;

                $statusLabel = match (strtolower((string) ($q->status ?? ''))) {
                    \App\Models\Quota::STATUS_AVAILABLE => 'Available',
                    \App\Models\Quota::STATUS_LIMITED => 'Limited',
                    \App\Models\Quota::STATUS_DEPLETED => 'Depleted',
                    default => 'Unknown',
                };
                $quotaBatches[] = [
                    'gov_ref' => $q->quota_number,
                    'range' => (string) ($q->government_category ?? 'N/A'),
                    'forecast' => (float) $forecastConsumed,
                    'actual' => (float) $actualConsumed,
                    'consumed' => (float) max($forecastConsumed - $actualConsumed, 0),
                    'remaining_status' => $statusLabel,
                ];
            }

            $quotaCards = $quotas;
        } catch (\Throwable $e) {}

        // Activity feed (last 7 days)
        $activities = [];
        try {
            $recentImportsQuery = \App\Models\Import::query();
            if ($hasImportCreatedAt) {
                $recentImportsQuery
                    ->where('created_at','>=', now()->subDays(7))
                    ->orderByDesc('created_at');
            } else {
                $recentImportsQuery->orderByDesc('id');
            }
            $recentImports = $recentImportsQuery->limit(10)->get();
            foreach ($recentImports as $im){
                $activities[] = [
                    'type' => $im->type,
                    'title' => sprintf('%s: %s (valid %d / error %d)', strtoupper($im->type), $im->source_filename, (int)($im->valid_rows ?? 0), (int)($im->error_rows ?? 0)),
                    'time' => ($hasImportCreatedAt && $im->created_at) ? $im->created_at->diffForHumans() : 'recent',
                ];
            }
        } catch (\Throwable $e) {}

        // Alerts
        $alerts = [];
        try {
            // PO tanpa mapping (hs_code_id null)
            $unmappedPo = (int) DB::table('po_lines')->whereNull('hs_code_id')->count();
            if ($unmappedPo > 0) {
                $alerts[] = $unmappedPo === 1
                    ? '1 PO line without HS mapping'
                    : ($unmappedPo.' PO lines without HS mapping');
            }
        } catch (\Throwable $e) {}
        try {
            $lowQuota = $quotaQuery()
                ->whereColumn('forecast_remaining','<=', DB::raw('total_allocation * 0.15'))
                ->count();
            if ($lowQuota > 0) {
                $alerts[] = $lowQuota === 1
                    ? '1 PK bucket nearing depletion (<15%)'
                    : ($lowQuota.' PK buckets nearing depletion (<15%)');
            }
        } catch (\Throwable $e) {}
        try {
            $overGr = (int) DB::table('po_lines')->whereColumn('qty_received','>','qty_ordered')->count();
            if ($overGr > 0) {
                $alerts[] = $overGr === 1
                    ? '1 line: GR exceeds ordered (needs audit)'
                    : ($overGr.' lines: GR exceeds ordered (needs audit)');
            }
        } catch (\Throwable $e) {}
        // Statistics
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        
        // Count admins and regular users
        $totalAdmins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->count();
        
        $totalRegularUsers = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->count();
        
        $totalRoles = Role::count();
        $totalPermissions = Permission::count();

        // Quota insights (large tile uses the new rule)
        try { $totalAlloc = (float) $quotaQuery()->sum('total_allocation'); } catch (\Throwable $e) { $totalAlloc = 0.0; }

        // Quota insights (exclude ACC)
        // Totals derived from per-quota rollups to stay consistent with moveSplitQuota updates and GR-based actuals
        $totalForecastConsumed = (float) $totalForecastConsumed;
        $totalActualConsumed = (float) $totalActualConsumed;

        $quotaStatusCount = function (string $status) use ($quotaQuery) {
            try {
                return (int) $quotaQuery()->whereRaw('LOWER(status) = ?', [$status])->count();
            } catch (\Throwable $e) {
                return 0;
            }
        };

        $quotaStats = [
            'total' => $quotaTotal,
            'available' => $quotaStatusCount(Quota::STATUS_AVAILABLE),
            'limited' => $quotaStatusCount(Quota::STATUS_LIMITED),
            'depleted' => $quotaStatusCount(Quota::STATUS_DEPLETED),
            'forecast_remaining' => max($totalAlloc - $totalForecastConsumed, 0),
            'actual_remaining' => max($totalAlloc - $totalActualConsumed, 0),
        ];

        // Purchase order insights
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd = Carbon::now()->endOfMonth();

        if ($usePurchaseOrders) {
            $dateCol = $purchaseOrderDateCol;
            $qtyCol = $purchaseOrderQtyCol;
            $receivedCol = $purchaseOrderQtyReceivedCol;

            $poStats = [
                'this_month' => ($dateCol && $purchaseOrdersTable)
                    ? (int) DB::table($purchaseOrdersTable)
                        ->whereBetween($dateCol, [$currentPeriodStart->toDateString(), $currentPeriodEnd->toDateString()])
                        ->distinct()
                        ->count($purchaseOrderDocCol)
                    : ($purchaseOrdersTable ? (int) DB::table($purchaseOrdersTable)->distinct()->count($purchaseOrderDocCol) : 0),
                'need_shipment' => ($qtyCol && $receivedCol)
                    ? (int) DB::table($purchaseOrdersTable)
                        ->where($qtyCol, '>', 0)
                        ->whereColumn($receivedCol, '<', $qtyCol)
                        ->count()
                    : 0,
                'in_transit' => ($qtyCol && $receivedCol)
                    ? (int) DB::table($purchaseOrdersTable)
                        ->where($receivedCol, '>', 0)
                        ->whereColumn($receivedCol, '<', $qtyCol)
                        ->count()
                    : 0,
                'completed' => ($qtyCol && $receivedCol)
                    ? (int) DB::table($purchaseOrdersTable)
                        ->where($qtyCol, '>', 0)
                        ->whereColumn($receivedCol, '>=', $qtyCol)
                        ->count()
                    : 0,
            ];

            $orderCol = $dateCol ?? $purchaseOrderDocCol;
            $poRowsQuery = DB::table($purchaseOrdersTable)
                ->select(array_filter([
                    $purchaseOrderDocCol ? DB::raw($quote($purchaseOrderDocCol).' as po_doc') : null,
                    $dateCol ? DB::raw($quote($dateCol).' as po_date') : null,
                    $purchaseOrderVendorCol ? DB::raw($quote($purchaseOrderVendorCol).' as vendor_name') : null,
                    $qtyCol ? DB::raw('COALESCE('.$quote($qtyCol).",0) as qty") : DB::raw('0 as qty'),
                    $receivedCol ? DB::raw('COALESCE('.$quote($receivedCol).",0) as received_qty") : DB::raw('0 as received_qty'),
                    $purchaseOrderSapStatusCol ? DB::raw($quote($purchaseOrderSapStatusCol).' as sap_order_status') : null,
                    $purchaseOrderLineCol ? DB::raw($quote($purchaseOrderLineCol).' as line_no') : null,
                ]))
                ->orderByDesc($orderCol)
                ->limit(100);

            $recentPurchaseOrders = $poRowsQuery->get()
                ->filter(fn ($row) => !empty($row->po_doc))
                ->groupBy('po_doc')
                ->map(function ($group) {
                    $totalQty = (float) $group->sum('qty');
                    $receivedQty = (float) $group->sum('received_qty');

                    $statusKey = 'draft';
                    if ($totalQty <= 0) {
                        $statusKey = 'draft';
                    } elseif ($receivedQty >= $totalQty) {
                        $statusKey = 'completed';
                    } elseif ($receivedQty > 0) {
                        $statusKey = 'partial';
                    } else {
                        $statusKey = 'ordered';
                    }

                    return (object) [
                        'po_number' => (string) $group->first()->po_doc,
                        'po_date' => $group->max('po_date'),
                        'supplier' => $group->first()->vendor_name,
                        'line_count' => $group->count(),
                        'total_qty' => $totalQty,
                        'received_qty' => $receivedQty,
                        'status_key' => $statusKey,
                        'sap_statuses' => $group->pluck('sap_order_status')->filter()->unique()->values()->all(),
                    ];
                })
                ->sortByDesc(fn ($row) => $row->po_date ?? '')
                ->take(5)
                ->values();
        } else {
            $poStats = [
                'this_month' => PoHeader::whereBetween('po_date', [$currentPeriodStart, $currentPeriodEnd])->count(),
                'need_shipment' => PoLine::whereColumn('qty_received', '<', 'qty_ordered')->count(),
                'in_transit' => PoLine::where('qty_received', '>', 0)->whereColumn('qty_received', '<', 'qty_ordered')->count(),
                'completed' => PoLine::where('qty_ordered', '>', 0)->whereColumn('qty_received', '>=', 'qty_ordered')->count(),
            ];

            $recentPurchaseOrdersQuery = PoHeader::with(['lines' => function ($query) {
                    $query->orderBy('line_no');
                }])
                ->orderByDesc('po_date');

            if ($hasPoCreatedAt) {
                $recentPurchaseOrdersQuery->orderByDesc('created_at');
            }

            $recentPurchaseOrders = $recentPurchaseOrdersQuery->take(5)->get()
                ->map(function (PoHeader $header) {
                    $totalQty = (float) $header->lines->sum('qty_ordered');
                    $receivedQty = (float) $header->lines->sum('qty_received');

                    $statusKey = 'draft';
                    if ($totalQty <= 0) {
                        $statusKey = 'draft';
                    } elseif ($receivedQty >= $totalQty) {
                        $statusKey = 'completed';
                    } elseif ($receivedQty > 0) {
                        $statusKey = 'partial';
                    } else {
                        $statusKey = 'ordered';
                    }

                    return (object) [
                        'po_number' => $header->po_number,
                        'po_date' => $header->po_date,
                        'supplier' => $header->supplier,
                        'line_count' => $header->lines->count(),
                        'total_qty' => $totalQty,
                        'received_qty' => $receivedQty,
                        'status_key' => $statusKey,
                        'sap_statuses' => $header->lines->pluck('sap_order_status')->filter()->unique()->values()->all(),
                    ];
                });
        }

        // Shipment insights (derived from PO lines & GR receipts)
        $shipmentStats = [
            'in_transit' => $poStats['in_transit'],
            'delivered' => GrReceipt::count(),
            'pending_receipt' => $poStats['need_shipment'],
        ];

        // Consolidated summary tiles
        if ($usePurchaseOrders) {
            $qtyCol = $purchaseOrderQtyCol;
            $receivedCol = $purchaseOrderQtyReceivedCol;
            $orderedTotal = ($qtyCol && $purchaseOrdersTable) ? (float) DB::table($purchaseOrdersTable)->sum($qtyCol) : 0.0;
            $receivedTotal = ($receivedCol && $purchaseOrdersTable) ? (float) DB::table($purchaseOrdersTable)->sum($receivedCol) : 0.0;
        } else {
            $poAggregate = PoLine::selectRaw('SUM(qty_ordered) as ordered_total, SUM(qty_received) as received_total')->first();
            $orderedTotal = (float) ($poAggregate->ordered_total ?? 0);
            $receivedTotal = (float) ($poAggregate->received_total ?? 0);
        }
        $hasGrUnique = Schema::hasColumn('gr_receipts', 'gr_unique');
        $grDocExpr = $driver === 'sqlsrv'
            ? (
                $hasGrUnique
                    ? "COUNT(DISTINCT COALESCE(gr_unique, CONCAT(po_no,'|',".DbExpression::lineNoTrimmed('line_no').",'|', CONVERT(varchar(50), receive_date, 126)))) as total"
                    : "COUNT(DISTINCT CONCAT(po_no,'|',".DbExpression::lineNoTrimmed('line_no').",'|', CONVERT(varchar(50), receive_date, 126))) as total"
            )
            : (
                $hasGrUnique
                    ? "COUNT(DISTINCT COALESCE(gr_unique, po_no || '|' || ".DbExpression::lineNoTrimmed('line_no')." || '|' || receive_date::text)) as total"
                    : "COUNT(DISTINCT (po_no || '|' || ".DbExpression::lineNoTrimmed('line_no')." || '|' || receive_date::text)) as total"
            );
        $summary = [
            'po_total' => $usePurchaseOrders
                ? (int) DB::table($purchaseOrdersTable)->distinct()->count($purchaseOrderDocCol)
                : $poHeaderCount,
            'po_ordered_total' => $orderedTotal,
            'po_outstanding_total' => max($orderedTotal - $receivedTotal, 0),
            'gr_total_qty' => (float) DB::table('gr_receipts')->sum('qty'),
            // Count distinct GR docs using normalized line_no per driver; tolerate DBs/views without gr_unique
            'gr_document_total' => (int) DB::table('gr_receipts')->selectRaw($grDocExpr)->value('total'),
            'quota_total_allocation' => (float) $totalAlloc,
            'quota_total_remaining' => max($totalAlloc - $totalActualConsumed, 0),
        ];

        // Order by receive_date and only use id when the column exists
        $recentShipments = collect();
        if ($grReceiptsTable && $grPoCol && $grLineCol && $grReceiveCol) {
            $itemExprParts = [];
            foreach ([$grItemCol, $grCatDescCol, $grCatPoDescCol, $grCatPoCol, $grMatDocCol] as $col) {
                if ($col) { $itemExprParts[] = 'NULLIF('.$quote($col).", '')"; }
            }
            $itemExpr = !empty($itemExprParts) ? 'COALESCE('.implode(',', $itemExprParts).')' : 'NULL';
            $recentShipmentsQuery = DB::table($grReceiptsTable)
                ->select(array_filter([
                    DB::raw($quote($grPoCol).' as po_no'),
                    DB::raw($quote($grLineCol).' as line_no'),
                    DB::raw($quote($grReceiveCol).' as receive_date'),
                    DB::raw($itemExpr.' as item_name'),
                    $grVendorCol ? DB::raw($quote($grVendorCol).' as vendor_name') : null,
                    $grWhNameCol ? DB::raw($quote($grWhNameCol).' as wh_name') : null,
                    $grCatPoCol ? DB::raw($quote($grCatPoCol).' as cat_po') : null,
                    $grQtyCol ? DB::raw($quote($grQtyCol).' as qty') : null,
                ]))
                ->orderByDesc($grReceiveCol);

            if ($hasGrId && $grIdCol) {
                $recentShipmentsQuery->orderByDesc($grIdCol);
            } else {
                $recentShipmentsQuery->orderByDesc($grPoCol)->orderByDesc($grLineCol);
            }

            $recentShipments = $recentShipmentsQuery
                ->take(5)
                ->get()
                ->map(function ($receipt) {
                    return (object) [
                        'po_number'      => $receipt->po_no ?? null,
                        'line_no'        => $receipt->line_no ?? null,
                        'receive_date'   => $receipt->receive_date ?? null,
                        'item_name'      => $receipt->item_name ?? null,
                        'vendor_name'    => $receipt->vendor_name ?? null,
                        'warehouse_name' => $receipt->wh_name ?? null,
                        'sap_status'     => $receipt->cat_po ?? null,
                        'quantity'       => (float) ($receipt->qty ?? 0),
                    ];
                });
        }

        // Optional no-cache response (diagnostic only). Uncomment to disable browser caching while styling.
        // return response()->view('admin.dashboard', compact(
        //     'totalUsers', 'activeUsers', 'inactiveUsers', 'totalAdmins', 'totalRegularUsers', 'totalRoles', 'totalPermissions',
        //     'recentUsers', 'usersByRole', 'quotaStats', 'quotaAlerts', 'recentQuotaHistories', 'poStats', 'recentPurchaseOrders',
        //     'shipmentStats', 'recentShipments'
        // ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        //   ->header('Pragma', 'no-cache')
        //   ->header('Expires', '0');

        $viewName = \Illuminate\Support\Facades\View::exists('admin.dashboard')
            ? 'admin.dashboard'
            : (\Illuminate\Support\Facades\View::exists('dashboard.index') ? 'dashboard.index' : 'dashboard');

        return view($viewName, compact(
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalAdmins',
            'totalRegularUsers',
            'totalRoles',
            'totalPermissions',
            'quotaStats',
            'poStats',
            'recentPurchaseOrders',
            'shipmentStats',
            'recentShipments',
            'metrics',
            'quotaCards',
            'quotaBatches',
            'activities',
            'alerts',
            'summary'
        ));
    }
}
