@extends('layouts.admin')

@section('title', 'Manajemen Permissions')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Permissions</li>
@endsection

@push('styles')
<style>
    .permissions-page { display:flex; flex-direction:column; gap:28px; }
    .permissions-header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:flex-start; }
    .permissions-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .permissions-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:540px; }
    .permissions-actions { display:flex; gap:12px; }
    .permissions-action {
        display:inline-flex; align-items:center; gap:8px; padding:10px 18px;
        border-radius:14px; font-size:13px; font-weight:600; text-decoration:none;
        transition:all .2s ease; border:1px solid transparent;
    }
    .permissions-action--primary { background:#2563eb; color:#ffffff; box-shadow:0 18px 36px -30px rgba(37,99,235,.8); }
    .permissions-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }

    .permissions-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; }
    .perm-metric {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:18px;
        padding:18px 20px; box-shadow:0 24px 48px -44px rgba(15,23,42,.4);
    }
    .perm-metric__label { font-size:12px; text-transform:uppercase; letter-spacing:.12em; color:#94a3b8; font-weight:600; }
    .perm-metric__value { font-size:26px; font-weight:700; color:#1f2937; margin:4px 0; }
    .perm-metric__caption { font-size:12.5px; color:#64748b; }

    .alert-tip {
        padding:16px 20px; border-radius:16px; border:1px dashed rgba(37,99,235,.45);
    .alert-modern {
        border-radius:14px; padding:14px 18px; font-size:13px; display:flex; align-items:center; gap:10px;
        border:1px solid transparent;
    }
    .alert-modern.success { background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.25); color:#166534; }
    .alert-modern.error { background:rgba(248,113,113,.12); border-color:rgba(248,113,113,.25); color:#b91c1c; }
        background:rgba(37,99,235,.08); color:#1d4ed8; font-size:13px; display:flex; gap:12px; align-items:center;
    }

    .table-shell {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:22px;
        overflow:hidden; box-shadow:0 30px 60px -48px rgba(15,23,42,.45);
    }
    .permissions-table { width:100%; border-collapse:separate; border-spacing:0; }
    .permissions-table thead th {
        background:#f8faff; padding:16px 18px; font-size:12px; color:#64748b;
        text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5;
    }
    .permissions-table tbody td {
        padding:16px 18px; border-bottom:1px solid #eef2fb; font-size:13px; color:#1f2937;
    }
    .permissions-table tbody tr:hover { background:rgba(37,99,235,.04); }

    .perm-name-chip {
        display:inline-flex; align-items:center; gap:8px;
        padding:6px 12px; border-radius:12px;
        background:rgba(37,99,235,.12); color:#2563eb;
        font-size:12px; font-weight:600; letter-spacing:.04em;
    }

    .count-pill {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:38px; padding:6px 12px; border-radius:10px;
        font-weight:600; font-size:12px;
        background:rgba(226,232,240,.7); color:#1f2937;
    }

    .action-pills { display:inline-flex; gap:10px; }
    .action-pill {
        width:34px; height:34px; border-radius:12px;
        display:inline-flex; align-items:center; justify-content:center;
        font-size:13px; border:none; transition:all .2s ease;
    }
    .action-pill--view { background:rgba(96,165,250,.16); color:#1d4ed8; }
    .action-pill--edit { background:rgba(250,204,21,.16); color:#b45309; }
    .action-pill--delete { background:rgba(248,113,113,.16); color:#dc2626; }
    .action-pill:hover { transform:translateY(-1px); }

    .empty-state {
        padding:40px 20px; text-align:center; color:#94a3b8; font-size:13px;
    }

    .pagination-modern { display:flex; justify-content:flex-end; margin-top:20px; }
    
    @media (max-width: 640px) {
        .permissions-metrics { grid-template-columns: 1fr; }
        .perm-metric { padding:16px 18px; border-radius:16px; }
        .perm-metric__value { font-size:24px; }
        .perm-metric__caption { font-size:11.5px; }
        .permissions-table thead { display:none; }
        .permissions-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .permissions-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .permissions-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .permissions-table tbody td:last-child { justify-content:flex-start; }
        .action-pills { width:100%; justify-content:flex-start; }
    }
@media (max-width: 768px) {
        .permissions-table thead { display:none; }
        .permissions-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .permissions-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .permissions-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .permissions-table tbody td:last-child { justify-content:flex-start; }
        .action-pills { width:100%; justify-content:flex-start; }
    }

</style>
@endpush

@section('content')
<div class="permissions-page">
    <div class="permissions-header">
        <div>
            <h1 class="permissions-title">Manajemen Permissions</h1>
            <p class="permissions-subtitle">
                Atur hak akses granular untuk setiap fitur. Gunakan prefiks <code>create</code>, <code>read</code>,
                <code>update</code>, atau <code>delete</code> agar konsisten.
            </p>
        </div>
        <div class="permissions-actions">
            @can('create permissions')
                <a href="{{ route('admin.permissions.create') }}" class="permissions-action permissions-action--primary">
                    <i class="fas fa-plus"></i>
                    Buat Permission
                </a>
            @endcan
        </div>
    </div>

    <div class="permissions-metrics">
        <div class="perm-metric">
            <span class="perm-metric__label">Total Permissions</span>
            <div class="perm-metric__value">{{ number_format($stats['total'] ?? 0) }}</div>
            <p class="perm-metric__caption">Seluruh permission yang tersedia di aplikasi.</p>
        </div>
        <div class="perm-metric">
            <span class="perm-metric__label">Create</span>
            <div class="perm-metric__value text-primary">{{ number_format($stats['create'] ?? 0) }}</div>
            <p class="perm-metric__caption">Hak akses untuk membuat data.</p>
        </div>
        <div class="perm-metric">
            <span class="perm-metric__label">Read</span>
            <div class="perm-metric__value text-primary">{{ number_format($stats['read'] ?? 0) }}</div>
            <p class="perm-metric__caption">Hak akses untuk melihat data.</p>
        </div>
        <div class="perm-metric">
            <span class="perm-metric__label">Update / Delete</span>
            <div class="perm-metric__value text-primary">{{ number_format(($stats['update'] ?? 0) + ($stats['delete'] ?? 0)) }}</div>
            <p class="perm-metric__caption">Gabungan izin pengubahan dan penghapusan.</p>
        </div>
    </div>

    <div class="alert-tip">
        <i class="fas fa-info-circle"></i>
        Pastikan penamaan permission mengikuti pola CRUD agar mudah dipetakan dengan role.
    </div>

    @if(session('success'))
        <div class="alert-modern success mt-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert-modern error mt-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="table-shell">
        <table class="permissions-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Permission</th>
                    <th>Deskripsi</th>
                    <th class="text-center">Jumlah Role</th>
                    <th>Dibuat</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    <tr>
                        <td data-label="#">{{ $permissions->firstItem() + $loop->index }}</td>
                        <td data-label="Permission"><span class="perm-name-chip">{{ $permission->name }}</span></td>
                        <td data-label="Deskripsi">{{ $permission->description ?? '-' }}</td>
                        <td data-label="Jumlah Role" class="text-center">
                            <span class="count-pill">{{ $permission->roles->count() }}</span>
                        </td>
                        <td data-label="Dibuat">{{ $permission->created_at->format('d M Y') }}</td>
                        <td data-label="Aksi" class="text-end">
                            <div class="action-pills">
                                @if(Route::has('admin.permissions.show'))
                                    <a href="{{ route('admin.permissions.show', $permission) }}" class="action-pill action-pill--view" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                                @can('update permissions')
                                    <a href="{{ route('admin.permissions.edit', $permission) }}" class="action-pill action-pill--edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endcan
                                @can('delete permissions')
                                    <button type="button" class="action-pill action-pill--delete" onclick="deletePermission({{ $permission->id }})" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endcan
                            </div>
                            @can('delete permissions')
                                <form id="delete-permission-{{ $permission->id }}" action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="d-none">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">
                            Belum ada permission yang diset. @can('create permissions')<a href="{{ route('admin.permissions.create') }}">Buat permission baru</a>@endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($permissions, 'links'))
        <div class="pagination-modern">
            {{ $permissions->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deletePermission(id) {
    Swal.fire({
        title: 'Hapus permission?',
        text: 'Aksi ini akan mencabut akses dari role terkait.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#2563eb',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-permission-' + id).submit();
        }
    });
}
</script>
@endpush
