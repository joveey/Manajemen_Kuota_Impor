@extends('layouts.admin')

@section('title', 'Admin Details')

@section('page-title', 'Admin Details')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.admins.index') }}">Admins</a></li>
    <li class="breadcrumb-item active">{{ $admin->name }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Admin: <strong>{{ $admin->name }}</strong>
                    @if($admin->id === auth()->id())
                        <span class="badge badge-info">You</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.admins.edit', $admin) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Full Name:</dt>
                    <dd class="col-sm-9">{{ $admin->name }}</dd>
                    
                    <dt class="col-sm-3">Email:</dt>
                    <dd class="col-sm-9">{{ $admin->email }}</dd>
                    
                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9">
                        @if($admin->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </dd>
                    
                    <dt class="col-sm-3">Created At:</dt>
                    <dd class="col-sm-9">{{ $admin->created_at->format('d M Y H:i:s') }}</dd>
                    
                    <dt class="col-sm-3">Last Login:</dt>
                    <dd class="col-sm-9">{{ $admin->last_login_at ? $admin->last_login_at->format('d M Y H:i:s') : 'Never logged in' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Assigned Roles ({{ $admin->roles->count() }})</h3>
            </div>
            <div class="card-body">
                @if($admin->roles->count() > 0)
                    <div class="row">
                        @foreach($admin->roles as $role)
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5>
                                        <span class="badge badge-success">{{ $role->name }}</span>
                                        @if($role->name === 'admin')
                                            <span class="badge badge-primary">Primary</span>
                                        @endif
                                    </h5>
                                    <p class="mb-2">{{ $role->description ?? 'No description' }}</p>
                                    <small class="text-muted">
                                        <i class="fas fa-key"></i> {{ $role->permissions->count() }} permissions
                                    </small>
                                    <br>
                                    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-info mt-2">
                                        <i class="fas fa-eye"></i> View Role
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This admin has no roles assigned (This should not happen!).
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Permissions</h3>
            </div>
            <div class="card-body">
                @php
                    $allPermissions = $admin->roles->flatMap->permissions->unique('id');
                @endphp
                
                @if($allPermissions->count() > 0)
                    <div class="row">
                        @foreach($allPermissions as $permission)
                        <div class="col-md-6 mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-key text-primary mr-2"></i>
                                <div>
                                    <strong>{{ $permission->name }}</strong>
                                    @if($permission->description)
                                        <br><small class="text-muted">{{ $permission->description }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This admin has no permissions.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.admins.edit', $admin) }}" class="btn btn-warning btn-block">
                    <i class="fas fa-edit"></i> Edit Admin
                </a>
                <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary btn-block">
                    <i class="fas fa-list"></i> Back to List
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Statistics</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-user-tag"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Roles</span>
                        <span class="info-box-number">{{ $admin->roles->count() }}</span>
                    </div>
                </div>
                
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Permissions</span>
                        <span class="info-box-number">{{ $allPermissions->count() }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($admin->id !== auth()->id())
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Danger Zone</h3>
            </div>
            <div class="card-body">
                <p>Convert this admin to a regular user to remove admin privileges. After conversion, the user can be deleted from the Users page.</p>
                <button type="button" class="btn btn-danger btn-block" onclick="convertToUser()">
                    <i class="fas fa-user-minus"></i> Convert to User
                </button>
                
                <form id="convert-form" 
                      action="{{ route('admin.admins.convert', $admin) }}" 
                      method="POST" 
                      style="display: none;">
                    @csrf
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function convertToUser() {
    Swal.fire({
        title: 'Convert Admin to User?',
        text: "This will remove admin privileges. The user can then be deleted from the Users page.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, convert it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('convert-form').submit();
        }
    });
}
</script>
@endpush
