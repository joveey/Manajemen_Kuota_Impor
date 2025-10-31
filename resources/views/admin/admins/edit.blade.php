@extends('layouts.admin')

@section('title', 'Edit Admin')

@section('page-title', 'Edit Admin')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.admins.index') }}">Admins</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Admin: <strong>{{ $admin->name }}</strong></h3>
            </div>
            <form action="{{ route('admin.admins.update', $admin) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label for="name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $admin->name) }}"
                               required>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="text-danger">*</span></label>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email', $admin->email) }}"
                               required>
                        @error('email')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       id="password" 
                                       name="password">
                                @error('password')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="form-text text-muted">Leave blank to keep current password</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Admin role will always be maintained. You can assign additional roles below.
                    </div>

                    <div class="form-group">
                        <label>Assign Roles</label>
                        <div class="card">
                            <div class="card-body">
                                @if($roles->count() > 0)
                                    @foreach($roles as $role)
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" 
                                               class="custom-control-input" 
                                               id="role-{{ $role->id }}" 
                                               name="roles[]" 
                                               value="{{ $role->id }}"
                                               {{ in_array($role->id, old('roles', $adminRoles)) ? 'checked' : '' }}
                                               {{ $role->name === 'admin' ? 'checked disabled' : '' }}>
                                        <label class="custom-control-label" for="role-{{ $role->id }}">
                                            {{ $role->name }}
                                            @if($role->name === 'admin')
                                                <span class="badge badge-primary">Required</span>
                                            @endif
                                            @if($role->description)
                                                <br><small class="text-muted">{{ $role->description }}</small>
                                            @endif
                                        </label>
                                    </div>
                                    @endforeach
                                @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No roles available.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" 
                                   class="custom-control-input" 
                                   id="is_active" 
                                   name="is_active" 
                                   value="1"
                                   {{ old('is_active', $admin->is_active) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active Admin</label>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Admin
                    </button>
                    <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="{{ route('admin.admins.show', $admin) }}" class="btn btn-info">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Admin Info</h3>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7">{{ $admin->created_at->format('d M Y') }}</dd>
                    
                    <dt class="col-sm-5">Last Login:</dt>
                    <dd class="col-sm-7">{{ $admin->last_login_at ? $admin->last_login_at->setTimezone('Asia/Jakarta')->format('d-m-Y') : 'Never' }}</dd>
                    
                    <dt class="col-sm-5">Status:</dt>
                    <dd class="col-sm-7">
                        @if($admin->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Danger Zone</h3>
            </div>
            <div class="card-body">
                <p>Convert this admin to a regular user to remove admin privileges.</p>
                @if($admin->id !== auth()->id())
                <button type="button" class="btn btn-danger btn-block" onclick="convertToUser()">
                    <i class="fas fa-user-minus"></i> Convert to User
                </button>
                
                <form id="convert-form" 
                      action="{{ route('admin.admins.convert', $admin) }}" 
                      method="POST" 
                      style="display: none;">
                    @csrf
                </form>
                @else
                <p class="text-muted"><small>You cannot convert your own account.</small></p>
                @endif
            </div>
        </div>
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
