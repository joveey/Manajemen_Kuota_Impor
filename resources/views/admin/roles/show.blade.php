@extends('layouts.admin')

@section('title', 'Role Details')

@section('page-title', 'Role Details')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
    <li class="breadcrumb-item active">{{ $role->name }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Role: <strong>{{ $role->name }}</strong></h3>
                <div class="card-tools">
                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Role Name:</dt>
                    <dd class="col-sm-9">
                        <span class="badge badge-success">{{ $role->name }}</span>
                    </dd>
                    
                    <dt class="col-sm-3">Description:</dt>
                    <dd class="col-sm-9">{{ $role->description ?? '-' }}</dd>
                    
                    <dt class="col-sm-3">Created At:</dt>
                    <dd class="col-sm-9">{{ $role->created_at->format('d M Y H:i:s') }}</dd>
                    
                    <dt class="col-sm-3">Updated At:</dt>
                    <dd class="col-sm-9">{{ $role->updated_at->format('d M Y H:i:s') }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Permissions ({{ $role->permissions->count() }})</h3>
            </div>
            <div class="card-body">
                @if($role->permissions->count() > 0)
                    <div class="row">
                        @foreach($role->permissions as $permission)
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
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No permissions assigned to this role.
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Users with this Role</h3>
            </div>
            <div class="card-body">
                @if($users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->isAdmin())
                                        <a href="{{ route('admin.admins.show', $user) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    @else
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    {{ $users->links() }}
                </div>
                @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No users have this role yet.
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
                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-warning btn-block">
                    <i class="fas fa-edit"></i> Edit Role
                </a>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary btn-block">
                    <i class="fas fa-list"></i> Back to List
                </a>
                @if($role->name !== 'admin' && $role->name !== 'super-admin')
                <button type="button" class="btn btn-danger btn-block" onclick="deleteRole()">
                    <i class="fas fa-trash"></i> Delete Role
                </button>
                @endif
                
                <form id="delete-form" 
                      action="{{ route('admin.roles.destroy', $role) }}" 
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
                    <span class="info-box-icon bg-primary"><i class="fas fa-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Permissions</span>
                        <span class="info-box-number">{{ $role->permissions->count() }}</span>
                    </div>
                </div>
                
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Users</span>
                        <span class="info-box-number">{{ $role->users->count() }}</span>
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
function deleteRole() {
    Swal.fire({
        title: 'Are you sure?',
        text: "This role will be removed from all users!",
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
