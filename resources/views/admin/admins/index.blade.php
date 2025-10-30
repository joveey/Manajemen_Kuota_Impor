@extends('layouts.admin')

@section('title', 'Admin Management')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Admin Panel</li>
@endsection

@push('styles')
<style>
    .admins-page { display:flex; flex-direction:column; gap:28px; }
    .admins-header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:flex-start; }
    .admins-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .admins-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:560px; }
    .admins-actions { display:flex; gap:12px; }
    .admins-action {
        display:inline-flex; align-items:center; gap:8px; padding:10px 18px;
        border-radius:14px; font-size:13px; font-weight:600; text-decoration:none;
        transition:all .2s ease; border:1px solid transparent;
    }
    .admins-action--primary { background:#2563eb; color:#ffffff; box-shadow:0 18px 36px -30px rgba(37,99,235,.8); }
    .admins-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }

    .admins-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
    .metric-card { background:#ffffff; border:1px solid #e6ebf5; border-radius:18px; padding:18px 20px; box-shadow:0 24px 48px -44px rgba(15,23,42,.4); }
    .metric-label { font-size:12px; text-transform:uppercase; letter-spacing:.12em; color:#94a3b8; font-weight:600; }
    .metric-value { font-size:28px; font-weight:700; color:#1f2937; margin:4px 0; }
    .metric-caption { font-size:12.5px; color:#64748b; }

    .alert-modern {
        border-radius:14px; padding:14px 18px; font-size:13px; display:flex; align-items:center; gap:10px;
        background:rgba(250,204,21,.16); border:1px solid rgba(250,204,21,.35); color:#b45309;
    }
    .alert-modern.success { background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.25); color:#166534; }
    .alert-modern.error { background:rgba(248,113,113,.12); border-color:rgba(248,113,113,.25); color:#b91c1c; }

    .table-shell { background:#ffffff; border:1px solid #e6ebf5; border-radius:22px; overflow:hidden; box-shadow:0 30px 60px -48px rgba(15,23,42,.45); }

    .data-table { width:100%; border-collapse:separate; border-spacing:0; }
    .data-table thead th {
        background:#f8faff; padding:16px 18px; font-size:12px; color:#64748b;
        text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5;
    }
    .data-table tbody td {
        padding:16px 18px; border-bottom:1px solid #eef2fb; font-size:13px; color:#1f2937; vertical-align:middle;
    }
    .data-table tbody tr:hover { background:rgba(37,99,235,.04); }

    .role-chip {
        display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:12px;
        background:rgba(37,99,235,.12); color:#2563eb;
        font-size:12px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; margin:0 6px 6px 0;
    }
    .status-pill {
        display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:999px; font-size:12px; font-weight:600;
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
    .action-pill--convert { background:rgba(248,113,113,.16); color:#dc2626; }
    .action-pill:hover { transform:translateY(-1px); }

    .empty-state { padding:40px 20px; text-align:center; color:#94a3b8; font-size:13px; }

    .pagination-modern { display:flex; justify-content:flex-end; margin-top:20px; }

    
    @media (max-width: 640px) {
        .admins-metrics { grid-template-columns: 1fr; }
        .metric-card { padding:16px 18px; border-radius:16px; }
        .metric-value { font-size:24px; }
        .metric-caption { font-size:11.5px; }
        .data-table thead { display:none; }
        .data-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .data-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .data-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .data-table tbody td:last-child { justify-content:flex-start; }
        .action-pills { width:100%; justify-content:flex-start; }
    }
@media (max-width: 768px) {
        .data-table thead { display:none; }
        .data-table tbody tr {
            display:block; margin-bottom:18px; border:1px solid #e2e8f0; border-radius:16px; padding:12px 16px;
            box-shadow:0 10px 24px -18px rgba(15,23,42,.35);
        }
        .data-table tbody td {
            display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border:none;
        }
        .data-table tbody td::before {
            content: attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em;
            margin-right:12px;
        }
        .data-table tbody td:last-child { justify-content:flex-start; }
        .action-pills { width:100%; justify-content:flex-start; }
    }
</style>
@endpush

@section('content')
<div class="admins-page">
    <div class="admins-header">
        <div>
            <h1 class="admins-title">Panel Administrator</h1>
            <p class="admins-subtitle">
                Kelola akun administrator dengan akses penuh ke sistem. Konversikan admin ke pengguna biasa sebelum penghapusan untuk menjaga keamanan.
            </p>
        </div>
        <div class="admins-actions">
            <a href="{{ route('admin.admins.create') }}" class="admins-action admins-action--primary">
                <i class="fas fa-user-plus"></i>
                Tambah Admin
            </a>
        </div>
    </div>

    <div class="admins-metrics">
        <div class="metric-card">
            <span class="metric-label">Total Admin</span>
            <div class="metric-value">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="metric-caption">Jumlah admin yang tercatat dalam sistem.</div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Aktif</span>
            <div class="metric-value text-success">{{ number_format($stats['active'] ?? 0) }}</div>
            <div class="metric-caption">Admin dengan status aktif.</div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Inactive</span>
            <div class="metric-value text-danger">{{ number_format($stats['inactive'] ?? 0) }}</div>
            <div class="metric-caption">Admin yang sedang dinonaktifkan.</div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Login Terbaru</span>
            <div class="metric-value">
                {{ $stats['recent_login'] ? \Carbon\Carbon::parse($stats['recent_login'])->diffForHumans() : 'Belum ada' }}
            </div>
            <div class="metric-caption">Aktivitas login admin paling terkini.</div>
        </div>
    </div>

    <div class="alert-modern">
        <i class="fas fa-exclamation-triangle"></i>
        Admin tidak dapat dihapus langsung. Konversikan terlebih dahulu menjadi pengguna biasa, lalu hapus dari menu Users.
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
        <table class="data-table">
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
                @forelse($admins as $admin)
                    <tr>
                        <td data-label="#">{{ $admins->firstItem() + $loop->index }}</td>
                        <td data-label="Nama">
                            {{ $admin->name }}
                            @if($admin->id === auth()->id())
                                <span class="role-chip">You</span>
                            @endif
                        </td>
                        <td data-label="Email">{{ $admin->email }}</td>
                        <td data-label="Roles">
                            @foreach($admin->roles as $role)
                                <span class="role-chip">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td data-label="Status">
                            <span class="status-pill {{ $admin->is_active ? 'status-pill--active' : 'status-pill--inactive' }}">
                                {{ $admin->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td data-label="Last Login">{{ $admin->last_login_at ? $admin->last_login_at->format('d M Y H:i') : 'Never' }}</td>
                        <td data-label="Aksi" class="text-end">
                            <div class="action-pills">
                                @if(Route::has('admin.admins.show'))
                                    <a href="{{ route('admin.admins.show', $admin) }}" class="action-pill action-pill--view" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                                <a href="{{ route('admin.admins.edit', $admin) }}" class="action-pill action-pill--edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if($admin->id !== auth()->id())
                                    <button type="button" class="action-pill action-pill--convert" onclick="convertToUser({{ $admin->id }})" title="Konversi">
                                        <i class="fas fa-user-minus"></i>
                                    </button>
                                @endif
                            </div>
                            @if($admin->id !== auth()->id())
                                <form id="convert-form-{{ $admin->id }}" action="{{ route('admin.admins.convert', $admin) }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">
                            Belum ada admin terdaftar. <a href="{{ route('admin.admins.create') }}">Tambah admin pertama</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-modern">
        {{ $admins->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function convertToUser(id) {
    Swal.fire({
        title: 'Konversi admin menjadi user?',
        text: 'Admin akan kehilangan hak akses penuh dan bisa dihapus dari menu Users.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#2563eb',
        confirmButtonText: 'Ya, konversi',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('convert-form-' + id).submit();
        }
    });
}
</script>
@endpush
