@extends('layouts.admin')

@section('title', 'Create User')

@section('page-title', 'Create New User')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
    <li class="breadcrumb-item active">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Information</h3>
            </div>
            <form action="{{ route('admin.users.store') }}" method="POST">
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
                                               {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="role-{{ $role->id }}">
                                            {{ $role->name }}
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
                                   {{ old('is_active', 1) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active User</label>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
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
                <p><i class="fas fa-info-circle text-info"></i> <strong>Note:</strong></p>
                @if(auth()->user()->hasRole('admin'))
                    <ul>
                        <li>As an Administrator, you can assign the <strong>admin</strong> role directly here.</li>
                        <li>Password must be at least 8 characters</li>
                        <li>Users can be assigned multiple roles</li>
                    </ul>
                @else
                    <ul>
                        <li>Users created here will NOT have admin privileges</li>
                        <li>Password must be at least 8 characters</li>
                        <li>Users can be assigned multiple roles</li>
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
