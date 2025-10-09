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
            'recentShipments'
        ));
    }
}
