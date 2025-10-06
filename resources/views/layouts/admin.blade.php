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

        /* Header Tabs */
        .header-tabs {
            display: flex;
            gap: 8px;
        }

        .header-tab {
            padding: 8px 16px;
            border: 1px solid #e5eaef;
            border-radius: 8px;
            background: white;
            color: #5A6A85;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .header-tab:hover {
            background: #F6F9FC;
            color: #2A3547;
        }

        .header-tab.active {
            background: #ECF2FF;
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .header-tab i {
            font-size: 16px;
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
                <li class="menu-item">
                    <a href="{{ route('dashboard') }}" class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboards</span>
                    </a>
                </li>

                @can('read quota')
                <li class="menu-item {{ request()->is('quota*') ? 'menu-open' : '' }}">
                    <a href="#" class="menu-link" onclick="toggleSubmenu(this); return false;">
                        <i class="fas fa-chart-pie"></i>
                        <span>Quota Management</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="menu-submenu">
                        <li><a href="#" class="submenu-link">Quota List</a></li>
                        @can('create quota')
                        <li><a href="#" class="submenu-link">Create Quota</a></li>
                        @endcan
                    </ul>
                </li>
                @endcan

                @can('read purchase_orders')
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSubmenu(this); return false;">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Purchase Orders</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="menu-submenu">
                        <li><a href="#" class="submenu-link">PO List</a></li>
                        @can('create purchase_orders')
                        <li><a href="#" class="submenu-link">Create PO</a></li>
                        @endcan
                    </ul>
                </li>
                @endcan

                @can('read master_data')
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSubmenu(this); return false;">
                        <i class="fas fa-database"></i>
                        <span>Master Data</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="menu-submenu">
                        <li><a href="#" class="submenu-link">Products</a></li>
                        <li><a href="#" class="submenu-link">Suppliers</a></li>
                        <li><a href="#" class="submenu-link">Categories</a></li>
                    </ul>
                </li>
                @endcan

                @can('read reports')
                <li class="menu-item">
                    <a href="#" class="menu-link" onclick="toggleSubmenu(this); return false;">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="menu-submenu">
                        <li><a href="#" class="submenu-link">Quota Reports</a></li>
                        <li><a href="#" class="submenu-link">PO Reports</a></li>
                        <li><a href="#" class="submenu-link">Analytics</a></li>
                    </ul>
                </li>
                @endcan

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
                    <a href="{{ route('profile.edit') }}" class="menu-link">
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
                    <button class="btn-add" onclick="window.location='{{ route('admin.users.create') }}'">
                        <i class="fas fa-plus"></i>
                        <span>Add</span>
                    </button>
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=5D87FF&color=fff" 
                         alt="User" 
                         class="user-avatar"
                         onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="content-area">
            @if(session('success'))
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> {{ session('info') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <strong><i class="fas fa-ban"></i> Error!</strong>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSubmenu(element) {
            const menuItem = element.parentElement;
            menuItem.classList.toggle('menu-open');
        }
    </script>
    @stack('scripts')
</body>
</html>