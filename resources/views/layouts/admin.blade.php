{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Import Control') }} - @yield('title', 'Dashboard')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* Global base font sizing similar to GitHub */
        html { font-size: 14px; }
        :root {
            --sidebar-width: 260px;
            --surface: #f5f7fb;
            --card: #ffffff;
            --stroke: #e4e8f1;
            --primary: #2563eb;
            --primary-soft: rgba(37, 99, 235, 0.12);
            --text: #0f172a;
            --muted: #6b7280;
            /* Global font scale */
            --font-scale: 0.875; /* ~14px base from 16px */
            /* Bootstrap base font override */
            --bs-body-font-size: calc(1rem * var(--font-scale));
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--surface);
            color: var(--text);
        }

        a { text-decoration: none; color: inherit; }

        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
            border-right: 1px solid var(--stroke);
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            gap: 28px;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1030;
            transition: transform 0.3s ease;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .brand-symbol {
            display: flex;
            align-items: center;
            width: 150px;
            margin: 0 auto;
        }

        .brand-symbol img {
            width: 100%;
            height: auto;
            display: block;
        }

        .nav-groups {
            flex: 1;
            overflow-y: auto;
            padding: 0 8px 28px 8px;
        }

        .nav-groups::-webkit-scrollbar {
            width: 4px;
        }

        .nav-groups::-webkit-scrollbar-thumb {
            background: rgba(15, 23, 42, 0.08);
            border-radius: 999px;
        }

        .nav-group {
            margin-bottom: 18px;
            border-radius: 18px;
            padding: 12px 16px;
            background: rgba(148, 163, 184, 0.06);
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .nav-group.is-open {
            background: rgba(37, 99, 235, 0.1);
            box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.16);
        }

        .nav-group:hover:not(.is-open) {
            background: rgba(148, 163, 184, 0.12);
        }

        .nav-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            font-weight: 700;
            margin: 0;
        }

        .nav-group.is-open .nav-title,
        .nav-group.is-current .nav-title {
            color: var(--primary);
        }

        .nav-group__toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            background: transparent;
            border: none;
            padding: 0;
            cursor: pointer;
        }

        .nav-group__toggle:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 4px;
        }

        .nav-group__caret {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            background: rgba(37, 99, 235, 0.16);
            color: var(--primary);
            transition: transform 0.22s ease, background 0.22s ease, color 0.22s ease;
        }

        .nav-group:not(.is-open) .nav-group__caret {
            transform: rotate(-90deg);
            background: transparent;
            color: rgba(148, 163, 184, 0.85);
        }

        .nav-group__body {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 12px;
        }

        .nav-group:not(.is-open) .nav-group__body {
            display: none;
        }

        .nav-group__body .nav-link {
            padding-left: 18px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            color: rgba(30, 41, 59, 0.78);
            transition: all 0.2s ease;
            letter-spacing: 0.01em;
        }

        .nav-link:hover,
        .nav-link:focus-visible {
            background: rgba(37, 99, 235, 0.1);
            color: #1d4ed8;
        }

        .nav-link span:last-child {
            flex: 1;
        }

        .nav-icon {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(148, 163, 184, 0.12);
            color: rgba(30, 41, 59, 0.6);
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .nav-icon i {
            font-size: 15px;
            line-height: 1;
        }

        .nav-link:hover .nav-icon,
        .nav-link:focus-visible .nav-icon,
        .nav-link.active .nav-icon {
            background: rgba(37, 99, 235, 0.18);
            color: #1d4ed8;
            transform: translateY(-1px);
        }

        .nav-link.active {
            background: rgba(37, 99, 235, 0.16);
            color: #1d4ed8;
            font-weight: 700;
        }

        .nav-link-muted {
            background: rgba(148, 163, 184, 0.12);
            color: #334155;
            font-weight: 600;
        }

        .nav-link-muted .nav-icon {
            background: rgba(148, 163, 184, 0.2);
            color: #1f2937;
        }

        .nav-link-muted:hover {
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
        }

        .nav-link-muted:hover .nav-icon {
            background: rgba(37, 99, 235, 0.18);
            color: var(--primary);
        }

        .nav-footer {
            border-top: 1px solid var(--stroke);
            padding-top: 18px;
            display: grid;
            gap: 12px;
        }

        .user-block {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-block img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 12px;
            background: rgba(248, 113, 113, 0.12);
            color: #b91c1c;
            border: none;
            font-size: 12.5px;
            font-weight: 600;
            width: 100%;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .logout-btn:hover {
            background: rgba(248, 113, 113, 0.18);
            transform: translateY(-1px);
        }

        .logout-btn .nav-icon {
            background: rgba(248, 113, 113, 0.2);
            color: #b91c1c;
        }

        .app-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .app-bar {
            min-height: 88px;
            background: #ffffff;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            position: sticky;
            top: 0;
            z-index: 1020;
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 36px -30px rgba(15, 23, 42, 0.4);
        }

        .bar-left { display: flex; flex-direction: column; gap: 12px; }
        .bar-left h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .bar-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: #1d4ed8;
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.18);
        }

        .meta-pill svg {
            width: 14px;
            height: 14px;
        }

        .meta-pill.neutral {
            color: #475569;
            background: rgba(148, 163, 184, 0.1);
            border-color: rgba(148, 163, 184, 0.2);
        }

        .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: '›';
            color: #cbd5f5;
            font-size: 12px;
        }

        .breadcrumb a {
            color: inherit;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #475569;
            font-weight: 600;
        }

        .bar-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .quick-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(37, 99, 235, 0.22);
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 16px 36px -30px rgba(37, 99, 235, 0.82);
        }

        .quick-action svg {
            width: 16px;
            height: 16px;
        }

        .quick-action:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 20px 44px -28px rgba(37, 99, 235, 0.85);
        }

        .search-lite {
            position: relative;
            display: none;
        }

        .search-lite input {
            background: rgba(15, 23, 42, 0.04);
            border: 1px solid var(--stroke);
            border-radius: 10px;
            padding: 8px 12px 8px 36px;
            font-size: 11.5px;
        }

        /* Scale down common form/control text sizes */
        .btn { --bs-btn-font-size: calc(1rem * var(--font-scale)); }
        .form-control, .form-select, .form-label, .form-check-label, .input-group-text {
            font-size: calc(0.95rem * var(--font-scale));
        }

        /* Plugin UIs */
        .dataTable, .table, .select2-container, .select2-selection__rendered,
        .select2-dropdown, .flatpickr-input, .pagination { font-size: 0.875rem; }

        .search-lite svg {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: #cbd5f5;
        }

        .app-content {
            padding: 20px 36px 36px;
        }

        .card,
        .glass-card {
            border-radius: 16px !important;
            border: 1px solid var(--stroke) !important;
            background: var(--card) !important;
            box-shadow: 0 12px 24px -18px rgba(15, 23, 42, 0.25);
        }

        .card-header { background: transparent !important; border-bottom: 1px solid var(--stroke) !important; border-radius: 16px 16px 0 0 !important; }

        .btn { border-radius: 10px !important; font-weight: 600; }

        .table { border-radius: 12px !important; overflow: hidden; }
        .table thead { background: rgba(15, 23, 42, 0.04); }

        @media (max-width: 1024px) {
            :root { --sidebar-width: 220px; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.is-open { transform: translateX(0); }
            .app-main { margin-left: 0; }
            .search-lite { display: block; }
            .quick-action { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php $currentUser = Auth::user(); @endphp
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <span class="brand-symbol">
                    <img src="{{ asset('images/panasonic-logo.svg') }}" alt="Panasonic logo">
                </span>
            </div>

            <nav class="nav-groups">
                @php
                    $overviewActive = request()->routeIs('dashboard') || request()->is('admin/master-data*');
                    $overviewExpand = request()->is('admin/master-data*');

                    $canQuota = $currentUser?->can('read quota');
                    $canPOCreate = $currentUser?->can('create purchase_orders');
                    $canPORead = $currentUser?->can('read purchase_orders');
                    $canReports = $currentUser?->can('read reports');

                    $operationalActive = ($canQuota && (request()->is('admin/quotas*') || request()->is('admin/kuota*'))) ||
                        ($canPOCreate && request()->is('admin/purchase-order/create')) ||
                        ($canPORead && (request()->is('admin/purchase-orders*') || request()->is('admin/purchase-order*') ||
                            request()->is('admin/shipments*') || request()->is('admin/shipment')));

                    $reportsActive = $canReports && (request()->is('admin/reports*') || request()->is('analytics*'));

                    $adminActive = request()->is('admin/users*') || request()->is('admin/roles*') ||
                        request()->is('admin/permissions*') || request()->is('admin/admins*');
                @endphp

                <div class="nav-group {{ $overviewActive ? 'is-current' : '' }} {{ $overviewExpand ? 'is-open' : '' }}" data-nav-group>
                    <button type="button"
                            class="nav-group__toggle"
                            data-nav-toggle
                            aria-expanded="{{ $overviewExpand ? 'true' : 'false' }}"
                            aria-controls="nav-group-overview">
                        <span class="nav-title">Overview</span>
                        <span class="nav-group__caret"><i class="fas fa-chevron-right"></i></span>
                    </button>
                    <div class="nav-group__body" id="nav-group-overview">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fas fa-gauge-high"></i></span>
                            <span>Dashboard</span>
                        </a>
                        @if($currentUser?->can('read master_data'))
                            <a href="{{ route('admin.master-data.index') }}" class="nav-link {{ request()->is('admin/master-data*') ? 'active' : '' }}">
                                <span class="nav-icon"><i class="fas fa-boxes"></i></span>
                                <span>Produk</span>
                            </a>
                            @can('create master_data')
                                <a href="{{ route('admin.master-data.create') }}" class="nav-link {{ request()->routeIs('admin.master-data.create') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-circle-plus"></i></span>
                                    <span>Tambah Produk</span>
                                </a>
                            @endcan
                            
                        @endif
                    </div>
                </div>

                @if($canQuota || $canPOCreate || $canPORead)
                    <div class="nav-group {{ $operationalActive ? 'is-open is-current' : '' }}" data-nav-group>
                        <button type="button"
                                class="nav-group__toggle"
                                data-nav-toggle
                                aria-expanded="{{ $operationalActive ? 'true' : 'false' }}"
                                aria-controls="nav-group-operational">
                            <span class="nav-title">Operasional</span>
                            <span class="nav-group__caret"><i class="fas fa-chevron-right"></i></span>
                        </button>
                        <div class="nav-group__body" id="nav-group-operational">
                            @if($canQuota)
                                <a href="{{ route('admin.quotas.index') }}" class="nav-link {{ request()->is('admin/quotas*') || request()->is('admin/kuota*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-percentage"></i></span>
                                    <span>Manajemen Kuota</span>
                                </a>
                                <a href="{{ route('admin.product-quotas.index') }}" class="nav-link {{ request()->routeIs('admin.product-quotas.*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-link"></i></span>
                                    <span>Mapping Produk-Kuota</span>
                                </a>
                            @endif
                            @if($canPOCreate)
                                <a href="{{ route('admin.purchase-orders.create') }}" class="nav-link {{ request()->routeIs('admin.purchase-orders.create') || request()->routeIs('admin.purchase-order.create') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-plus"></i></span>
                                    <span>Buat Purchase Order</span>
                                </a>
                            @endif
                            @if($canPORead)
                                <a href="{{ route('admin.purchase-orders.index') }}" class="nav-link {{ request()->routeIs('admin.purchase-orders.index') || request()->routeIs('admin.purchase-order.index') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
                                    <span>Daftar Purchase Order</span>
                                </a>
                                <a href="{{ route('admin.shipments.index') }}" class="nav-link {{ request()->routeIs('admin.shipments.index') || request()->is('admin/shipments*') || request()->is('admin/shipment') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-truck"></i></span>
                                    <span>Pengiriman & Receipt</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                @if($canReports)
                    <div class="nav-group {{ $reportsActive ? 'is-open is-current' : '' }}" data-nav-group>
                        <button type="button"
                                class="nav-group__toggle"
                                data-nav-toggle
                                aria-expanded="{{ $reportsActive ? 'true' : 'false' }}"
                                aria-controls="nav-group-reports">
                            <span class="nav-title">Reports</span>
                            <span class="nav-group__caret"><i class="fas fa-chevron-right"></i></span>
                        </button>
                        <div class="nav-group__body" id="nav-group-reports">
                            <a href="{{ route('analytics.index') }}" class="nav-link {{ request()->is('analytics*') ? 'active' : '' }}">
                                <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                                <span>Analytics</span>
                            </a>
                            <a href="{{ route('admin.reports.final') }}" class="nav-link {{ request()->routeIs('admin.reports.final') ? 'active' : '' }}">
                                <span class="nav-icon"><i class="fas fa-file-alt"></i></span>
                                <span>Laporan Gabungan</span>
                            </a>
                        </div>
                    </div>
                @endif

                @if($currentUser?->can('read users') || $currentUser?->can('read roles') || $currentUser?->can('read permissions') || $currentUser?->isAdmin())
                    <div class="nav-group {{ $adminActive ? 'is-open is-current' : '' }}" data-nav-group>
                        <button type="button"
                                class="nav-group__toggle"
                                data-nav-toggle
                                aria-expanded="{{ $adminActive ? 'true' : 'false' }}"
                                aria-controls="nav-group-administration">
                            <span class="nav-title">Administrasi</span>
                            <span class="nav-group__caret"><i class="fas fa-chevron-right"></i></span>
                        </button>
                        <div class="nav-group__body" id="nav-group-administration">
                            @if($currentUser?->can('read users'))
                                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-users"></i></span>
                                    <span>Users</span>
                                </a>
                            @endif
                            @if($currentUser?->can('read roles'))
                                <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->is('admin/roles*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-id-badge"></i></span>
                                    <span>Roles</span>
                                </a>
                            @endif
                            @if($currentUser?->can('read permissions'))
                                <a href="{{ route('admin.permissions.index') }}" class="nav-link {{ request()->is('admin/permissions*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-key"></i></span>
                                    <span>Permissions</span>
                                </a>
                            @endif
                            @if($currentUser?->isAdmin())
                                <a href="{{ route('admin.admins.index') }}" class="nav-link {{ request()->is('admin/admins*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-user-cog"></i></span>
                                    <span>Admin Panel</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </nav>

            <div class="nav-footer">
                <div class="user-block">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($currentUser?->name ?? 'User') }}&background=2563eb&color=fff" alt="Avatar">
                    <div>
                        <strong>{{ $currentUser?->name }}</strong>
                        <div style="font-size: 11px; color: #cbd5f5;">{{ $currentUser?->email }}</div>
                    </div>
                </div>
                <a href="{{ route('profile.edit') }}" class="nav-link nav-link-muted">
                    <span class="nav-icon"><i class="fas fa-user-cog"></i></span>
                    <span>Pengaturan Akun</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-bar">
                <button class="nav-toggle btn btn-light d-lg-none" id="navToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="bar-left">
                    @php
                        $pageTitle = trim($__env->yieldContent('page-title'));
                        if ($pageTitle === '') {
                            $pageTitle = trim($__env->yieldContent('title', 'Dashboard'));
                        }
                        $displayDate = now()->format('l, d F Y');
                    @endphp
                    <h1>{{ $pageTitle }}</h1>
                    <div class="bar-meta">
                        <span class="meta-pill neutral">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ $displayDate }}
                        </span>
                        @if(!empty($currentUser?->name))
                            <span class="meta-pill">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z" />
                                </svg>
                                Halo, {{ $currentUser->name }}
                            </span>
                        @endif
                    </div>
                    @if(trim($__env->yieldContent('breadcrumb')) !== '')
                        <ol class="breadcrumb mb-0">
                            @yield('breadcrumb')
                        </ol>
                    @endif
                </div>
                <div class="bar-actions">
                    @if($currentUser?->can('create purchase_orders'))
                        <a href="{{ route('admin.purchase-orders.create') }}" class="quick-action">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Purchase Order</span>
                        </a>
                    @endif
                    <div class="search-lite">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 11.25a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                        </svg>
                        <input type="search" placeholder="Cari...">
                    </div>
                </div>
            </header>

            <main class="app-content">
                @if (session('status'))
                    <div class="alert alert-success border-0 shadow-sm" role="alert">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const navToggle = document.getElementById('navToggle');
        const sidebar = document.getElementById('sidebar');

        if (navToggle) {
            navToggle.addEventListener('click', () => {
                sidebar.classList.toggle('is-open');
            });
        }

        document.addEventListener('click', (event) => {
            if (window.innerWidth <= 1024 && sidebar.classList.contains('is-open')) {
                if (!sidebar.contains(event.target) && !navToggle.contains(event.target)) {
                    sidebar.classList.remove('is-open');
                }
            }
        });

        document.querySelectorAll('[data-nav-toggle]').forEach(button => {
            const group = button.closest('.nav-group');
            if (!group) {
                return;
            }

            button.setAttribute('aria-expanded', group.classList.contains('is-open') ? 'true' : 'false');

            button.addEventListener('click', () => {
                const nowOpen = group.classList.toggle('is-open');
                button.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
            });
        });

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '{{ session('success') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true
            });
        @endif

        @if(session('warning'))
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: '{{ session('warning') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true
            });
        @endif

        @if(session('info'))
            Swal.fire({
                icon: 'info',
                title: 'Info',
                text: '{{ session('info') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: '<ul style="text-align:left;margin:0;padding-left:18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
                confirmButtonColor: '#ef4444'
            });
        @endif

        $(document).ready(function () {
            if ($.fn.select2) {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }

            if (typeof flatpickr !== 'undefined') {
                flatpickr('.datepicker', {
                    dateFormat: 'Y-m-d',
                    allowInput: true
                });
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
