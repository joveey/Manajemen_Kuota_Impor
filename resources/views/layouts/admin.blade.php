{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name') }} - @yield('title', 'Dashboard')</title>

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <!-- Flatpickr CSS (for date picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        :root {
            --primary-color: #5D87FF;
            --sidebar-width: 270px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F5F5;
            color: #2A3547;
            font-size: 14px;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid #e5eaef;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #dfe5ef;
            border-radius: 10px;
        }

        /* Logo */
        .sidebar-logo {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e5eaef;
        }

        .sidebar-logo-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .sidebar-logo-text {
            color: #2A3547;
            font-size: 20px;
            font-weight: 700;
        }

        /* Search */
        .sidebar-search {
            padding: 20px 24px;
            border-bottom: 1px solid #e5eaef;
        }

        .search-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 1px solid #e5eaef;
            border-radius: 8px;
            font-size: 14px;
            background: #F9FAFB;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }

        .search-wrapper {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7C8FAC;
            font-size: 14px;
        }

        .search-shortcut {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7C8FAC;
            font-size: 12px;
            font-weight: 500;
        }

        /* Menu */
        .sidebar-menu {
            padding: 12px 0;
        }

        .menu-item {
            list-style: none;
            margin: 2px 12px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: #5A6A85;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-link:hover {
            background: #F6F9FC;
            color: #2A3547;
        }

        .menu-link.active {
            background: #ECF2FF;
            color: var(--primary-color);
        }

        .menu-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 18px;
        }

        .menu-arrow {
            margin-left: auto;
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .menu-item.menu-open .menu-arrow {
            transform: rotate(90deg);
        }

        .menu-submenu {
            display: none;
            padding-left: 32px;
            margin-top: 4px;
        }

        .menu-item.menu-open .menu-submenu {
            display: block;
        }

        .submenu-link {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            color: #5A6A85;
            text-decoration: none;
            font-size: 13px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .submenu-link:hover {
            color: var(--primary-color);
            background: #F6F9FC;
        }

        .submenu-link.active {
            color: var(--primary-color);
            background: #ECF2FF;
        }

        .submenu-link::before {
            content: '';
            width: 5px;
            height: 5px;
            background: #C5D3E8;
            border-radius: 50%;
            margin-right: 10px;
        }

        /* Menu Section */
        .menu-section {
            padding: 24px 24px 8px;
            color: #7C8FAC;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .menu-section-divider {
            height: 1px;
            background: #e5eaef;
            margin: 16px 24px;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e5eaef;
            position: sticky;
            top: 0;
            z-index: 999;
            height: var(--header-height);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            height: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
            font-size: 14px;
        }

        .breadcrumb-item {
            color: #5A6A85;
        }

        .breadcrumb-item a {
            color: #5A6A85;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-color);
        }

        .breadcrumb-item.active {
            color: #2A3547;
            font-weight: 500;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: '/';
            padding: 0 8px;
            color: #C5D3E8;
        }

        /* Header Right */
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-action {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
            border: 1px solid #e5eaef;
            color: #5A6A85;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .header-action:hover {
            background: #F6F9FC;
            color: var(--primary-color);
        }

        .header-action .badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #FA896B;
            border: 2px solid white;
        }

        .btn-add {
            padding: 10px 20px;
            background: #2A3547;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-add:hover {
            background: #1e2732;
            transform: translateY(-1px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #e5eaef;
        }

        /* Content Area */
        .content-area {
            padding: 30px;
        }

        /* Page Title */
        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #2A3547;
            margin: 0;
        }

        /* Cards */
        .card {
            border-radius: 12px;
            border: 1px solid #e5eaef;
            background: white;
            margin-bottom: 24px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e5eaef;
            padding: 20px 24px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #2A3547;
            margin: 0;
        }

        .card-body {
            padding: 24px;
        }

        /* Alerts */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert i {
            margin-right: 8px;
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e5eaef;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 13px;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }

        table.dataTable thead th {
            border-bottom: 2px solid #e5eaef;
            font-weight: 600;
            color: #2A3547;
            padding: 12px;
        }

        table.dataTable tbody td {
            padding: 12px;
            vertical-align: middle;
        }

        /* Select2 Custom Styling */
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #e5eaef;
            border-radius: 6px;
            min-height: 38px;
        }

        /* Flatpickr Custom Styling */
        .flatpickr-input {
            border: 1px solid #e5eaef;
            border-radius: 6px;
            padding: 8px 12px;
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid #e5eaef;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(93, 135, 255, 0.15);
        }

        .form-label {
            font-weight: 500;
            color: #2A3547;
            margin-bottom: 8px;
        }

        /* Buttons */
        .btn {
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: #4570EA;
            border-color: #4570EA;
        }

        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <!-- Logo -->
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <span class="sidebar-logo-text">Quota Monitor</span>
        </div>

        <!-- Search -->
        <div class="sidebar-search">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Search">
                <span class="search-shortcut">⌘ K</span>
            </div>
        </div>

        <!-- Menu -->
        <nav class="sidebar-menu">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <!-- Dashboard -->
                <li class="menu-item">
                    <a href="{{ route('dashboard') }}" class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- MANAJEMEN KUOTA IMPOR Section -->
                <div class="menu-section-divider"></div>
                <li class="menu-section">MANAJEMEN KUOTA IMPOR</li>

                <!-- Master Data -->
                <li class="menu-item">
                    <a href="/admin/master-data" class="menu-link {{ request()->is('admin/master-data*') ? 'active' : '' }}">
                        <i class="fas fa-database"></i>
                        <span>Master Data</span>
                    </a>
                </li>

                <!-- Manajemen Kuota -->
                <li class="menu-item">
                    <a href="/admin/kuota" class="menu-link {{ request()->is('admin/kuota*') ? 'active' : '' }}">
                        <i class="fas fa-chart-pie"></i>
                        <span>Manajemen Kuota</span>
                    </a>
                </li>

                <!-- Input Order (PO) -->
                <li class="menu-item">
                    <a href="/admin/purchase-order/create" class="menu-link {{ request()->is('admin/purchase-order/create') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice"></i>
                        <span>Input Order (PO)</span>
                    </a>
                </li>

                <!-- Daftar Purchase Order -->
                <li class="menu-item">
                    <a href="/admin/purchase-order" class="menu-link {{ request()->is('admin/purchase-order') || request()->is('admin/purchase-order/index') ? 'active' : '' }}">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Daftar Purchase Order</span>
                    </a>
                </li>

                <!-- Pengiriman (Shipment) -->
                <li class="menu-item">
                    <a href="/admin/shipment" class="menu-link {{ request()->is('admin/shipment*') ? 'active' : '' }}">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Pengiriman (Shipment)</span>
                    </a>
                </li>

                <!-- ADMINISTRATION Section -->
                @if(Auth::user()->hasPermission('read permissions') || Auth::user()->hasPermission('read roles') || Auth::user()->hasPermission('read users') || Auth::user()->isAdmin())
                <div class="menu-section-divider"></div>
                <li class="menu-section">ADMINISTRATION</li>
                @endif

                @if(Auth::user()->hasPermission('read permissions'))
                <li class="menu-item">
                    <a href="{{ route('admin.permissions.index') }}" class="menu-link {{ request()->is('admin/permissions*') ? 'active' : '' }}">
                        <i class="fas fa-key"></i>
                        <span>Permissions</span>
                    </a>
                </li>
                @endif

                @if(Auth::user()->hasPermission('read roles'))
                <li class="menu-item">
                    <a href="{{ route('admin.roles.index') }}" class="menu-link {{ request()->is('admin/roles*') ? 'active' : '' }}">
                        <i class="fas fa-user-tag"></i>
                        <span>Roles</span>
                    </a>
                </li>
                @endif

                @if(Auth::user()->hasPermission('read users'))
                <li class="menu-item">
                    <a href="{{ route('admin.users.index') }}" class="menu-link {{ request()->is('admin/users*') ? 'active' : '' }}">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                @endif

                @if(Auth::user()->isAdmin())
                <li class="menu-item">
                    <a href="{{ route('admin.admins.index') }}" class="menu-link {{ request()->is('admin/admins*') ? 'active' : '' }}">
                        <i class="fas fa-user-shield"></i>
                        <span>Admins</span>
                    </a>
                </li>
                @endif

                <!-- SYSTEM Section -->
                <div class="menu-section-divider"></div>
                <li class="menu-section">SYSTEM</li>

                @if(Auth::user()->isAdmin())
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <i class="fas fa-history"></i>
                        <span>Activity Log</span>
                    </a>
                </li>
                @endif

                <li class="menu-item">
                    <a href="{{ route('profile.edit') }}" class="menu-link {{ request()->is('profile*') ? 'active' : '' }}">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            @yield('breadcrumb')
                        </ol>
                    </nav>
                </div>
                <div class="header-right">
                    <a href="#" class="header-action">
                        <i class="far fa-bell"></i>
                    </a>
                    <a href="#" class="header-action">
                        <i class="far fa-comments"></i>
                        <span class="badge"></span>
                    </a>
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=5D87FF&color=fff" 
                         alt="User" 
                         class="user-avatar"
                         onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                         title="Click to logout">
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="content-area">
            @yield('content')
        </main>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
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
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Global Scripts -->
    <script>
        // Toggle Submenu
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            menuItem.classList.toggle('menu-open');
        }

        // Global SweetAlert2 Notification Handler
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '{{ session('success') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '{{ session('error') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('warning'))
            Swal.fire({
                icon: 'warning',
                title: 'Warning!',
                text: '{{ session('warning') }}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
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
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Validation Error!',
                html: '<ul style="text-align: left;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
                confirmButtonColor: '#d33'
            });
        @endif

        // Initialize Select2
        $(document).ready(function() {
            if ($.fn.select2) {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
        });

        // Initialize Flatpickr
        $(document).ready(function() {
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