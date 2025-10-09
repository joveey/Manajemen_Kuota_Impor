@extends('layouts.admin')

@section('title', 'Edit Permission')

@section('page-title', 'Edit Permission')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}">Permissions</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Permission: <strong>{{ $permission->name }}</strong></h3>
            </div>
            <form action="{{ route('admin.permissions.update', $permission) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Important:</strong> Permission name must start with <code>create</code>, <code>read</code>, <code>update</code>, or <code>delete</code>
                    </div>

                    <div class="form-group">
                        <label for="name">Permission Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $permission->name) }}"
                               required>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <small class="form-text text-muted">
                            Use lowercase and spaces. Must start with create, read, update, or delete.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" 
                                  name="description" 
                                  rows="3">{{ old('description', $permission->description) }}</textarea>
                        @error('description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Assigned to Roles:</label>
                        <div>
                            @if($permission->roles->count() > 0)
                                @foreach($permission->roles as $role)
                                    <span class="badge badge-info mr-1">{{ $role->name }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">Not assigned to any role</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Permission
                    </button>
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    @if(Route::has('admin.permissions.show'))
                    <a href="{{ route('admin.permissions.show', $permission) }}" class="btn btn-info">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Permission Info</h3>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7">{{ $permission->created_at->format('d M Y H:i') }}</dd>
                    
                    <dt class="col-sm-5">Updated:</dt>
                    <dd class="col-sm-7">{{ $permission->updated_at->format('d M Y H:i') }}</dd>
                    
                    <dt class="col-sm-5">Roles Count:</dt>
                    <dd class="col-sm-7">
                        <span class="badge badge-info">{{ $permission->roles->count() }}</span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
