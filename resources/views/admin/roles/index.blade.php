{{-- resources/views/admin/roles/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Roles Management')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Roles</li>
@endsection

@push('styles')
<style>
    .roles-page { display:flex; flex-direction:column; gap:28px; }
    .roles-header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:flex-start; }
    .roles-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .roles-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:520px; }
    .roles-actions { display:flex; gap:12px; }
    .roles-action {
        display:inline-flex; align-items:center; gap:8px;
        padding:10px 18px; border-radius:14px; font-size:13px; font-weight:600;
        text-decoration:none; transition:all .2s ease; border:1px solid transparent;
    }
    .roles-action--primary { background:#2563eb; color:#ffffff; box-shadow:0 18px 36px -30px rgba(37,99,235,.8); }
    .roles-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }

    .table-shell {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:22px;
        overflow:hidden; box-shadow:0 30px 60px -48px rgba(15,23,42,.45);
    }
    .roles-table { width:100%; border-collapse:separate; border-spacing:0; }
    .roles-table thead th {
        background:#f8faff; padding:16px 18px; font-size:12px; color:#64748b;
        text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5;
    }
    .roles-table tbody td {
        padding:16px 18px; border-bottom:1px solid #eef2fb;
        font-size:13px; color:#1f2937; vertical-align:middle;
    }
    .roles-table tbody tr:hover { background:rgba(37,99,235,.04); }

    .role-name-chip {
        display:inline-flex; align-items:center; gap:8px;
        padding:6px 12px; border-radius:12px;
        background:rgba(37,99,235,.12); color:#1d4ed8;
        font-weight:600; font-size:12px; letter-spacing:.04em; text-transform:uppercase;
    }

    .count-pill {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:36px; padding:6px 12px; border-radius:10px;
        font-weight:600; font-size:12px;
        background:rgba(226, 232, 240, 0.7); color:#1f2937;
    }

    .roles-table__description { color:#475569; font-size:12.5px; }

    .action-badge {
        width:34px; height:34px; border-radius:12px;
        display:inline-flex; align-items:center; justify-content:center;
        font-size:13px; transition:all .2s ease; border:none;
    }
    .action-badge--view { background:rgba(96,165,250,.16); color:#1d4ed8; }
    .action-badge--edit { background:rgba(250,204,21,.16); color:#b45309; }
    .action-badge--delete { background:rgba(248,113,113,.16); color:#dc2626; }
    .action-badge:hover { transform:translateY(-1px); }

    
    @media (max-width: 640px) {
        .table-shell { border-radius:18px; }
        .roles-table thead { display:none; }
        .roles-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .roles-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .roles-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .roles-table tbody td:last-child { justify-content:flex-start; }
    }
.pagination-modern { display:flex; justify-content:flex-end; margin-top:20px; }
    @media (max-width: 768px) {
        .roles-table thead { display:none; }
        .roles-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .roles-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .roles-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .roles-table tbody td:last-child { justify-content:flex-start; }
    }

</style>
@endpush

@section('content')
<div class="roles-page">
    <div class="roles-header">
        <div>
            <h1 class="roles-title">Manajemen Roles</h1>
            <p class="roles-subtitle">Kelola role dan distribusi permissions untuk mengatur akses pengguna di platform.</p>
        </div>
        <div class="roles-actions">
            @can('create roles')
                <a href="{{ route('admin.roles.create') }}" class="roles-action roles-action--primary">
                    <i class="fas fa-plus"></i>
                    Buat Role Baru
                </a>
            @endcan
        </div>
    </div>

    <div class="table-shell">
        <table class="roles-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Role</th>
                    <th>Deskripsi</th>
                    <th class="text-center">Permissions</th>
                    <th class="text-center">Users</th>
                    <th>Dibuat</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td data-label="#">{{ $roles->firstItem() + $loop->index }}</td>
                        <td data-label="Role">
                            <span class="role-name-chip">{{ $role->name }}</span>
                        </td>
                        <td data-label="Deskripsi">
                            <div class="roles-table__description">{{ $role->description ?? 'Tidak ada deskripsi' }}</div>
                        </td>
                        <td data-label="Permissions" class="text-center">
                            <span class="count-pill">{{ $role->permissions_count }}</span>
                        </td>
                        <td data-label="Users" class="text-center">
                            <span class="count-pill">{{ $role->users_count }}</span>
                        </td>
                        <td data-label="Dibuat">{{ $role->created_at->format('d M Y') }}</td>
                        <td data-label="Aksi" class="text-end">
                            <div class="btn-group" role="group" aria-label="Actions">
                                @if(Route::has('admin.roles.show'))
                                    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-info btn-sm" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                                @can('update roles')
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-warning btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endcan
                                @if(auth()->user()->can('delete roles') && !in_array($role->name, ['admin','super-admin']))
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteRole({{ $role->id }})" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endif
                            </div>
                            <form id="delete-role-{{ $role->id }}" action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Belum ada role yang terdaftar. @can('create roles')<a href="{{ route('admin.roles.create') }}">Buat role baru</a>@endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($roles, 'links'))
        <div class="pagination-modern">
            {{ $roles->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function deleteRole(id) {
        Swal.fire({
            title: 'Hapus role ini?',
            text: 'Role akan dicabut dari semua pengguna yang terkait.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#2563eb',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                document.getElementById('delete-role-' + id).submit();
            }
        });
    }
</script>
@endpush