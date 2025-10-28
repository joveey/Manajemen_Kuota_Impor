<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        // Derived per-quota KPI (quota_id-based, clamped to allocation)
        $quotaCards = collect();
        try {
            // Use single-year window (align with Analytics logic)
            $year = (int) request()->query('year', (int) now()->year);
            $start = \Carbon\Carbon::create($year, 1, 1)->toDateString();
            $end   = \Carbon\Carbon::create($year, 12, 31)->toDateString();

            // Select quotas overlapping the chosen year
            $quotas = \App\Models\Quota::query()
                ->where(function($q) use ($start, $end) {
                    $q->whereNull('period_start')
                      ->orWhereNull('period_end')
                      ->orWhere(function($qq) use ($start, $end){
                          $qq->where('period_start','<=',$end)->where('period_end','>=',$start);
                      });
                })
                ->orderBy('quota_number')
                ->get();

            $ids = $quotas->pluck('id')->all();
            $forecastByQuota = empty($ids) ? collect() : \DB::table('purchase_order_quota')
                ->select('quota_id', \DB::raw('SUM(allocated_qty) as qty'))
                ->whereIn('quota_id', $ids)
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');
            $actualByQuota = empty($ids) ? collect() : \DB::table('quota_histories')
                ->select('quota_id', \DB::raw('SUM(ABS(quantity_change)) as qty'))
                ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
                ->whereIn('quota_id', $ids)
                ->whereBetween('occurred_on', [$start, $end])
                ->groupBy('quota_id')
                ->pluck('qty', 'quota_id');

            foreach ($quotas as $q) {
                $alloc = (float) ($q->total_allocation ?? 0);
                // Forecast fallback: use allocation - forecast_remaining when pivot empty
                $pivotFc = (float) ($forecastByQuota[$q->id] ?? 0);
                $fallbackFc = max($alloc - (float) ($q->forecast_remaining ?? 0), 0.0);
                $fc = min($alloc, $pivotFc > 0 ? $pivotFc : $fallbackFc);
                $ac = min($alloc, (float) ($actualByQuota[$q->id] ?? 0));
                $q->setAttribute('forecast_consumed', $fc);
                $q->setAttribute('actual_consumed', $ac);
                $q->setAttribute('forecast_remaining', max($alloc - $fc, 0));
                $q->setAttribute('actual_remaining', max($alloc - $ac, 0));
                $q->setAttribute('in_transit', max($fc - $ac, 0));
                $q->setAttribute('year', $year);
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

        // Quota insights
        $quotaStats = [
            'total' => Quota::count(),
            'available' => Quota::where('status', Quota::STATUS_AVAILABLE)->count(),
            'limited' => Quota::where('status', Quota::STATUS_LIMITED)->count(),
            'depleted' => Quota::where('status', Quota::STATUS_DEPLETED)->count(),
            'forecast_remaining' => Quota::sum('forecast_remaining'),
            'actual_remaining' => Quota::sum('actual_remaining'),
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
            'gr_total_qty' => (float) \DB::table('gr_receipts')->sum('qty'),
            'gr_document_total' => (int) \DB::table('gr_receipts')
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
            'activities',
            'alerts',
            'summary'
        ));
    }
}
