@extends('layouts.admin')

@section('title', 'Permission Details')

@section('page-title', 'Permission Details')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}">Permissions</a></li>
    <li class="breadcrumb-item active">{{ $permission->name }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Permission: <strong>{{ $permission->name }}</strong></h3>
                <div class="card-tools d-flex gap-2">
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-list"></i> Back to List
                    </a>
                    @can('update permissions')
                        <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    @endcan
                    @can('delete permissions')
                        <button type="button" class="btn btn-danger btn-sm" onclick="deletePermission()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    @endcan
                    <form id="delete-form" action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Permission Name:</dt>
                    <dd class="col-sm-9">
                        <span class="badge bg-primary">{{ $permission->name }}</span>
                    </dd>
                    
                    <dt class="col-sm-3">Description:</dt>
                    <dd class="col-sm-9">{{ $permission->description ?? '-' }}</dd>
                    
                    <dt class="col-sm-3">Created At:</dt>
                    <dd class="col-sm-9">{{ $permission->created_at->format('d M Y H:i:s') }}</dd>
                    
                    <dt class="col-sm-3">Updated At:</dt>
                    <dd class="col-sm-9">{{ $permission->updated_at->format('d M Y H:i:s') }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Roles with this Permission</h3>
            </div>
            <div class="card-body">
                @if($roles->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Role Name</th>
                                <th>Description</th>
                                <th>Users Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                            <tr>
                                <td>{{ $loop->iteration + ($roles->currentPage() - 1) * $roles->perPage() }}</td>
                                <td>
                                    <span class="badge bg-success">{{ $role->name }}</span>
                                </td>
                                <td>{{ $role->description ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-info">{{ $role->users->count() }}</span>
                                </td>
                                <td>
                                    @if(Route::has('admin.roles.show'))
                                    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-info btn-sm">
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
                    {{ $roles->links() }}
                </div>
                @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This permission is not assigned to any role yet.
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Statistics</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-user-tag"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Roles</span>
                        <span class="info-box-number">{{ $permission->roles->count() }}</span>
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
function deletePermission() {
    Swal.fire({
        title: 'Are you sure?',
        text: "This permission will be removed from all roles!",
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
