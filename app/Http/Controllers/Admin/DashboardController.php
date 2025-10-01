<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Statistics
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        $totalRoles = Role::where('is_active', true)->count();
        $totalPermissions = Permission::count();

        // Recent users
        $recentUsers = User::with('roles')
            ->latest()
            ->take(5)
            ->get();

        // User by role
        $usersByRole = Role::withCount('users')->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalRoles',
            'totalPermissions',
            'recentUsers',
            'usersByRole'
        ));
    }
}