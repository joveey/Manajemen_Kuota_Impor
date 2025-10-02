@extends('layouts.admin')

@section('title', 'Create Role')

@section('page-title', 'Create New Role')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
    <li class="breadcrumb-item active">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Role Information</h3>
            </div>
            <form action="{{ route('admin.roles.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Role Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       placeholder="e.g., Manager, Editor, Viewer"
                                       value="{{ old('name') }}"
                                       required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" 
                                       class="form-control @error('description') is-invalid @enderror" 
                                       id="description" 
                                       name="description" 
                                       placeholder="Brief description of this role"
                                       value="{{ old('description') }}">
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Assign Permissions</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                @if($permissions->count() > 0)
                                    <div class="row">
                                        @foreach($permissions as $permission)
                                        <div class="col-md-4">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" 
                                                       class="custom-control-input" 
                                                       id="permission-{{ $permission->id }}" 
                                                       name="permissions[]" 
                                                       value="{{ $permission->id }}"
                                                       {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="permission-{{ $permission->id }}">
                                                    {{ $permission->name }}
                                                    @if($permission->description)
                                                        <br><small class="text-muted">{{ $permission->description }}</small>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                            <i class="fas fa-check-square"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                                            <i class="fas fa-square"></i> Deselect All
                                        </button>
                                    </div>
                                @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No permissions available. 
                                        <a href="{{ route('admin.permissions.create') }}">Create permissions first</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @error('permissions')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Role
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function selectAll() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAll() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}
</script>
@endpush
