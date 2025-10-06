

{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('styles')
<style>
    /* Stat Cards */
    .stat-card {
        background: white;
        border: 1px solid #e5eaef;
        border-radius: 12px;
        padding: 24px;
        height: 100%;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 16px;
    }

    .stat-icon.primary {
        background: #ECF2FF;
        color: #5D87FF;
    }

    .stat-icon.success {
        background: #E6FFFA;
        color: #13DEB9;
    }

    .stat-icon.info {
        background: #FEF5E5;
        color: #FFAE1F;
    }

    .stat-icon.danger {
        background: #FDEDE8;
        color: #FA896B;
    }

    .stat-label {
        font-size: 14px;
        color: #5A6A85;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #2A3547;
        margin-bottom: 12px;
    }

    .stat-link {
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        color: #5D87FF;
        transition: all 0.2s ease;
    }

    .stat-link:hover {
        gap: 8px;
    }

    .stat-link i {
        margin-left: 6px;
        font-size: 12px;
    }

    /* Welcome Card */
    .welcome-card {
        background: linear-gradient(135deg, #5D87FF 0%, #7C4DFF 100%);
        border: none;
        color: white;
    }

    .welcome-card .card-body {
        padding: 32px;
    }

    .user-greeting {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .user-info-item {
        margin-bottom: 12px;
        opacity: 0.95;
    }

    .user-info-label {
        font-size: 13px;
        opacity: 0.8;
        margin-bottom: 6px;
    }

    .user-info-value {
        font-size: 14px;
        font-weight: 500;
    }

    .role-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.2);
        font-size: 12px;
        font-weight: 600;
        margin-right: 6px;
        margin-top: 4px;
    }

    .divider-light {
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        margin: 24px 0;
    }

    .quick-actions-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 16px;
    }

    .btn-quick {
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.1);
        color: white;
        width: 100%;
        transition: all 0.2s ease;
    }

    .btn-quick:hover {
        background: white;
        color: #5D87FF;
        border-color: white;
        transform: translateY(-2px);
    }

    /* Role Distribution */
    .role-table {
        margin: 0;
    }

    .role-table th {
        font-weight: 600;
        color: #5A6A85;
        font-size: 12px;
        text-transform: uppercase;
        border: none;
        padding: 12px 16px;
        background: #F9FAFB;
    }

    .role-table td {
        padding: 14px 16px;
        border-top: 1px solid #e5eaef;
        font-size: 14px;
        color: #2A3547;
    }

    .role-badge-table {
        padding: 6px 12px;
        border-radius: 6px;
        background: #ECF2FF;
        color: #5D87FF;
        font-size: 12px;
        font-weight: 600;
    }

    /* Users Table */
    .users-table {
        margin: 0;
    }

    .users-table thead th {
        font-weight: 600;
        color: #5A6A85;
        font-size: 12px;
        text-transform: uppercase;
        border: none;
        padding: 14px 16px;
        background: #F9FAFB;
    }

    .users-table tbody td {
        padding: 14px 16px;
        border-top: 1px solid #e5eaef;
        font-size: 14px;
        color: #2A3547;
        vertical-align: middle;
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar-table {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid #e5eaef;
    }

    .user-name {
        font-weight: 600;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.active {
        background: #E6FFFA;
        color: #13DEB9;
    }

    .status-badge.inactive {
        background: #FDEDE8;
        color: #FA896B;
    }

    .btn-view {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid #e5eaef;
        background: white;
        color: #5A6A85;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .btn-view:hover {
        background: #ECF2FF;
        color: #5D87FF;
        border-color: #5D87FF;
    }

    .card-header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-add-user {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        background: #5D87FF;
        color: white;
        border: none;
        transition: all 0.2s ease;
    }

    .btn-add-user:hover {
        background: #4570EA;
        transform: translateY(-2px);
    }
</style>
@endpush

@section('content')

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    
    @if(Auth::user()->hasPermission('read users'))
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-label">Total Users</div>
            <div class="stat-number">{{ $totalUsers }}</div>
            <a href="{{ route('admin.users.index') }}" class="stat-link">
                View Details <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
    @endif

    @if(Auth::user()->isAdmin())
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-label">Administrators</div>
            <div class="stat-number">{{ $totalAdmins }}</div>
            <a href="{{ route('admin.admins.index') }}" class="stat-link">
                View Details <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
    @endif

    @if(Auth::user()->hasPermission('read roles'))
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-user-tag"></i>
            </div>
            <div class="stat-label">Roles</div>
            <div class="stat-number">{{ $totalRoles }}</div>
            <a href="{{ route('admin.roles.index') }}" class="stat-link">
                View Details <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
    @endif

    @if(Auth::user()->hasPermission('read permissions'))
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-key"></i>
            </div>
            <div class="stat-label">Permissions</div>
            <div class="stat-number">{{ $totalPermissions }}</div>
            <a href="{{ route('admin.permissions.index') }}" class="stat-link">
                View Details <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
    @endif

</div>

<!-- Welcome & Role Cards -->
<div class="row g-3 mb-4">
    
    <!-- Welcome Card -->
    <div class="col-lg-{{ (Auth::user()->hasPermission('read users') || Auth::user()->hasPermission('read roles')) ? '8' : '12' }}">
        <div class="card welcome-card">
            <div class="card-body">
                <div class="user-greeting">Hello, {{ Auth::user()->name }}! 👋</div>
                
                <div class="user-info-item">
                    <div class="user-info-label">Your roles:</div>
                    <div class="user-info-value">
                        @foreach(Auth::user()->roles as $role)
                            <span class="role-badge">{{ $role->name }}</span>
                        @endforeach
                    </div>
                </div>
                
                <div class="user-info-item">
                    <div class="user-info-label">Last login:</div>
                    <div class="user-info-value">
                        @if(Auth::user()->last_login_at)
                            {{ Auth::user()->last_login_at->diffForHumans() }}
                            <span style="opacity: 0.7;"> ({{ Auth::user()->last_login_at->format('d M Y, H:i') }})</span>
                        @else
                            First time login
                        @endif
                    </div>
                </div>

                @if(Auth::user()->hasPermission('read users') || Auth::user()->hasPermission('read roles') || Auth::user()->hasPermission('read permissions'))
                <div class="divider-light"></div>
                
                <div class="quick-actions-title">Quick Actions</div>
                <div class="row g-2">
                    @if(Auth::user()->hasPermission('read users'))
                    <div class="col-md-4">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-quick">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                    </div>
                    @endif
                    @if(Auth::user()->hasPermission('read roles'))
                    <div class="col-md-4">
                        <a href="{{ route('admin.roles.index') }}" class="btn btn-quick">
                            <i class="fas fa-user-tag"></i> Manage Roles
                        </a>
                    </div>
                    @endif
                    @if(Auth::user()->hasPermission('read permissions'))
                    <div class="col-md-4">
                        <a href="{{ route('admin.permissions.index') }}" class="btn btn-quick">
                            <i class="fas fa-key"></i> Manage Permissions
                        </a>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Users by Role -->
    @if(Auth::user()->hasPermission('read users') || Auth::user()->hasPermission('read roles'))
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie me-2"></i>Users by Role
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table role-table mb-0">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th class="text-end">Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usersByRole as $role)
                            <tr>
                                <td>
                                    <span class="role-badge-table">{{ $role->name }}</span>
                                </td>
                                <td class="text-end">
                                    <strong>{{ $role->users_count }}</strong>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

<!-- Recent Users -->
@if(Auth::user()->hasPermission('read users'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-header-actions">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Recent Users
                    </h3>
                    @if(Auth::user()->hasPermission('create users'))
                    <a href="{{ route('admin.users.create') }}" class="btn btn-add-user">
                        <i class="fas fa-user-plus me-2"></i>Add User
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table users-table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentUsers as $user)
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&size=40&background=5D87FF&color=fff" 
                                             class="user-avatar-table" alt="User">
                                        <span class="user-name">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @foreach($user->roles as $role)
                                        <span class="role-badge-table me-1">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($user->is_active)
                                        <span class="status-badge active">Active</span>
                                    @else
                                        <span class="status-badge inactive">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->last_login_at)
                                        <small class="text-muted">{{ $user->last_login_at->diffForHumans() }}</small>
                                    @else
                                        <small class="text-muted">Never</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($user->isAdmin())
                                        <a href="{{ route('admin.admins.show', $user) }}" class="btn btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    console.log('Dashboard loaded successfully!');
</script>
@endpush