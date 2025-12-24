<aside class="main-sidebar sidebar-dark-primary elevation-4">
    
    <!-- Brand Logo -->
    <a href="{{ route('dashboard') }}" class="brand-link">
        <img src="https://ui-avatars.com/api/?name=QMS&background=007bff&color=fff&bold=true" 
             alt="Quota Monitor Logo" 
             class="brand-image img-circle elevation-3">
        <span class="brand-text font-weight-light">Quota Monitor</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=random" 
                     class="img-circle elevation-2" 
                     alt="User Image">
            </div>
            <div class="info">
                <a href="{{ route('profile.edit') }}" class="d-block">
                    {{ Auth::user()->name }}
                </a>
            </div>
        </div>

        @php
          $isHsPk       = request()->routeIs('admin.imports.hs_pk.*');
          $isQuotas     = request()->routeIs('admin.imports.quotas.*');
          $isGrImport   = request()->routeIs('admin.imports.gr.*');
          $isUnmapped   = request()->routeIs('admin.mapping.unmapped.*');
          $isMapped     = request()->routeIs('admin.mapping.mapped.page');
          $isQuickModel = request()->routeIs('admin.master.quick_hs.*') || request()->routeIs('admin.mapping.model_hs.*');
          $isModelHsImport = request()->routeIs('admin.imports.model_hs.*');
          $isPoImport   = request()->routeIs('admin.purchase-orders.import.*');
          // pastikan grup Data Preparation membuka saat salah satu aktif
          $operationalOpen = ($isHsPk || $isQuotas || $isGrImport || $isUnmapped || $isMapped || $isQuickModel || $isModelHsImport || ($operationalOpen ?? false));
        @endphp

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                
                <!-- Data Preparation -->
                <li class="nav-item {{ $operationalOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $operationalOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-briefcase"></i>
                        <p>
                            Data Preparation
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('editor'))
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.hs_pk.index') }}" class="nav-link {{ $isHsPk ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Register HS &amp; PK</p>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.quotas.index') }}" class="nav-link {{ $isQuotas ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Register Quota</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.model_hs.index') }}" class="nav-link {{ $isModelHsImport ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Import Model → HS</p>
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a href="{{ route('admin.mapping.unmapped.page') }}" class="nav-link {{ $isUnmapped ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Unmapped Products</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.mapping.mapped.page') }}" class="nav-link {{ $isMapped ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Model &gt; HS (Mapped)</p>
                            </a>
                        </li>
                        @if(auth()->user()?->can('product.create'))
                        <li class="nav-item">
                            <a href="{{ route('admin.master.quick_hs.index') }}" class="nav-link {{ $isQuickModel ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Add Model &gt; HS</p>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>

                <!-- Dashboard -->
                <li class="nav-item {{ request()->routeIs('dashboard') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>
                            Dashboard
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" 
                               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Overview</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Quota Management -->
                @can('read quota')
                @php
                    $quotaMenuOpen = request()->is('admin/quotas*') || request()->is('admin/kuota*');
                @endphp
                <li class="nav-item {{ $quotaMenuOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $quotaMenuOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Quota Management
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.quotas.index') }}" class="nav-link {{ request()->routeIs('admin.quotas.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Quota List</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.quotas.index') }}" class="nav-link {{ request()->routeIs('admin.imports.quotas.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Register Quota</p>
                            </a>
                        </li>
                    </ul>
                </li>
                @endcan

                {{-- Imports --}}
                @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('editor'))
                @php
                    $importsMenuOpen = request()->is('admin/imports/hs-pk*') || request()->is('admin/imports/quotas*');
                @endphp
                <li class="nav-item {{ $importsMenuOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $importsMenuOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-file-import"></i>
                        <p>
                            Imports
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.hs_pk.index') }}" class="nav-link {{ request()->routeIs('admin.imports.hs_pk.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Register HS &amp; PK</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.quotas.index') }}" class="nav-link {{ request()->routeIs('admin.imports.quotas.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Quotas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.imports.gr.index') }}" class="nav-link {{ $isGrImport ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Import GR</p>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                {{-- Mapping --}}
                @php
                    $mappingMenuOpen = request()->is('admin/mapping/*');
                @endphp
                <li class="nav-item {{ $mappingMenuOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $mappingMenuOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-project-diagram"></i>
                        <p>
                            Mapping
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.mapping.unmapped.page') }}" class="nav-link {{ request()->routeIs('admin.mapping.unmapped.page') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Unmapped</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Purchase Orders -->
                @can('read purchase_orders')
                @php
                    $poMenuOpen = request()->is('admin/purchase-orders*') || request()->is('admin/import/po*');
                @endphp
                <li class="nav-item {{ $poMenuOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $poMenuOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>
                            Purchase Orders
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.purchase-orders.index') }}" class="nav-link {{ request()->routeIs('admin.purchase-orders.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>PO List</p>
                            </a>
                        </li>
                        @can('create purchase_orders')
                        <li class="nav-item">
                            <a href="{{ route('admin.purchase-orders.import.form') }}" class="nav-link {{ $isPoImport ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Import PO (Excel)</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan

                {{-- Master Data (Products) removed per request --}}

                <!-- Shipments -->
                @if(Route::has('admin.shipments.index') && Auth::user()->can('read purchase_orders'))
                <li class="nav-item {{ request()->routeIs('admin.shipments.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.shipments.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-truck"></i>
                        <p>
                            Shipments &amp; Receipts
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.shipments.index') }}" class="nav-link {{ request()->routeIs('admin.shipments.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Shipment List</p>
                            </a>
                        </li>
                        @if(Route::has('admin.shipments.create') && Auth::user()->can('create purchase_orders'))
                        <li class="nav-item">
                            <a href="{{ route('admin.shipments.create') }}" class="nav-link {{ request()->routeIs('admin.shipments.create') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Create Shipment</p>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Reports -->
                @can('read reports')
                @php $repOpen = request()->routeIs('analytics.*') || request()->routeIs('admin.reports.final'); @endphp
                <li class="nav-item {{ $repOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $repOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>
                            Reports
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('analytics.index') }}" class="nav-link {{ request()->routeIs('analytics.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Analytics</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.reports.final') }}" class="nav-link {{ request()->routeIs('admin.reports.final') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Combined Report</p>
                            </a>
                        </li>
                    </ul>
                </li>
                @endcan

                <!-- Administration -->
                @if(Auth::user()->can('read permissions') || Auth::user()->can('read roles') || Auth::user()->can('read users') || Auth::user()->isAdmin())
                @php
                    $adminMenuOpen = request()->is('admin/permissions*') || request()->is('admin/roles*') || request()->is('admin/users*') || request()->is('admin/admins*');
                @endphp
                <li class="nav-item {{ $adminMenuOpen ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $adminMenuOpen ? 'active' : '' }}">
                        <i class="nav-icon fas fa-toolbox"></i>
                        <p>
                            Administration
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @if(Auth::user()->can('read permissions'))
                        <li class="nav-item">
                            <a href="{{ route('admin.permissions.index') }}" 
                               class="nav-link {{ request()->is('admin/permissions*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Permissions</p>
                            </a>
                        </li>
                        @endif

                        @if(Auth::user()->can('read roles'))
                        <li class="nav-item">
                            <a href="{{ route('admin.roles.index') }}" 
                               class="nav-link {{ request()->is('admin/roles*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Roles</p>
                            </a>
                        </li>
                        @endif

                        @if(Auth::user()->can('read users'))
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}" 
                               class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Users</p>
                            </a>
                        </li>
                        @endif

                        @if(Auth::user()->isAdmin())
                        <li class="nav-item">
                            <a href="{{ route('admin.admins.index') }}" 
                               class="nav-link {{ request()->is('admin/admins*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Admins</p>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Administrasi: Account Settings -->
                <li class="nav-item">
                    <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <p>Account Settings</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>
