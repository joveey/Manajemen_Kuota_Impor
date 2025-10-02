@extends('layouts.admin')

@section('title', 'Permissions Management')

@section('page-title', 'Permissions')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Permissions</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Permissions</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Create Permission
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> All permissions must start with <code>create</code>, <code>read</code>, <code>update</code>, or <code>delete</code>
                </div>
                
                @if($permissions->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Permission Name</th>
                                <th>Description</th>
                                <th>Roles Count</th>
                                <th>Created At</th>
                                <th style="width: 200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permissions as $permission)
                            <tr>
                                <td>{{ $loop->iteration + ($permissions->currentPage() - 1) * $permissions->perPage() }}</td>
                                <td>
                                    <span class="badge badge-primary">{{ $permission->name }}</span>
                                </td>
                                <td>{{ $permission->description ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-info">{{ $permission->roles->count() }}</span>
                                </td>
                                <td>{{ $permission->created_at->format('d M Y') }}</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.permissions.show', $permission) }}" 
                                           class="btn btn-info btn-sm" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.permissions.edit', $permission) }}" 
                                           class="btn btn-warning btn-sm" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm" 
                                                onclick="deletePermission({{ $permission->id }})"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <form id="delete-form-{{ $permission->id }}" 
                                          action="{{ route('admin.permissions.destroy', $permission) }}" 
                                          method="POST" 
                                          style="display: none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    {{ $permissions->links() }}
                </div>
                @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No permissions found. 
                    <a href="{{ route('admin.permissions.create') }}">Create one now</a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deletePermission(id) {
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
            document.getElementById('delete-form-' + id).submit();
        }
    });
}
</script>
@endpush
