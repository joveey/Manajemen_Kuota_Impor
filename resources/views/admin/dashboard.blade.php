

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
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.05);
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

    .stat-icon i {
        color: inherit;
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
        background: linear-gradient(135deg, #ffffff 0%, #f6f9ff 100%);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: #0f172a;
        box-shadow: 0 22px 40px -28px rgba(15, 23, 42, 0.45);
    }

    .welcome-card .card-body {
        padding: 28px 32px;
    }

    .welcome-hero {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }

    .welcome-avatar {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        background: rgba(37, 99, 235, 0.12);
        color: #2563eb;
        display: grid;
        place-items: center;
        font-size: 24px;
        box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.2);
    }

    .welcome-heading {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
    }

    .welcome-subtitle {
        margin: 4px 0 18px;
        color: #64748b;
        font-size: 14px;
    }

    .welcome-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
        margin-bottom: 18px;
    }

    .welcome-info-block {
        background: rgba(15, 23, 42, 0.03);
        border-radius: 12px;
        padding: 16px 18px;
    }

    .welcome-info-block .user-info-label {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #64748b;
        opacity: 1;
    }

    .welcome-info-block .user-info-value {
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
    }

    .role-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.12);
        color: #2563eb;
        font-size: 12px;
        font-weight: 600;
        margin: 4px 6px 0 0;
    }

    .divider-light {
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        margin: 18px 0;
    }

    .quick-actions-title {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #0f172a;
    }

    .btn-quick {
        padding: 11px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 13px;
        border: 1px solid rgba(37, 99, 235, 0.18);
        background: rgba(37, 99, 235, 0.08);
        color: #2563eb;
        width: 100%;
        transition: all 0.2s ease;
    }

    .btn-quick:hover {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        transform: translateY(-2px);
    }

    .welcome-note {
        display: block;
        font-size: 12px;
        color: #94a3b8;
        margin-top: 6px;
        font-weight: 500;
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

<!-- Supply Chain Metrics -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-dolly"></i>
            </div>
            <div class="stat-label">Total Kuota</div>
            <div class="stat-number">{{ number_format($quotaStats['total']) }}</div>
            <p class="text-muted small mb-0">Available: {{ number_format($quotaStats['available']) }} | Limited: {{ number_format($quotaStats['limited']) }} | Depleted: {{ number_format($quotaStats['depleted']) }}</p>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-balance-scale"></i>
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

<!-- Operational Overview -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Quota Alerts</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($quotaAlerts as $alert)
                        @php
                            $quotaDetailUrl = \Illuminate\Support\Facades\Route::has('admin.quotas.show')
                                ? route('admin.quotas.show', $alert)
                                : null;
                        @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $alert->quota_number }}</strong>
                                <div class="text-muted small">Forecast: {{ number_format($alert->forecast_remaining ?? 0) }} | Actual: {{ number_format($alert->actual_remaining ?? 0) }}</div>
                            </div>
                            @if($quotaDetailUrl)
                                <a href="{{ $quotaDetailUrl }}" class="btn btn-sm btn-outline-primary">Detail</a>
                            @else
                                <span class="text-muted small">Detail route unavailable</span>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Belum ada alert.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Purchase Orders</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PO</th>
                                <th>Produk</th>
                                <th class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPurchaseOrders as $po)
                                <tr>
                                    <td>
                                        <strong>{{ $po->po_number }}</strong><br>
                                        <small class="text-muted">{{ $po->order_date?->format('d M') }}</small>
                                    </td>
                                    <td>{{ $po->product->code }}</td>
                                    <td class="text-end">{{ number_format($po->quantity) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-truck me-2"></i>Recent Shipments</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Shipment</th>
                                <th>Status</th>
                                <th class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentShipments as $shipment)
                                @php
                                    $statusLabels = [
                                        \App\Models\Shipment::STATUS_IN_TRANSIT => ['label' => 'In Transit', 'class' => 'bg-warning text-dark'],
                                        \App\Models\Shipment::STATUS_PARTIAL => ['label' => 'Partial', 'class' => 'bg-info text-dark'],
                                        \App\Models\Shipment::STATUS_DELIVERED => ['label' => 'Delivered', 'class' => 'bg-success'],
                                        \App\Models\Shipment::STATUS_PENDING => ['label' => 'Pending', 'class' => 'bg-secondary'],
                                    ];
                                    $statusMeta = $statusLabels[$shipment->status] ?? ['label' => ucfirst($shipment->status), 'class' => 'bg-secondary'];
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $shipment->shipment_number }}</strong><br>
                                        <small class="text-muted">{{ $shipment->purchaseOrder->po_number }}</small>
                                    </td>
                                    <td><span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span></td>
                                    <td class="text-end">{{ number_format($shipment->quantity_planned) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
                                        @if(\Illuminate\Support\Facades\Route::has('admin.admins.show'))
                                            <a href="{{ route('admin.admins.show', $user) }}" class="btn btn-view">
                                                <i class="fas fa-eye"></i>
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
