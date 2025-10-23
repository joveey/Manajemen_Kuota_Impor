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
            overflow-x: hidden;
        }

        body.sidebar-open { overflow: hidden; }

        a { text-decoration: none; color: inherit; }

        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.28s ease;
            z-index: 1025;
        }

        .sidebar-backdrop.is-visible {
            opacity: 1;
            pointer-events: auto;
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
            flex-direction: column;
            align-items: stretch;
            gap: 18px;
            padding: 20px 32px;
            position: sticky;
            top: 0;
            z-index: 1020;
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 36px -30px rgba(15, 23, 42, 0.4);
        }

        .app-bar__masthead {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            width: 100%;
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
            content: '>';
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
            justify-content: flex-end;
            gap: 14px;
        }

        .nav-toggle {
            display: none;
        }

        .app-bar-brand {
            display: none;
        }

        .app-bar-brand img {
            width: 128px;
            height: auto;
            display: block;
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

        /* Scale down common form/control text sizes */
        .btn { --bs-btn-font-size: calc(1rem * var(--font-scale)); }
        .form-control, .form-select, .form-label, .form-check-label, .input-group-text {
            font-size: calc(0.95rem * var(--font-scale));
        }

        /* Plugin UIs */
        .dataTable, .table, .select2-container, .select2-selection__rendered,
        .select2-dropdown, .flatpickr-input, .pagination { font-size: 0.875rem; }

        .app-content {
            padding: 20px 36px 36px;
            width: 100%;
            overflow-x: hidden;
        }

        .page-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .page-header__title {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
        }

        .page-header__subtitle {
            margin-top: 6px;
            color: #64748b;
            font-size: 13px;
            max-width: 540px;
        }

        .page-header__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: flex-end;
        }

        .page-header__button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            text-decoration: none;
            transition: all .2s ease;
        }

        .page-header__button--primary {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 18px 38px -30px rgba(37, 99, 235, .78);
        }

        .page-header__button--primary:hover {
            background: #1d4ed8;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .page-header__button--outline {
            background: rgba(148, 163, 184, .08);
            color: #1f2937;
            border-color: rgba(148, 163, 184, .35);
        }

        .page-header__button--outline:hover {
            background: rgba(148, 163, 184, .14);
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
            .sidebar {
                width: min(300px, 82vw);
                transform: translateX(-100%);
            }
            .sidebar.is-open {
                transform: translateX(0);
                box-shadow: 0 16px 36px -20px rgba(15, 23, 42, 0.42);
            }
            .app-main { margin-left: 0; }
            .app-bar {
                padding: 18px 24px;
                gap: 16px;
            }
            .app-bar__masthead {
                display: grid;
                grid-template-columns: auto 1fr;
                align-items: center;
                gap: 12px;
            }
            .app-bar__masthead .nav-toggle {
                display: inline-flex;
                justify-self: start;
            }
            .app-bar-brand {
                display: flex;
                justify-self: end;
            }
            .bar-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
                gap: 10px;
            }
            .quick-action { display: none; }
        }

        @media (max-width: 640px) {
            html { font-size: 13px; }
            body { padding: 0; }
            .app-shell { flex-direction: column; min-height: 100vh; }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: min(84vw, 320px);
                padding: calc(env(safe-area-inset-top, 0) + 24px) 20px calc(env(safe-area-inset-bottom, 0) + 32px);
                display: flex;
                flex-direction: column;
                gap: 22px;
                background: #ffffff;
                box-shadow: 0 16px 32px -18px rgba(15, 23, 42, 0.45);
                border-right: 1px solid rgba(148, 163, 184, 0.25);
                transform: translateX(-100%);
                overflow-y: auto;
            }
            .sidebar .brand {
                justify-content: flex-start;
                margin: 0;
            }
            .sidebar .brand-symbol { width: 132px; }
            .nav-groups { padding: 0; }
            .app-main { margin: 0; width: 100%; }
            .app-bar {
                gap: 12px;
                padding: 16px 18px;
            }
            .app-bar__masthead {
                grid-template-columns: auto 1fr;
                gap: 10px;
            }
            .app-bar__masthead .nav-toggle {
                margin-left: -4px;
            }
            .bar-left {
                gap: 8px;
            }
            .bar-left h1 { font-size: 20px; }
            .bar-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .bar-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
                align-items: stretch;
                gap: 8px;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }
            .page-header__actions {
                justify-content: flex-start;
            }
            .app-content { padding: 16px 18px 28px; width: 100%; overflow-x: hidden; }
            .sidebar.is-open { transform: translateX(0); }
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
                    $canPORead = $currentUser?->can('read purchase_orders');
                    $canPOCreate = $currentUser?->can('po.create');
                    $canReports = $currentUser?->can('read reports');
                    $canProductCreate = $currentUser?->can('product.create');

                    // Include imports and unmapped pages so Operasional opens on those routes
                    $operationalActive = (
                        request()->routeIs('admin.imports.hs_pk.*') ||
                        request()->routeIs('admin.imports.quotas.*') ||
                        request()->routeIs('admin.openpo.*') ||
                        request()->routeIs('admin.mapping.unmapped') ||
                        request()->routeIs('admin.mapping.unmapped.*') ||
                        ($canQuota && (request()->is('admin/quotas*') || request()->is('admin/kuota*'))) ||
                        ($canPORead && (request()->is('admin/purchase-orders*') ||
                            request()->is('admin/shipments*')))
                    );

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

                @if($canQuota || $canPORead)
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
                            @endif

                            @php
                                $canImportMenu = auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('editor') || auth()->user()?->hasRole('manager');
                    @endphp
                    @if($canImportMenu)
                    {{-- Import menus (kept outside permission blocks to ensure visibility) --}}
                    <a href="{{ route('admin.imports.hs_pk.index') }}" class="nav-link {{ request()->routeIs('admin.imports.hs_pk.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fas fa-file-import"></i></span>
                        <span>Import HS -> PK</span>
                    </a>
                    <a href="{{ route('admin.imports.quotas.index') }}" class="nav-link {{ request()->routeIs('admin.imports.quotas.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fas fa-file-import"></i></span>
                        <span>Import Kuota</span>
                    </a>
                    <a href="{{ route('admin.openpo.form') }}" class="nav-link {{ request()->routeIs('admin.openpo.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fas fa-upload"></i></span>
                        <span>Upload Open PO</span>
                    </a>
                    @endif
                            <a href="{{ route('admin.mapping.unmapped.page') }}" class="nav-link {{ request()->routeIs('admin.mapping.unmapped') || request()->routeIs('admin.mapping.unmapped.*') ? 'active' : '' }}">
                                <span class="nav-icon"><i class="fas fa-puzzle-piece"></i></span>
                                <span>Produk Unmapped</span>
                            </a>
                            <a href="{{ route('admin.mapping.mapped.page') }}" class="nav-link {{ request()->routeIs('admin.mapping.mapped.page') ? 'active' : '' }}">
                                <span class="nav-icon"><i class="fas fa-link"></i></span>
                                <span>Model → HS (Mapped)</span>
                            </a>
                            @if($canPORead)
                                <a href="{{ route('admin.purchase-orders.index') }}" class="nav-link {{ request()->routeIs('admin.purchase-orders.index') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
                                    <span>Daftar Purchase Order</span>
                                </a>
                                <a href="{{ route('admin.shipments.index') }}" class="nav-link {{ request()->routeIs('admin.shipments.index') || request()->is('admin/shipments*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-truck"></i></span>
                                    <span>Pengiriman & Receipt</span>
                                </a>
                            @endif
                            @if($canProductCreate)
                                <a href="{{ route('admin.master.quick_hs.create') }}" class="nav-link {{ request()->routeIs('admin.master.quick_hs.*') ? 'active' : '' }}">
                                    <span class="nav-icon"><i class="fas fa-circle-plus"></i></span>
                                    <span>Tambah Model → HS</span>
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
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div class="app-main">
            <header class="app-bar">
                <div class="app-bar__masthead">
                    <button class="nav-toggle btn btn-light" id="navToggle">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="app-bar-brand">
                        <img src="{{ asset('images/panasonic-logo.svg') }}" alt="Panasonic logo">
                    </span>
                    <div class="bar-actions"></div>
                </div>
                <div class="bar-left">
                    @php
                        $pageTitle = trim($__env->yieldContent('page-title', trim($__env->yieldContent('title', 'Dashboard'))));
                        $displayDate = now()->format('l, d F Y');
                    @endphp
                    <h1>{!! $pageTitle !!}</h1>
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
                                {{ $currentUser->name }}
                            </span>
                         @endif
                    </div>
                    @if(trim($__env->yieldContent('breadcrumb')) !== '')
                        <ol class="breadcrumb mb-0">
                            @yield('breadcrumb')
                        </ol>
                    @endif
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
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        const openSidebar = () => {
            if (!sidebar) return;
            sidebar.classList.add('is-open');
            document.body.classList.add('sidebar-open');
            if (navToggle) {
                navToggle.setAttribute('aria-expanded', 'true');
            }
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.add('is-visible');
            }
        };

        const closeSidebar = () => {
            if (!sidebar) return;
            sidebar.classList.remove('is-open');
            document.body.classList.remove('sidebar-open');
            if (navToggle) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.remove('is-visible');
            }
        };

        if (navToggle) {
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.setAttribute('aria-controls', 'sidebar');
        }

        if (navToggle && sidebar) {
            navToggle.addEventListener('click', () => {
                if (sidebar.classList.contains('is-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        if (sidebar) {
            sidebar.querySelectorAll('a, button.logout-btn').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 1024) {
                        closeSidebar();
                    }
                });
            });
        }

        document.addEventListener('click', (event) => {
            if (!sidebar || window.innerWidth > 1024 || !sidebar.classList.contains('is-open')) {
                return;
            }

            const clickedToggle = navToggle && navToggle.contains(event.target);
            const clickedSidebar = sidebar.contains(event.target);

            if (!clickedSidebar && !clickedToggle) {
                closeSidebar();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
                closeSidebar();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                closeSidebar();
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
