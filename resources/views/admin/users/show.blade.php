@extends('layouts.admin')

@section('title', 'User Details')

@section('page-title', 'User Details')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
    <li class="breadcrumb-item active">{{ $user->name }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User: <strong>{{ $user->name }}</strong></h3>
                <div class="card-tools">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Full Name:</dt>
                    <dd class="col-sm-9">{{ $user->name }}</dd>
                    
                    <dt class="col-sm-3">Email:</dt>
                    <dd class="col-sm-9">{{ $user->email }}</dd>
                    
                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9">
                        @if($user->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </dd>
                    
                    <dt class="col-sm-3">Created At:</dt>
                    <dd class="col-sm-9">{{ $user->created_at->format('d M Y H:i:s') }}</dd>
                    
                    <dt class="col-sm-3">Last Login:</dt>
                    <dd class="col-sm-9">{{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i:s') : 'Never logged in' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Assigned Roles ({{ $user->roles->count() }})</h3>
            </div>
            <div class="card-body">
                @if($user->roles->count() > 0)
                    <div class="row">
                        @foreach($user->roles as $role)
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5>
                                        <span class="badge bg-success">{{ $role->name }}</span>
                                    </h5>
                                    <p class="mb-2">{{ $role->description ?? 'No description' }}</p>
                                    <small class="text-muted">
                                        <i class="fas fa-key"></i> {{ $role->permissions->count() }} permissions
                                    </small>
                                    <br>
                                    @if(Route::has('admin.roles.show'))
                                    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-info mt-2">
                                        <i class="fas fa-eye"></i> View Role
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This user has no roles assigned.
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
                    $allPermissions = $user->roles->flatMap->permissions->unique('id');
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
                        <i class="fas fa-exclamation-triangle"></i> This user has no permissions (no roles assigned).
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
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning btn-block">
                    <i class="fas fa-edit"></i> Edit User
                </a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-block">
                    <i class="fas fa-list"></i> Back to List
                </a>
                <button type="button" class="btn btn-danger btn-block" onclick="deleteUser()">
                    <i class="fas fa-trash"></i> Delete User
                </button>
                
                <form id="delete-form" 
                      action="{{ route('admin.users.destroy', $user) }}" 
                      method="POST" 
                      style="display: none;">
                    @csrf
                    @method('DELETE')
                </form>
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
                        <span class="info-box-number">{{ $user->roles->count() }}</span>
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
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deleteUser() {
    Swal.fire({
        title: 'Are you sure?',
        text: "This user will be permanently deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-form').submit();
        }
    });
}
</script>
@endpush
