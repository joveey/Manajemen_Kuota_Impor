@extends('layouts.admin')

@section('title', 'Admins Management')

@section('page-title', 'Admins')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Admins</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Administrators</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.admins.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Create Admin
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Important:</strong> Admins cannot be deleted directly. You must convert them to regular users first before deletion.
                </div>
                
                @if($admins->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th style="width: 250px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($admins as $admin)
                            <tr>
                                <td>{{ $loop->iteration + ($admins->currentPage() - 1) * $admins->perPage() }}</td>
                                <td>
                                    {{ $admin->name }}
                                    @if($admin->id === auth()->id())
                                        <span class="badge bg-info">You</span>
                                    @endif
                                </td>
                                <td>{{ $admin->email }}</td>
                                <td>
                                    @foreach($admin->roles as $role)
                                        <span class="badge bg-success">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($admin->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $admin->last_login_at ? $admin->last_login_at->format('d M Y H:i') : 'Never' }}</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.admins.show', $admin) }}" 
                                           class="btn btn-info btn-sm" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.admins.edit', $admin) }}" 
                                           class="btn btn-warning btn-sm" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if($admin->id !== auth()->id())
                                        <button type="button" 
                                                class="btn btn-danger btn-sm" 
                                                onclick="convertToUser({{ $admin->id }})"
                                                title="Convert to User">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                        @endif
                                    </div>
                                    
                                    @if($admin->id !== auth()->id())
                                    <form id="convert-form-{{ $admin->id }}" 
                                          action="{{ route('admin.admins.convert', $admin) }}" 
                                          method="POST" 
                                          style="display: none;">
                                        @csrf
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    {{ $admins->links() }}
                </div>
                @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No admins found. 
                    <a href="{{ route('admin.admins.create') }}">Create one now</a>
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
function convertToUser(id) {
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
            document.getElementById('convert-form-' + id).submit();
        }
    });
}
</script>
@endpush
