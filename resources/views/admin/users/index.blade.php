@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Users</li>
@endsection

@push('styles')
<style>
    .users-page { display:flex; flex-direction:column; gap:28px; }
    .users-header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:flex-start; }
    .users-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .users-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:540px; }
    .users-actions { display:flex; gap:12px; }
    .users-action {
        display:inline-flex; align-items:center; gap:8px; padding:10px 18px;
        border-radius:14px; font-size:13px; font-weight:600; text-decoration:none;
        transition:all .2s ease; border:1px solid transparent;
    }
    .users-action--primary { background:#2563eb; color:#ffffff; box-shadow:0 18px 36px -30px rgba(37,99,235,.8); }
    .users-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }

    .users-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
    .metric-card {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:18px;
        padding:18px 20px; box-shadow:0 24px 48px -44px rgba(15,23,42,.4);
    }
    .metric-label { font-size:12px; text-transform:uppercase; letter-spacing:.12em; color:#94a3b8; font-weight:600; }
    .metric-value { font-size:28px; font-weight:700; color:#1f2937; margin:4px 0; }
    .metric-caption { font-size:12.5px; color:#64748b; }

    .alert-modern {
        border-radius:14px; padding:14px 18px; font-size:13px; display:flex; align-items:center; gap:10px;
        border:1px solid transparent;
    }
    .alert-modern.success { background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.25); color:#166534; }
    .alert-modern.error { background:rgba(248,113,113,.12); border-color:rgba(248,113,113,.25); color:#b91c1c; }

    .table-shell {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:22px;
        overflow:hidden; box-shadow:0 30px 60px -48px rgba(15,23,42,.45);
    }
    .users-table { width:100%; border-collapse:separate; border-spacing:0; }
    .users-table thead th {
        background:#f8faff; padding:16px 18px; font-size:12px; color:#64748b;
        text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5; white-space:nowrap;
    }
    .users-table tbody td {
        padding:16px 18px; border-bottom:1px solid #eef2fb;
        font-size:13px; color:#1f2937; vertical-align:middle;
    }
    .users-table tbody tr:hover { background:rgba(37,99,235,.04); }

    .role-chip {
        display:inline-flex; align-items:center; gap:6px; padding:6px 12px;
        border-radius:12px; background:rgba(37,99,235,.12); color:#2563eb;
        font-size:12px; font-weight:600; letter-spacing:.04em; text-transform:uppercase;
        margin:0 6px 6px 0;
    }

    .status-pill {
        display:inline-flex; align-items:center; gap:6px; padding:6px 14px;
        border-radius:999px; font-size:12px; font-weight:600;
    }
    .status-pill--active { background:rgba(34,197,94,.16); color:#166534; }
    .status-pill--inactive { background:rgba(248,113,113,.18); color:#b91c1c; }

    .action-pills { display:inline-flex; gap:10px; }
    .action-pill {
        width:34px; height:34px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center;
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
        .users-metrics { grid-template-columns: 1fr; }
        .metric-card { padding:16px 18px; border-radius:16px; }
        .metric-value { font-size:24px; }
        .metric-caption { font-size:11.5px; }
        .users-table thead { display:none; }
        .users-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .users-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .users-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .users-table tbody td:last-child { justify-content:flex-start; }
        .action-pills { width:100%; justify-content:flex-start; }
    }
@media (max-width: 768px) {
        .users-table thead { display:none; }
        .users-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .users-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .users-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .users-table tbody td:last-child { justify-content:flex-start; }
        .action-pills { width:100%; justify-content:flex-start; }
    }

</style>
@endpush

@section('content')
<div class="users-page">
    <div class="users-header">
        <div>
            <h1 class="users-title">Manajemen Pengguna</h1>
            <p class="users-subtitle">
                Kelola akun pengguna non-admin, atur status aktif, dan tetapkan role sesuai kebutuhan operasional.
            </p>
        </div>
        <div class="users-actions">
            @can('create users')
                <a href="{{ route('admin.users.create') }}" class="users-action users-action--primary">
                    <i class="fas fa-plus"></i>
                    Tambah Pengguna
                </a>
            @endcan
        </div>
    </div>

    <div class="users-metrics">
        <div class="metric-card">
            <span class="metric-label">Total Pengguna</span>
            <div class="metric-value">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="metric-caption">Pengguna non-admin yang tercatat dalam sistem.</div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Aktif</span>
            <div class="metric-value text-success">{{ number_format($stats['active'] ?? 0) }}</div>
            <div class="metric-caption">Akun yang saat ini berstatus aktif.</div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Tidak Aktif</span>
            <div class="metric-value text-danger">{{ number_format($stats['inactive'] ?? 0) }}</div>
            <div class="metric-caption">Akun yang dinonaktifkan atau menunggu aktivasi.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-modern success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert-modern error">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="table-shell">
        <table class="users-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td data-label="#">{{ $users->firstItem() + $loop->index }}</td>
                        <td data-label="Nama">{{ $user->name }}</td>
                        <td data-label="Email">{{ $user->email }}</td>
                        <td data-label="Roles">
                            @if($user->roles->count() > 0)
                                @foreach($user->roles as $role)
                                    <span class="role-chip">{{ $role->name }}</span>
                                @endforeach
                            @else
                                <span class="text-muted small">No role</span>
                            @endif
                        </td>
                        <td data-label="Status">
                            <span class="status-pill {{ $user->is_active ? 'status-pill--active' : 'status-pill--inactive' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td data-label="Last Login">{{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i') : 'Never' }}</td>
                        <td data-label="Aksi" class="text-end">
                            <div class="action-pills">
                                @if(Route::has('admin.users.show'))
                                    <a href="{{ route('admin.users.show', $user) }}" class="action-pill action-pill--view" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                                @can('update users')
                                    <a href="{{ route('admin.users.edit', $user) }}" class="action-pill action-pill--edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endcan
                                @can('delete users')
                                    <button type="button" class="action-pill action-pill--delete" onclick="deleteUser({{ $user->id }})" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endcan
                            </div>
                            @can('delete users')
                                <form id="delete-form-{{ $user->id }}" action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-none">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">
                            Belum ada pengguna yang tercatat. @can('create users')<a href="{{ route('admin.users.create') }}">Tambah pengguna pertama</a>@endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-modern">
        {{ $users->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deleteUser(id) {
    Swal.fire({
        title: 'Hapus pengguna?',
        text: 'Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#2563eb',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-form-' + id).submit();
        }
    });
}
</script>
@endpush
