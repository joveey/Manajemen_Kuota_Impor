@extends('layouts.admin')

@section('title', 'Create Permission')

@section('page-title', 'Create New Permission')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}">Permissions</a></li>
    <li class="breadcrumb-item active">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Permission Information</h3>
            </div>
            <form action="{{ route('admin.permissions.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Important:</strong> Permission name must start with <code>create</code>, <code>read</code>, <code>update</code>, or <code>delete</code>
                        <br>
                        <small>Example: <code>create new admin</code>, <code>read users</code>, <code>update roles</code>, <code>delete permissions</code></small>
                    </div>

                    <div class="form-group">
                        <label for="name">Permission Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               placeholder="e.g., create new admin"
                               value="{{ old('name') }}"
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
                                  rows="3"
                                  placeholder="Brief description of what this permission allows">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Permission
                    </button>
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Permission Guidelines</h3>
            </div>
            <div class="card-body">
                <h6><strong>Valid Prefixes:</strong></h6>
                <ul>
                    <li><code>create</code> - For creating new resources</li>
                    <li><code>read</code> - For viewing/reading resources</li>
                    <li><code>update</code> - For editing/updating resources</li>
                    <li><code>delete</code> - For deleting resources</li>
                </ul>

                <h6 class="mt-3"><strong>Examples:</strong></h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success"></i> <code>create new admin</code></li>
                    <li><i class="fas fa-check text-success"></i> <code>read users list</code></li>
                    <li><i class="fas fa-check text-success"></i> <code>update user profile</code></li>
                    <li><i class="fas fa-check text-success"></i> <code>delete old records</code></li>
                    <li><i class="fas fa-times text-danger"></i> <code>manage users</code> (invalid)</li>
                    <li><i class="fas fa-times text-danger"></i> <code>view dashboard</code> (invalid)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
