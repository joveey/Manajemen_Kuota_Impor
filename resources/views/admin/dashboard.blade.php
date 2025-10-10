

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
        border-radius: 14px;
        padding: 22px;
        height: 100%;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06);
        background: rgba(15, 23, 42, 0.02);
    }

    .stat-icon i {
        font-size: 20px;
    }

    .stat-icon.primary {
        color: #2563eb;
        background: rgba(37, 99, 235, 0.12);
    }

    .stat-icon.success {
        color: #10b981;
        background: rgba(16, 185, 129, 0.12);
    }

    .stat-icon.info {
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.12);
    }

    .stat-icon.danger {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.12);
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
        border: 1px solid #e6ebf5;
        border-radius: 20px;
        background: linear-gradient(135deg, #ffffff 0%, #f7faff 100%);
        box-shadow: 0 28px 42px -38px rgba(15, 23, 42, 0.45);
        color: #0f172a;
    }

    .welcome-card .card-body {
        padding: 26px 30px 30px;
    }

    .welcome-card__header {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        align-items: flex-start;
    }

    .welcome-card__title {
        font-size: 22px;
        font-weight: 700;
        margin: 0;
        color: #0f172a;
    }

    .welcome-card__subtitle {
        margin: 6px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .welcome-card__last-login {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        min-width: 150px;
    }

    .welcome-card__last-login-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
    }

    .welcome-card__last-login-value {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
    }

    .welcome-card__last-login-meta {
        font-size: 12px;
        color: #94a3b8;
    }

    .welcome-card__roles {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 20px 0 6px;
    }

    .role-badge,
    .role-chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.12);
        color: #2563eb;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .welcome-card__actions {
        margin-top: 22px;
    }

    .welcome-card__actions-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        margin-bottom: 12px;
        display: block;
    }

    .quick-action-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .quick-action-chip {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 14px;
        border: 1px solid rgba(37, 99, 235, 0.18);
        background: rgba(37, 99, 235, 0.08);
        color: #1d4ed8;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        flex: 1 1 180px;
        transition: all 0.2s ease;
    }

    .quick-action-chip:hover {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 16px 30px -24px rgba(37, 99, 235, 0.75);
        transform: translateY(-2px);
    }

    /* Operational panels */
    .panel-modern {
        background: #ffffff;
        border: 1px solid #e6ebf5;
        border-radius: 18px;
        box-shadow: 0 20px 42px -32px rgba(15, 23, 42, 0.45);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .panel-modern__header {
        padding: 20px 24px 16px;
        border-bottom: 1px solid #eef2fb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .panel-modern__title {
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .panel-modern__body {
        padding: 20px 24px 24px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .panel-modern__link {
        font-size: 12px;
        font-weight: 600;
        color: #2563eb;
    }

    .panel-modern__link:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .panel-modern__empty {
        padding: 28px;
        text-align: center;
        border: 1px dashed #e2e8f0;
        border-radius: 14px;
        color: #94a3b8;
        font-size: 13px;
        margin-top: 6px;
    }

    .role-panel {
        border: 1px solid #e6ebf5;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 24px 44px -36px rgba(15, 23, 42, 0.35);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .role-panel__header {
        padding: 20px 24px 16px;
        border-bottom: 1px solid #eef2fb;
        font-weight: 600;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
    }

    .role-panel__body {
        padding: 18px 24px 24px;
        flex: 1;
    }

    .role-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .role-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 18px;
        border-radius: 12px;
        border: 1px solid #eef2fb;
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .role-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 32px -30px rgba(15, 23, 42, 0.4);
    }

    .role-item__count {
        font-weight: 700;
        color: #0f172a;
        font-size: 14px;
    }

    .quota-alerts {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .quota-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 16px 18px;
        border-radius: 14px;
        border: 1px solid #eef2fb;
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .quota-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 28px -28px rgba(15, 23, 42, 0.45);
    }

    .quota-item--warning {
        border-color: #fde68a;
        background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
    }

    .quota-item--critical {
        border-color: #fecaca;
        background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
    }

    .quota-item__title {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
    }

    .quota-meta {
        font-size: 11.5px;
        color: #64748b;
        margin-top: 4px;
    }

    .quota-pill {
        margin-top: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: #e2e8f0;
        color: #475569;
        letter-spacing: 0.02em;
    }

    .quota-item--warning .quota-pill {
        background: #fef3c7;
        color: #92400e;
    }

    .quota-item--critical .quota-pill {
        background: #fee2e2;
        color: #b91c1c;
    }

    .quota-item--normal .quota-pill {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid rgba(37, 99, 235, 0.35);
        background: rgba(37, 99, 235, 0.08);
        color: #2563eb;
        font-weight: 600;
        font-size: 12px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-ghost:hover {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 10px 20px -18px rgba(37, 99, 235, 0.75);
    }

    .data-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .data-row {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.7fr) minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
        padding: 16px 18px;
        border-radius: 14px;
        border: 1px solid #eef2fb;
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .data-row--shipment {
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.6fr) minmax(0, 1fr) auto;
    }

    .data-row:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px -28px rgba(15, 23, 42, 0.45);
    }

    .data-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .data-cell--status {
        align-items: flex-start;
        gap: 6px;
    }

    .data-cell--qty {
        align-items: flex-end;
        text-align: right;
        min-width: 72px;
    }

    .data-title {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .data-link {
        font-size: 13px;
        font-weight: 600;
        color: #2563eb;
        text-decoration: none;
    }

    .data-link:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .data-sub {
        font-size: 12px;
        color: #64748b;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .data-qty {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    @media (max-width: 1100px) {
        .data-row,
        .data-row--shipment {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .data-cell--qty {
            align-items: flex-start;
            text-align: left;
        }
    }

    .badge-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .badge-chip--warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-chip--info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-chip--success {
        background: #dcfce7;
        color: #166534;
    }

    .badge-chip--muted {
        background: #e2e8f0;
        color: #475569;
    }

    .timeline-sm {
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }

    .timeline-sm li {
        position: relative;
        padding-left: 40px;
        margin-bottom: 20px;
    }

    .timeline-sm li:last-child {
        margin-bottom: 0;
    }

    .timeline-sm li::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 4px;
        bottom: -20px;
        width: 2px;
        background: #e2e8f0;
    }

    .timeline-sm li:last-child::before {
        bottom: 10px;
    }

    .timeline-icon {
        position: absolute;
        left: 8px;
        top: 0;
        width: 24px;
        height: 24px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        color: #ffffff;
        box-shadow: 0 6px 16px -10px rgba(37, 99, 235, 0.6);
    }

    .timeline-icon i {
        font-size: 11px;
        line-height: 1;
    }

    /* Role & Summary Tables */
    .dashboard-table,
    .role-table,
    .users-table {
        margin: 0;
    }

    .dashboard-table thead th,
    .role-table thead th,
    .users-table thead th {
        font-weight: 600;
        color: #5A6A85;
        font-size: 12px;
        text-transform: uppercase;
        border: none;
        padding: 14px 20px;
        background: #F9FAFB;
    }

    .dashboard-table tbody td,
    .role-table tbody td,
    .users-table tbody td {
        padding: 16px 20px;
        border-top: 1px solid #e5eaef;
        font-size: 14px;
        color: #2A3547;
        vertical-align: middle;
    }

    .role-badge-table {
        padding: 6px 12px;
        border-radius: 6px;
        background: #ECF2FF;
        color: #5D87FF;
        font-size: 12px;
        font-weight: 600;
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
                View Details
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14" style="margin-left:6px">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
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
                View Details
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14" style="margin-left:6px">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>
    @endif

    @if(Auth::user()->hasPermission('read roles'))
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-bookmark"></i>
            </div>
            <div class="stat-label">Roles</div>
            <div class="stat-number">{{ $totalRoles }}</div>
            <a href="{{ route('admin.roles.index') }}" class="stat-link">
                View Details
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14" style="margin-left:6px">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
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

<!-- Supply Chain Metrics -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-warehouse"></i>
            </div>
            <div class="stat-label">Total Kuota</div>
            <div class="stat-number">{{ number_format($quotaStats['total']) }}</div>
            <p class="text-muted small mb-0">Available: {{ number_format($quotaStats['available']) }} | Limited: {{ number_format($quotaStats['limited']) }} | Depleted: {{ number_format($quotaStats['depleted']) }}</p>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <div class="stat-label">Forecast Remaining</div>
            <div class="stat-number">{{ number_format($quotaStats['forecast_remaining']) }}</div>
            <p class="text-muted small mb-0">Actual Remaining: {{ number_format($quotaStats['actual_remaining']) }}</p>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-label">PO Bulan Ini</div>
            <div class="stat-number">{{ number_format($poStats['this_month']) }}</div>
            <p class="text-muted small mb-0">Need Shipment: {{ number_format($poStats['need_shipment']) }} | In Transit: {{ number_format($poStats['in_transit']) }}</p>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-truck-loading"></i>
            </div>
            <div class="stat-label">Pengiriman</div>
            <div class="stat-number">{{ number_format($shipmentStats['in_transit']) }}</div>
            <p class="text-muted small mb-0">Delivered: {{ number_format($shipmentStats['delivered']) }} | Pending Receipt: {{ number_format($shipmentStats['pending_receipt']) }}</p>
        </div>
    </div>
</div>
<!-- Welcome & Role Cards -->
<div class="row g-3 mb-4">
    
    <!-- Welcome Card -->
    <div class="col-lg-{{ (Auth::user()->hasPermission('read users') || Auth::user()->hasPermission('read roles')) ? '8' : '12' }}">
        <div class="card welcome-card">
            <div class="card-body">
                <div class="welcome-card__header">
                    <div>
                        <h2 class="welcome-card__title">Hello, {{ Auth::user()->name }}! &#128075;</h2>
                        <p class="welcome-card__subtitle">Kelola perizinan, kuota, dan pengirimanmu langsung dari sini.</p>
                    </div>
                    <div class="welcome-card__last-login">
                        <span class="welcome-card__last-login-label">Last login</span>
                        @if(Auth::user()->last_login_at)
                            <span class="welcome-card__last-login-value">{{ Auth::user()->last_login_at->diffForHumans() }}</span>
                            <span class="welcome-card__last-login-meta">{{ Auth::user()->last_login_at->format('d M Y, H:i') }}</span>
                        @else
                            <span class="welcome-card__last-login-value">First time login</span>
                        @endif
                    </div>
                </div>

                @if(Auth::user()->roles->isNotEmpty())
                <div class="welcome-card__roles">
                    @foreach(Auth::user()->roles as $role)
                        <span class="role-chip">{{ $role->name }}</span>
                    @endforeach
                </div>
                @endif

                @if(Auth::user()->hasPermission('read users') || Auth::user()->hasPermission('read roles') || Auth::user()->hasPermission('read permissions'))
                <div class="welcome-card__actions">
                    <span class="welcome-card__actions-label">Quick actions</span>
                    <div class="quick-action-grid">
                        @if(Auth::user()->hasPermission('read users'))
                            <a href="{{ route('admin.users.index') }}" class="quick-action-chip">
                                <i class="fas fa-users"></i>
                                <span>Manage Users</span>
                            </a>
                        @endif
                        @if(Auth::user()->hasPermission('read roles'))
                            <a href="{{ route('admin.roles.index') }}" class="quick-action-chip">
                                <i class="fas fa-user-tag"></i>
                                <span>Manage Roles</span>
                            </a>
                        @endif
                        @if(Auth::user()->hasPermission('read permissions'))
                            <a href="{{ route('admin.permissions.index') }}" class="quick-action-chip">
                                <i class="fas fa-key"></i>
                                <span>Manage Permissions</span>
                            </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Users by Role -->
    @if(Auth::user()->hasPermission('read users') || Auth::user()->hasPermission('read roles'))
    <div class="col-lg-4">
        <div class="role-panel">
            <div class="role-panel__header">
                <i class="fas fa-chart-pie"></i>
                <span>Users by Role</span>
            </div>
            <div class="role-panel__body">
                @if($usersByRole->isEmpty())
                    <div class="panel-modern__empty">Belum ada data role.</div>
                @else
                    <ul class="role-list">
                        @foreach($usersByRole as $role)
                            <li class="role-item">
                                <span class="role-chip">{{ $role->name }}</span>
                                <span class="role-item__count">{{ $role->users_count }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @endif
<!-- Operational Overview -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="panel-modern h-100">
            <div class="panel-modern__header">
                <h3 class="panel-modern__title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M4.93 19h14.14a1 1 0 00.86-1.5L12.86 4.5a1 1 0 00-1.72 0L4.07 17.5A1 1 0 004.93 19z" />
                    </svg>
                    Quota Alerts
                </h3>
                @if(\Illuminate\Support\Facades\Route::has('admin.quotas.index'))
                    <a href="{{ route('admin.quotas.index') }}" class="panel-modern__link">Lihat semua</a>
                @endif
            </div>
            <div class="panel-modern__body">
                @if($quotaAlerts->isEmpty())
                    <div class="panel-modern__empty">Belum ada alert.</div>
                @else
                    <div class="quota-alerts">
                        @foreach($quotaAlerts as $alert)
                            @php
                                $quotaDetailUrl = \Illuminate\Support\Facades\Route::has('admin.quotas.show')
                                    ? route('admin.quotas.show', $alert)
                                    : null;
                                $totalAllocation = (int) ($alert->total_allocation ?? 0);
                                $forecastRemaining = max(0, (int) ($alert->forecast_remaining ?? 0));
                                $actualRemaining = max(0, (int) ($alert->actual_remaining ?? 0));
                                $calcBase = $totalAllocation > 0 ? $totalAllocation : null;
                                $remainingPercent = $calcBase ? max(0, round(($forecastRemaining / $calcBase) * 100)) : null;
                                $consumed = $calcBase ? max(0, $calcBase - $forecastRemaining) : null;
                                $severity = match(true) {
                                    $alert->status === \App\Models\Quota::STATUS_DEPLETED || ($remainingPercent !== null && $remainingPercent <= 5) => 'critical',
                                    $alert->status === \App\Models\Quota::STATUS_LIMITED || ($remainingPercent !== null && $remainingPercent <= 20) => 'warning',
                                    default => 'normal',
                                };
                            @endphp
                            <div class="quota-item quota-item--{{ $severity }}">
                                <div>
                                    <span class="quota-item__title">{{ $alert->quota_number }}</span>
                                    @if($alert->name)
                                        <div class="quota-meta">{{ $alert->name }}</div>
                                    @endif
                                    <div class="quota-meta">Forecast: {{ number_format($forecastRemaining) }} | Actual: {{ number_format($actualRemaining) }}</div>
                                    <div class="quota-meta">
                                        Consumed: {{ $consumed !== null ? number_format($consumed) : '—' }}
                                        @if($remainingPercent !== null)
                                            • Remaining {{ $remainingPercent }}%
                                        @endif
                                    </div>
                                    <div class="quota-pill">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="12" height="12">
                                            <path d="M10 3a7 7 0 100 14 7 7 0 000-14zm0 2a1 1 0 01.993.883L11 6v4h3a1 1 0 01.117 1.993L14 12h-4a1 1 0 01-.993-.883L9 11V6a1 1 0 011-1z" />
                                        </svg>
                                        <span>{{ ucfirst($alert->status) }}</span>
                                    </div>
                                </div>
                                @if($quotaDetailUrl)
                                    <div class="d-flex flex-column align-items-end gap-2">
                                        <a href="{{ $quotaDetailUrl }}" class="btn-ghost">Detail</a>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel-modern h-100">
            <div class="panel-modern__header">
                <h3 class="panel-modern__title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h8m-9 8h10a2 2 0 002-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Recent Purchase Orders
                </h3>
                @if(\Illuminate\Support\Facades\Route::has('admin.purchase-orders.index'))
                    <a href="{{ route('admin.purchase-orders.index') }}" class="panel-modern__link">Lihat semua</a>
                @endif
            </div>
            <div class="panel-modern__body">
                @if($recentPurchaseOrders->isEmpty())
                    <div class="panel-modern__empty">Belum ada data.</div>
                @else
                    <div class="data-list">
                        @foreach($recentPurchaseOrders as $po)
                            @php
                                $poStatusStyles = [
                                    \App\Models\PurchaseOrder::STATUS_ORDERED => ['label' => 'Ordered', 'class' => 'badge-chip--info'],
                                    \App\Models\PurchaseOrder::STATUS_IN_TRANSIT => ['label' => 'In Transit', 'class' => 'badge-chip--warning'],
                                    \App\Models\PurchaseOrder::STATUS_PARTIAL => ['label' => 'Partial', 'class' => 'badge-chip--warning'],
                                    \App\Models\PurchaseOrder::STATUS_COMPLETED => ['label' => 'Completed', 'class' => 'badge-chip--success'],
                                    \App\Models\PurchaseOrder::STATUS_CANCELLED => ['label' => 'Cancelled', 'class' => 'badge-chip--muted'],
                                    \App\Models\PurchaseOrder::STATUS_DRAFT => ['label' => 'Draft', 'class' => 'badge-chip--muted'],
                                ];
                                $poStatus = $poStatusStyles[$po->status] ?? ['label' => ucfirst($po->status), 'class' => 'badge-chip--muted'];
                            @endphp
                            <div class="data-row data-row--po">
                                <div class="data-cell">
                                    <span class="data-title">{{ $po->po_number }}</span>
                                    <span class="data-sub">{{ $po->order_date?->format('d M') ?? 'Tanpa tanggal' }}</span>
                                </div>
                                <div class="data-cell">
                                    @if($po->product)
                                        <span class="data-link">{{ $po->product->code }}</span>
                                        <span class="data-sub">{{ \Illuminate\Support\Str::limit($po->product->name, 36) }}</span>
                                    @else
                                        <span class="data-sub">Produk tidak tersedia</span>
                                    @endif
                                </div>
                                <div class="data-cell data-cell--status">
                                    <span class="badge-chip {{ $poStatus['class'] }}">{{ $poStatus['label'] }}</span>
                                    @if($po->status_po_display)
                                        <span class="data-sub">{{ $po->status_po_display }}</span>
                                    @endif
                                </div>
                                <div class="data-cell data-cell--qty">
                                    <span class="data-qty">{{ number_format($po->quantity) }}</span>
                                    <span class="data-sub">unit</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel-modern h-100">
            <div class="panel-modern__header">
                <h3 class="panel-modern__title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v7h7l-3-5h-4V7H3zM5 18a2 2 0 104 0 2 2 0 10-4 0zm10 0a2 2 0 104 0 2 2 0 10-4 0z" />
                    </svg>
                    Recent Shipments
                </h3>
                @if(\Illuminate\Support\Facades\Route::has('admin.shipments.index'))
                    <a href="{{ route('admin.shipments.index') }}" class="panel-modern__link">Lihat semua</a>
                @endif
            </div>
            <div class="panel-modern__body">
                @if($recentShipments->isEmpty())
                    <div class="panel-modern__empty">Belum ada data.</div>
                @else
                    <div class="data-list">
                        @foreach($recentShipments as $shipment)
                            @php
                                $statusStyles = [
                                    \App\Models\Shipment::STATUS_IN_TRANSIT => ['label' => 'In Transit', 'class' => 'badge-chip--warning'],
                                    \App\Models\Shipment::STATUS_PARTIAL => ['label' => 'Partial', 'class' => 'badge-chip--info'],
                                    \App\Models\Shipment::STATUS_DELIVERED => ['label' => 'Delivered', 'class' => 'badge-chip--success'],
                                    \App\Models\Shipment::STATUS_PENDING => ['label' => 'Pending', 'class' => 'badge-chip--muted'],
                                    \App\Models\Shipment::STATUS_CANCELLED => ['label' => 'Cancelled', 'class' => 'badge-chip--muted'],
                                ];
                                $statusMeta = $statusStyles[$shipment->status] ?? ['label' => ucfirst($shipment->status), 'class' => 'badge-chip--muted'];
                            @endphp
                            <div class="data-row data-row--shipment">
                                <div class="data-cell">
                                    <span class="data-title">{{ $shipment->shipment_number }}</span>
                                    <span class="data-sub">{{ $shipment->ship_date?->format('d M') ?? 'Tanpa jadwal' }}</span>
                                </div>
                                <div class="data-cell">
                                    @if($shipment->purchaseOrder)
                                        <span class="data-link">{{ $shipment->purchaseOrder->po_number }}</span>
                                        <span class="data-sub">{{ \Illuminate\Support\Str::limit($shipment->purchaseOrder->product?->name, 36) }}</span>
                                    @else
                                        <span class="data-sub">PO tidak tersedia</span>
                                    @endif
                                </div>
                                <div class="data-cell data-cell--status">
                                    <span class="badge-chip {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                                    @if($shipment->eta_date)
                                        <span class="data-sub">ETA {{ $shipment->eta_date->format('d M') }}</span>
                                    @endif
                                </div>
                                <div class="data-cell data-cell--qty">
                                    <span class="data-qty">{{ number_format($shipment->quantity_planned) }}</span>
                                    <span class="data-sub">unit</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-history me-2"></i>Riwayat Kuota Terbaru</h3>
            </div>
            <div class="card-body">
                <ul class="timeline-sm mb-0">
                    @forelse($recentQuotaHistories as $history)
                        <li>
                            <span class="timeline-icon bg-primary"><i class="fas fa-sync"></i></span>
                            <div class="timeline-content">
                                <h6 class="mb-1">{{ ucfirst(str_replace('_', ' ', $history->change_type)) }}</h6>
                                <p class="small mb-1">{{ $history->quota?->quota_number }} &mdash; {{ number_format($history->quantity_change) }} unit</p>
                                <small class="text-muted">{{ $history->occurred_on?->format('d M Y') ?? $history->created_at->format('d M Y H:i') }} | {{ $history->description ?? 'Tidak ada catatan' }}</small>
                            </div>
                        </li>
                    @empty
                        <li class="text-muted">Belum ada riwayat.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Recent Users -->
@if(Auth::user()->hasPermission('read users'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-header-actions">
                    <h3 class="card-title mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16" class="me-2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14c-4.418 0-8 2.239-8 5v1h16v-1c0-2.761-3.582-5-8-5z"/></svg>Recent Users
                    </h3>
                    @if(Auth::user()->hasPermission('create users'))
                    <a href="{{ route('admin.users.create') }}" class="btn btn-add-user">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16" class="me-2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4a4 4 0 100 8 4 4 0 000-8zm8 8h-3m-1 0h-3m-8 4a6 6 0 0112 0v2H4v-2z"/></svg>Add User
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
                                        @if(\Illuminate\Support\Facades\Route::has('admin.admins.show'))
                                            <a href="{{ route('admin.admins.show', $user) }}" class="btn btn-view">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </a>
                                        @endif
                                    @else
                                        @if(\Illuminate\Support\Facades\Route::has('admin.users.show'))
                                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @endif
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



