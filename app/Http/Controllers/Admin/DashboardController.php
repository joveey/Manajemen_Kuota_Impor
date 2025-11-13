<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Quota;
use App\Models\Role;
use App\Models\PoHeader;
use App\Models\PoLine;
use App\Models\GrReceipt;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
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
                ->selectRaw("po_no, CAST(regexp_replace(CAST(line_no AS text),'[^0-9]','', 'g') AS INTEGER) AS ln")
                ->selectRaw('SUM(qty) as qty')
                ->groupBy('po_no','ln');
            $totalGr = (float) DB::table('po_lines as pl')
                ->join('po_headers as ph','pl.po_header_id','=','ph.id')
                ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                ->leftJoinSub($grn, 'grn', function($j){
                    $j->on('grn.po_no','=','ph.po_number')
                      ->whereRaw("grn.ln = CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','', 'g') AS INTEGER)");
                })
                ->whereNotNull('pl.hs_code_id')
                ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'")
                ->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');
            $metrics['gr_qty'] = $totalGr; // total GR all time under same filters
        } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }

        // In-Transit = total_po - total_gr (clamped >= 0)
        $metrics['in_transit_qty'] = max(($metrics['open_po_qty'] ?? 0) - ($metrics['gr_qty'] ?? 0), 0);

        try {
            // GR qty (last 30 days)
            $since = now()->subDays(30)->toDateString();
            $metrics['gr_qty'] = (float) DB::table('gr_receipts')->where('receive_date','>=',$since)->sum('qty');
        } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }

        // Derived per-quota KPI (quota_id-based, clamped to allocation)
        $quotaCards = collect();
        try {
            // Show all quotas (not limited by current period/year)
            $quotas = \App\Models\Quota::query()
                ->orderBy('quota_number')
                ->get();

            $ids = $quotas->pluck('id')->all();
            // Aggregate forecast/actual over full history
            $forecastByQuota = empty($ids) ? collect() : DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', \App\Models\QuotaHistory::TYPE_FORECAST_DECREASE)
                ->whereIn('quota_id', $ids)
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');
            $actualByQuota = empty($ids) ? collect() : DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
                ->whereIn('quota_id', $ids)
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');

            $quotaBatches = [];
            foreach ($quotas as $q) {
                $alloc = (float) ($q->total_allocation ?? 0);
                $fc = min($alloc, (float) ($forecastByQuota[$q->id] ?? 0));
                $ac = min($alloc, (float) ($actualByQuota[$q->id] ?? 0));
                $q->setAttribute('forecast_consumed', $fc);
                $q->setAttribute('actual_consumed', $ac);
                $q->setAttribute('forecast_remaining', max($alloc - $fc, 0));
                $q->setAttribute('actual_remaining', max($alloc - $ac, 0));
                // Compute In-Transit as PO outstanding (ordered - received) matched by quota's PK bucket and period, excluding ACC
                // Robust per-quota outstanding using GR receipts; avoid exceptions and fallbacks
                $bounds = \App\Support\PkCategoryParser::parse((string) ($q->government_category ?? ''));
                // 1) Distinct list of PO numbers allocated to this quota to avoid duplication
                $poList = DB::table('purchase_order_quota as pq')
                    ->join('purchase_orders as po', 'pq.purchase_order_id', '=', 'po.id')
                    ->where('pq.quota_id', $q->id)
                    ->distinct()
                    ->pluck('po.po_number');

                if ($poList->isEmpty()) {
                    $qOutstanding = 0.0;
                } else {
                    // 2) Aggregate GR per PO+line
                    $grn = DB::table('gr_receipts')
                        ->selectRaw("po_no, CAST(regexp_replace(CAST(line_no AS text),'[^0-9]','', 'g') AS INTEGER) AS ln")
                        ->selectRaw('SUM(qty) as qty')
                        ->groupBy('po_no','ln');

                    // 3) Sum outstanding per mapped non‑ACC line within bucket and period, for those POs only
                    $lines = DB::table('po_lines as pl')
                        ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                        ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                        ->leftJoinSub($grn, 'grn', function($j){
                            $j->on('grn.po_no','=','ph.po_number')
                              ->whereRaw("grn.ln = CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','', 'g') AS INTEGER)");
                        })
                        ->whereIn('ph.po_number', $poList->all())
                        ->whereNotNull('pl.hs_code_id')
                        ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");

                    if ($bounds['min_pk'] !== null) {
                        $lines->where('hs.pk_capacity', $bounds['min_incl'] ? '>=' : '>', $bounds['min_pk']);
                    }
                    if ($bounds['max_pk'] !== null) {
                        $lines->where('hs.pk_capacity', $bounds['max_incl'] ? '<=' : '<', $bounds['max_pk']);
                    }
                    if (!empty($q->period_start) && !empty($q->period_end)) {
                        $lines->whereBetween('ph.po_date', [
                            $q->period_start->toDateString(),
                            $q->period_end->toDateString(),
                        ]);
                    }

                    $qOutstanding = (float) $lines
                        ->selectRaw('SUM(GREATEST(COALESCE(pl.qty_ordered,0) - COALESCE(grn.qty,0), 0)) as s')
                        ->value('s');
                }
                $q->setAttribute('in_transit', max($qOutstanding, 0));
                // keep period labels from quota fields; no fixed year context

                $statusLabel = match ($q->status) {
                    \App\Models\Quota::STATUS_AVAILABLE => 'Available',
                    \App\Models\Quota::STATUS_LIMITED => 'Limited',
                    \App\Models\Quota::STATUS_DEPLETED => 'Depleted',
                    default => 'Unknown',
                };
                $quotaBatches[] = [
                    'gov_ref' => $q->quota_number,
                    'range' => (string) ($q->government_category ?? 'N/A'),
                    'forecast' => (float) $fc,
                    'actual' => (float) $alloc,
                    'consumed' => (float) $ac,
                    'remaining_status' => $statusLabel,
                ];
            }

            $quotaCards = $quotas;
            // Override metrics per new business rules (allocation, total_po, consumed, in_transit, forecast_rem, actual_rem)
            foreach ($quotaCards as $q) {
                $alloc = (float) ($q->total_allocation ?? 0);
                $bounds = \App\Support\PkCategoryParser::parse((string) ($q->government_category ?? ''));

                $grn = DB::table('gr_receipts')
                    ->selectRaw("po_no, CAST(regexp_replace(CAST(line_no AS text),'[^0-9]','', 'g') AS INTEGER) AS ln")
                    ->selectRaw('SUM(qty) as qty')
                    ->groupBy('po_no','ln');

                $base = DB::table('po_lines as pl')
                    ->join('po_headers as ph', 'pl.po_header_id', '=', 'ph.id')
                    ->leftJoin('hs_code_pk_mappings as hs', 'pl.hs_code_id', '=', 'hs.id')
                    ->leftJoinSub($grn, 'grn', function($j){
                        $j->on('grn.po_no','=','ph.po_number')
                          ->whereRaw("grn.ln = CAST(regexp_replace(COALESCE(pl.line_no,''),'[^0-9]','', 'g') AS INTEGER)");
                    })
                    ->whereNotNull('pl.hs_code_id')
                    ->whereRaw("COALESCE(UPPER(hs.hs_code),'') <> 'ACC'");

                if ($bounds['min_pk'] !== null) {
                    $base->where('hs.pk_capacity', $bounds['min_incl'] ? '>=' : '>', $bounds['min_pk']);
                }
                if ($bounds['max_pk'] !== null) {
                    $base->where('hs.pk_capacity', $bounds['max_incl'] ? '<=' : '<', $bounds['max_pk']);
                }
                if (!empty($q->period_start) && !empty($q->period_end)) {
                    $base->whereBetween('ph.po_date', [
                        $q->period_start->toDateString(),
                        $q->period_end->toDateString(),
                    ]);
                }

                $totalPo = (float) (clone $base)->selectRaw('SUM(COALESCE(pl.qty_ordered,0)) as s')->value('s');
                $consumed = (float) (clone $base)->selectRaw('SUM(COALESCE(grn.qty,0)) as s')->value('s');

                $q->setAttribute('forecast_consumed', $totalPo);
                $q->setAttribute('actual_consumed', $consumed);
                $q->setAttribute('in_transit', max($totalPo - $consumed, 0));
                $q->setAttribute('forecast_remaining', max($alloc - $totalPo, 0));
                $q->setAttribute('actual_remaining', max($alloc - $consumed, 0));
            }
            // expose to view for charts
            $quotaBatches = $quotaBatches;
        } catch (\Throwable $e) {}

        // Activity feed (last 7 days)
        $activities = [];
        try {
            $recentImports = \App\Models\Import::where('created_at','>=', now()->subDays(7))
                ->orderByDesc('created_at')->limit(10)->get();
            foreach ($recentImports as $im){
                $activities[] = [
                    'type' => $im->type,
                    'title' => sprintf('%s: %s (valid %d / error %d)', strtoupper($im->type), $im->source_filename, (int)($im->valid_rows ?? 0), (int)($im->error_rows ?? 0)),
                    'time' => $im->created_at->diffForHumans(),
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
            $lowQuota = \App\Models\Quota::whereColumn('forecast_remaining','<=', DB::raw('total_allocation * 0.15'))->count();
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
        try { $totalAlloc = (float) Quota::sum('total_allocation'); } catch (\Throwable $e) { $totalAlloc = 0.0; }
        $quotaStats = [
            'total' => Quota::count(),
            'available' => Quota::where('status', Quota::STATUS_AVAILABLE)->count(),
            'limited' => Quota::where('status', Quota::STATUS_LIMITED)->count(),
            'depleted' => Quota::where('status', Quota::STATUS_DEPLETED)->count(),
            // New business rule
            'forecast_remaining' => max($totalAlloc - ($metrics['open_po_qty'] ?? 0), 0),
            'actual_remaining' => max($totalAlloc - ($metrics['gr_qty'] ?? 0), 0),
        ];

        // Purchase order insights
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd = Carbon::now()->endOfMonth();

        $poStats = [
            'this_month' => PoHeader::whereBetween('po_date', [$currentPeriodStart, $currentPeriodEnd])->count(),
            'need_shipment' => PoLine::whereColumn('qty_received', '<', 'qty_ordered')->count(),
            'in_transit' => PoLine::where('qty_received', '>', 0)->whereColumn('qty_received', '<', 'qty_ordered')->count(),
            'completed' => PoLine::where('qty_ordered', '>', 0)->whereColumn('qty_received', '>=', 'qty_ordered')->count(),
        ];

        $recentPurchaseOrders = PoHeader::with(['lines' => function ($query) {
                $query->orderBy('line_no');
            }])
            ->orderByDesc('po_date')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
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

        // Shipment insights (derived from PO lines & GR receipts)
        $shipmentStats = [
            'in_transit' => $poStats['in_transit'],
            'delivered' => GrReceipt::count(),
            'pending_receipt' => $poStats['need_shipment'],
        ];

        // Consolidated summary tiles
        $poAggregate = PoLine::selectRaw('SUM(qty_ordered) as ordered_total, SUM(qty_received) as received_total')->first();
        $orderedTotal = (float) ($poAggregate->ordered_total ?? 0);
        $receivedTotal = (float) ($poAggregate->received_total ?? 0);
        $summary = [
            'po_total' => PoHeader::count(),
            'po_ordered_total' => $orderedTotal,
            'po_outstanding_total' => max($orderedTotal - $receivedTotal, 0),
            'gr_total_qty' => (float) DB::table('gr_receipts')->sum('qty'),
            'gr_document_total' => (int) DB::table('gr_receipts')
                ->selectRaw("COUNT(DISTINCT COALESCE(gr_unique, po_no || '|' || line_no || '|' || receive_date::text)) as total")
                ->value('total'),
            'quota_total_allocation' => (float) Quota::sum('total_allocation'),
            'quota_total_remaining' => (float) Quota::sum('actual_remaining'),
        ];

        $recentShipments = GrReceipt::orderByDesc('receive_date')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function (GrReceipt $receipt) {
                return (object) [
                    'po_number' => $receipt->po_no,
                    'line_no' => $receipt->line_no,
                    'receive_date' => $receipt->receive_date,
                    'quantity' => (float) $receipt->qty,
                    'vendor_name' => $receipt->vendor_name,
                    'warehouse_name' => $receipt->wh_name,
                    'item_name' => $receipt->item_name,
                    'sap_status' => $receipt->cat_po,
                ];
            });

        // Optional no-cache response (diagnostic only). Uncomment to disable browser caching while styling.
        // return response()->view('admin.dashboard', compact(
        //     'totalUsers', 'activeUsers', 'inactiveUsers', 'totalAdmins', 'totalRegularUsers', 'totalRoles', 'totalPermissions',
        //     'recentUsers', 'usersByRole', 'quotaStats', 'quotaAlerts', 'recentQuotaHistories', 'poStats', 'recentPurchaseOrders',
        //     'shipmentStats', 'recentShipments'
        // ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        //   ->header('Pragma', 'no-cache')
        //   ->header('Expires', '0');

        return view('admin.dashboard', compact(
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
