@extends('layouts.admin')

@section('title', 'Roles Management')

@section('page-title', 'Roles')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Roles</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Roles</h3>
                <div class="card-tools">
                    @if(auth()->user()->hasPermission('create roles'))
                    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Create Role
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if($roles->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Role Name</th>
                                <th>Description</th>
                                <th>Permissions</th>
                                <th>Users</th>
                                <th>Created At</th>
                                <th style="width: 200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                            <tr>
                                <td>{{ $loop->iteration + ($roles->currentPage() - 1) * $roles->perPage() }}</td>
                                <td>
                                    <span class="badge badge-success">{{ $role->name }}</span>
                                </td>
                                <td>{{ $role->description ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-primary">{{ $role->permissions_count }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-info">{{ $role->users_count }}</span>
                                </td>
                                <td>{{ $role->created_at->format('d M Y') }}</td>
                                <td>
                                    <div class="btn-group">
                                        @if(Route::has('admin.roles.show'))
                                        <a href="{{ route('admin.roles.show', $role) }}" 
                                           class="btn btn-info btn-sm" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @endif
                                        @if(auth()->user()->hasPermission('update roles'))
                                        <a href="{{ route('admin.roles.edit', $role) }}" 
                                           class="btn btn-warning btn-sm" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endif
                                        @if(auth()->user()->hasPermission('delete roles') && $role->name !== 'admin' && $role->name !== 'super-admin')
                                        <button type="button" 
                                                class="btn btn-danger btn-sm" 
                                                onclick="deleteRole({{ $role->id }})"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    </div>
                                    
                                    <form id="delete-form-{{ $role->id }}" 
                                          action="{{ route('admin.roles.destroy', $role) }}" 
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
                    {{ $roles->links() }}
                </div>
                @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No roles found. 
                    <a href="{{ route('admin.roles.create') }}">Create one now</a>
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
function deleteRole(id) {
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
            document.getElementById('delete-form-' + id).submit();
        }
    });
}
</script>
@endpush
