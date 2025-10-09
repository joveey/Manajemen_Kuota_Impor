{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Quota Monitor') }} — @yield('title', 'Dashboard')</title>

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
        :root {
            --sidebar-width: 240px;
            --surface: #f5f7fb;
            --card: #ffffff;
            --stroke: #e4e8f1;
            --primary: #2563eb;
            --primary-soft: rgba(37, 99, 235, 0.12);
            --text: #0f172a;
            --muted: #6b7280;
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
            gap: 12px;
        }

        .brand-symbol {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--primary);
            display: grid;
            place-items: center;
            color: white;
            font-weight: 700;
        }

        .brand-name {
            font-weight: 700;
            font-size: 18px;
        }

        .brand-subtitle {
            display: block;
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .nav-groups {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
        }

        .nav-groups::-webkit-scrollbar {
            width: 4px;
        }

        .nav-groups::-webkit-scrollbar-thumb {
            background: rgba(15, 23, 42, 0.08);
            border-radius: 999px;
        }

        .nav-group { margin-bottom: 22px; }

        .nav-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            transition: all 0.2s ease;
        }

        .nav-link:hover,
        .nav-link:focus-visible {
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
        }

        .nav-link i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 10px;
            font-size: 14px;
            background: rgba(15, 23, 42, 0.06);
            color: rgba(15, 23, 42, 0.55);
            transition: all 0.2s ease;
        }

        .nav-link:hover i,
        .nav-link:focus-visible i,
        .nav-link.active i {
            background: var(--primary);
            color: white;
        }

        .nav-link.active {
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
            font-weight: 600;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(220, 53, 69, 0.12);
            color: #b91c1c;
            border: none;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: rgba(220, 53, 69, 0.18);
        }

        .app-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .app-bar {
            height: 68px;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid var(--stroke);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 1020;
            backdrop-filter: blur(10px);
        }

        .bar-left { display: flex; flex-direction: column; gap: 4px; }
        .bar-left h1 { font-size: 20px; font-weight: 700; margin: 0; }

        .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 12px;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: '\f105';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .bar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .quick-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            font-size: 13px;
            border: none;
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
            font-size: 13px;
        }

        .search-lite i {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .app-content {
            padding: 32px 36px;
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
                <span class="brand-symbol"><i class="fas fa-signal"></i></span>
                <div>
                    <span class="brand-name">{{ config('app.name', 'Quota Monitor') }}</span>
                    <span class="brand-subtitle">Import Control</span>
                </div>
            </div>

            <nav class="nav-groups">
                <div class="nav-group">
                    <p class="nav-title">Overview</p>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                @if($currentUser?->can('read master_data'))
                    <div class="nav-group">
                        <p class="nav-title">Data Master</p>
                        <a href="{{ route('admin.master-data.index') }}" class="nav-link {{ request()->is('admin/master-data*') ? 'active' : '' }}">
                            <i class="fas fa-layer-group"></i>
                            <span>Produk</span>
                        </a>
                    </div>
                @endif

                @php
                    $canQuota = $currentUser?->can('read quota');
                    $canPOCreate = $currentUser?->can('create purchase_orders');
                    $canPORead = $currentUser?->can('read purchase_orders');
                @endphp
                @if($canQuota || $canPOCreate || $canPORead)
                    <div class="nav-group">
                        <p class="nav-title">Operasional</p>
                        @if($canQuota)
                            <a href="{{ route('admin.quotas.index') }}" class="nav-link {{ request()->is('admin/quotas*') || request()->is('admin/kuota*') ? 'active' : '' }}">
                                <i class="fas fa-chart-pie"></i>
                                <span>Manajemen Kuota</span>
                            </a>
                        @endif
                        @if($canPOCreate)
                            <a href="{{ route('admin.purchase-orders.create') }}" class="nav-link {{ request()->is('admin/purchase-order/create') ? 'active' : '' }}">
                                <i class="fas fa-circle-plus"></i>
                                <span>Buat Purchase Order</span>
                            </a>
                        @endif
                        @if($canPORead)
                            <a href="{{ route('admin.purchase-orders.index') }}" class="nav-link {{ request()->is('admin/purchase-orders') || request()->is('admin/purchase-order') ? 'active' : '' }}">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Daftar Purchase Order</span>
                            </a>
                            <a href="{{ route('admin.shipments.index') }}" class="nav-link {{ request()->is('admin/shipments*') || request()->is('admin/shipment') ? 'active' : '' }}">
                                <i class="fas fa-truck"></i>
                                <span>Pengiriman & Receipt</span>
                            </a>
                        @endif
                    </div>
                @endif

                @if($currentUser?->can('read users') || $currentUser?->can('read roles') || $currentUser?->can('read permissions') || $currentUser?->isAdmin())
                    <div class="nav-group">
                        <p class="nav-title">Administrasi</p>
                        @if($currentUser?->can('read users'))
                            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}">
                                <i class="fas fa-users"></i>
                                <span>Users</span>
                            </a>
                        @endif
                        @if($currentUser?->can('read roles'))
                            <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->is('admin/roles*') ? 'active' : '' }}">
                                <i class="fas fa-user-shield"></i>
                                <span>Roles</span>
                            </a>
                        @endif
                        @if($currentUser?->can('read permissions'))
                            <a href="{{ route('admin.permissions.index') }}" class="nav-link {{ request()->is('admin/permissions*') ? 'active' : '' }}">
                                <i class="fas fa-key"></i>
                                <span>Permissions</span>
                            </a>
                        @endif
                        @if($currentUser?->isAdmin())
                            <a href="{{ route('admin.admins.index') }}" class="nav-link {{ request()->is('admin/admins*') ? 'active' : '' }}">
                                <i class="fas fa-user-cog"></i>
                                <span>Admin Panel</span>
                            </a>
                        @endif
                    </div>
                @endif
            </nav>

            <div class="nav-footer">
                <div class="user-block">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($currentUser?->name ?? 'User') }}&background=2563eb&color=fff" alt="Avatar">
                    <div>
                        <strong>{{ $currentUser?->name }}</strong>
                        <div style="font-size: 12px; color: var(--muted);">{{ $currentUser?->email }}</div>
                    </div>
                </div>
                <a href="{{ route('profile.edit') }}" class="nav-link" style="padding: 10px 12px; background: rgba(15,23,42,0.04); border-radius: 10px;">
                    <i class="fas fa-user-edit"></i>
                    <span>Pengaturan Akun</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-arrow-right-from-bracket"></i>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-bar">
                <button class="nav-toggle btn btn-light d-lg-none" id="navToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="bar-left">
                    @php
                        $pageTitle = trim($__env->yieldContent('page-title'));
                        if ($pageTitle === '') {
                            $pageTitle = trim($__env->yieldContent('title', 'Dashboard'));
                        }
                    @endphp
                    <h1>{{ $pageTitle }}</h1>
                    @if(trim($__env->yieldContent('breadcrumb')) !== '')
                        <ol class="breadcrumb mb-0">
                            @yield('breadcrumb')
                        </ol>
                    @endif
                </div>
                <div class="bar-actions">
                    @if($currentUser?->can('create purchase_orders'))
                        <a href="{{ route('admin.purchase-orders.create') }}" class="quick-action">
                            <i class="fas fa-plus"></i>
                            <span>Purchase Order</span>
                        </a>
                    @endif
                    <div class="search-lite">
                        <i class="fas fa-search"></i>
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
