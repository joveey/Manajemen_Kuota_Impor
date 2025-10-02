@extends('layouts.admin')

@section('title', 'Create Admin')

@section('page-title', 'Create New Admin')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.admins.index') }}">Admins</a></li>
    <li class="breadcrumb-item active">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Admin Information</h3>
            </div>
            <form action="{{ route('admin.admins.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label for="name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}"
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
                               value="{{ old('email') }}"
                               required>
                        @error('email')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">Password <span class="text-danger">*</span></label>
                                <input type="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       id="password" 
                                       name="password" 
                                       required>
                                @error('password')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation" 
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Admin role will be automatically assigned. You can assign additional roles below.
                    </div>

                    <div class="form-group">
                        <label>Assign Additional Roles (Optional)</label>
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
                                               {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}
                                               {{ $role->name === 'admin' ? 'checked disabled' : '' }}>
                                        <label class="custom-control-label" for="role-{{ $role->id }}">
                                            {{ $role->name }}
                                            @if($role->name === 'admin')
                                                <span class="badge badge-primary">Auto-assigned</span>
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
                            <input type="checkbox" 
                                   class="custom-control-input" 
                                   id="is_active" 
                                   name="is_active" 
                                   {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active Admin</label>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Admin
                    </button>
                    <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Information</h3>
            </div>
            <div class="card-body">
                <p><i class="fas fa-info-circle text-info"></i> <strong>Admin Privileges:</strong></p>
                <ul>
                    <li>Full access to admin panel</li>
                    <li>Can manage users, roles, and permissions</li>
                    <li>Cannot be deleted directly</li>
                    <li>Must be converted to user before deletion</li>
                </ul>
                
                <hr>
                
                <p><i class="fas fa-shield-alt text-success"></i> <strong>Security:</strong></p>
                <ul>
                    <li>Password must be at least 8 characters</li>
                    <li>Use strong passwords for admin accounts</li>
                    <li>Admin role is automatically assigned</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
