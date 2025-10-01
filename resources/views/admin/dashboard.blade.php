@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    
    <!-- Info Boxes -->
    <div class="row">
        
        <!-- Total Users -->
        @can('users.view')
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $totalUsers }}</h3>
                    <p>Total Users</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <a href="#" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        @endcan

        <!-- Active Users -->
        @can('users.view')
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $activeUsers }}</h3>
                    <p>Active Users</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <a href="#" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        @endcan

        <!-- Total Roles -->
        @can('roles.view')
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $totalRoles }}</h3>
                    <p>Active Roles</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <a href="#" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        @endcan

        <!-- Total Permissions -->
        @can('permissions.view')
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $totalPermissions }}</h3>
                    <p>Permissions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-key"></i>
                </div>
                <a href="#" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        @endcan

    </div>

    <div class="row">
        
        <!-- Welcome Card -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">
                        <i class="fas fa-home mr-1"></i>
                        Welcome to Quota Monitoring System
                    </h3>
                </div>
                <div class="card-body">
                    <p>Hello, <strong>{{ Auth::user()->name }}</strong>!</p>
                    <p>Your roles: 
                        @foreach(Auth::user()->roles as $role)
                            <span class="badge badge-info">{{ $role->display_name }}</span>
                        @endforeach
                    </p>
                    <p>Last login: 
                        @if(Auth::user()->last_login_at)
                            {{ Auth::user()->last_login_at->diffForHumans() }}
                            <small class="text-muted">({{ Auth::user()->last_login_at->format('d M Y, H:i') }})</small>
                        @else
                            <span class="text-muted">First time login</span>
                        @endif
                    </p>

                    <hr>

                    <h5>Quick Links</h5>
                    <div class="row">
                        @can('quota.create')
                        <div class="col-md-4">
                            <a href="#" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> Create Quota
                            </a>
                        </div>
                        @endcan

                        @can('po.import')
                        <div class="col-md-4">
                            <a href="#" class="btn btn-info btn-block">
                                <i class="fas fa-file-import"></i> Import PO
                            </a>
                        </div>
                        @endcan

                        @can('reports.view')
                        <div class="col-md-4">
                            <a href="#" class="btn btn-success btn-block">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <!-- Users by Role -->
        @can('users.view')
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">
                        <i class="fas fa-chart-pie mr-1"></i>
                        Users by Role
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th class="text-right">Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($usersByRole as $role)
                                <tr>
                                    <td>
                                        <span class="badge badge-info">{{ $role->display_name }}</span>
                                    </td>
                                    <td class="text-right">
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
        @endcan

    </div>

    <!-- Recent Users -->
    @can('users.view')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">
                        <i class="fas fa-users mr-1"></i>
                        Recent Users
                    </h3>
                    <div class="card-tools">
                        <a href="#" class="btn btn-sm btn-primary">
                            <i class="fas fa-user-plus"></i> Add User
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-valign-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentUsers as $user)
                            <tr>
                                <td>
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&size=32" 
                                         class="img-circle img-size-32 mr-2" alt="User Image">
                                    {{ $user->name }}
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @foreach($user->roles as $role)
                                        <span class="badge badge-info badge-sm">{{ $role->display_name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($user->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->last_login_at)
                                        <small>{{ $user->last_login_at->diffForHumans() }}</small>
                                    @else
                                        <small class="text-muted">Never</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endcan

@endsection

@push('scripts')
<script>
    console.log('Dashboard loaded successfully!');
</script>
@endpush