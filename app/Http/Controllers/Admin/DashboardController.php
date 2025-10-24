<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\QuotaHistory;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Pipeline & KPI calculations (lightweight, no mapping changes)
        $metrics = [];
        try {
            // Products mapping coverage
            $metrics['mapped'] = \App\Models\Product::whereNotNull('hs_code')->whereNotNull('pk_capacity')->count();
            $metrics['unmapped'] = \App\Models\Product::where(function($q){ $q->whereNull('hs_code')->orWhereNull('pk_capacity'); })->count();
        } catch (\Throwable $e) {
            $metrics['mapped'] = $metrics['unmapped'] = 0; // tolerate if table pruned
        }

        try {
            // Open PO outstanding qty
            $metrics['open_po_qty'] = (float) \DB::table('po_lines')->selectRaw('SUM(GREATEST(qty_ordered - COALESCE(qty_received,0),0)) as s')->value('s');
        } catch (\Throwable $e) { $metrics['open_po_qty'] = 0; }

        try {
            // In-Transit = max(inv - gr, 0)
            $inv = \DB::table('invoices')->select('po_no','line_no', \DB::raw('SUM(qty) as qty'))->groupBy('po_no','line_no');
            $gr  = \DB::table('gr_receipts')->select('po_no','line_no', \DB::raw('SUM(qty) as qty'))->groupBy('po_no','line_no');
            $sum = \DB::table('po_lines as pl')
                ->join('po_headers as ph','pl.po_header_id','=','ph.id')
                ->leftJoinSub($inv,'inv',function($j){$j->on('ph.po_number','=','inv.po_no')->on('pl.line_no','=','inv.line_no');})
                ->leftJoinSub($gr,'gr',function($j){$j->on('ph.po_number','=','gr.po_no')->on('pl.line_no','=','gr.line_no');})
                ->selectRaw('SUM(GREATEST(COALESCE(inv.qty,0)-COALESCE(gr.qty,0),0)) as s')->value('s');
            $metrics['in_transit_qty'] = (float) $sum;
        } catch (\Throwable $e) { $metrics['in_transit_qty'] = 0; }

        try {
            // GR qty (last 30 days)
            $since = now()->subDays(30)->toDateString();
            $metrics['gr_qty'] = (float) \DB::table('gr_receipts')->where('receive_date','>=',$since)->sum('qty');
        } catch (\Throwable $e) { $metrics['gr_qty'] = 0; }

        // Derived per-quota KPI using service (tolerant if service fails)
        $quotaCards = collect();
        try {
            $quotas = \App\Models\Quota::orderByDesc('period_start')->limit(6)->get();
            $derived = app(\App\Services\QuotaConsumptionService::class)->computeForQuotas($quotas);
            foreach ($quotas as $q) {
                $d = $derived[$q->id] ?? null;
                if ($d){
                    $q->setAttribute('actual_consumed',$d['actual_consumed']);
                    $q->setAttribute('forecast_consumed',$d['forecast_consumed']);
                    $q->setAttribute('actual_remaining',$d['actual_remaining']);
                    $q->setAttribute('forecast_remaining',$d['forecast_remaining']);
                }
            }
            $quotaCards = $quotas;
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
            $unmappedPo = (int) \DB::table('po_lines')->whereNull('hs_code_id')->count();
            if ($unmappedPo > 0) { $alerts[] = "PO tanpa mapping HS: $unmappedPo line"; }
        } catch (\Throwable $e) {}
        try {
            $lowQuota = \App\Models\Quota::whereColumn('forecast_remaining','<=', \DB::raw('total_allocation * 0.15'))->count();
            if ($lowQuota > 0) { $alerts[] = "$lowQuota PK bucket mendekati habis (<15%)"; }
        } catch (\Throwable $e) {}
        try {
            $overGr = (int) \DB::table('po_lines')->whereColumn('qty_received','>','qty_ordered')->count();
            if ($overGr > 0) { $alerts[] = "Ada $overGr line: GR melebihi ordered (perlu audit)"; }
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

        // Recent users
        $recentUsers = User::with('roles')
            ->latest()
            ->take(5)
            ->get();

        // User by role
        $usersByRole = Role::withCount('users')->get();

        // Quota insights
        $quotaStats = [
            'total' => Quota::count(),
            'available' => Quota::where('status', Quota::STATUS_AVAILABLE)->count(),
            'limited' => Quota::where('status', Quota::STATUS_LIMITED)->count(),
            'depleted' => Quota::where('status', Quota::STATUS_DEPLETED)->count(),
            'forecast_remaining' => Quota::sum('forecast_remaining'),
            'actual_remaining' => Quota::sum('actual_remaining'),
        ];

        $quotaAlerts = Quota::orderBy('forecast_remaining')->take(5)->get();
        $recentQuotaHistories = QuotaHistory::with('quota')->latest()->take(5)->get();

        // Purchase order insights
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd = Carbon::now()->endOfMonth();

        $poStats = [
            'this_month' => PurchaseOrder::whereBetween('order_date', [$currentPeriodStart, $currentPeriodEnd])->count(),
            'need_shipment' => PurchaseOrder::whereColumn('quantity_shipped', '<', 'quantity')->count(),
            'in_transit' => PurchaseOrder::where('status', PurchaseOrder::STATUS_IN_TRANSIT)->count(),
            'completed' => PurchaseOrder::where('status', PurchaseOrder::STATUS_COMPLETED)->count(),
        ];

        $recentPurchaseOrders = PurchaseOrder::with(['product', 'quota'])
            ->latest('order_date')
            ->take(5)
            ->get();

        // Shipment insights
        $shipmentStats = [
            'in_transit' => Shipment::whereIn('status', [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_PARTIAL])->count(),
            'delivered' => Shipment::where('status', Shipment::STATUS_DELIVERED)->count(),
            'pending_receipt' => Shipment::whereColumn('quantity_planned', '>', 'quantity_received')->count(),
        ];

        $recentShipments = Shipment::with(['purchaseOrder.product'])
            ->latest('ship_date')
            ->take(5)
            ->get();

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
            'recentUsers',
            'usersByRole',
            'quotaStats',
            'quotaAlerts',
            'recentQuotaHistories',
            'poStats',
            'recentPurchaseOrders',
            'shipmentStats',
            'recentShipments',
            'metrics',
            'quotaCards',
            'activities',
            'alerts'
        ));
    }
}
