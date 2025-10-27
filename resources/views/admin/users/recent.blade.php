@extends('layouts.admin')

@section('title', 'Pengguna Terbaru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
    <li class="breadcrumb-item active">Pengguna Terbaru</li>
@endsection

@push('styles')
<style>
    .recent-shell { display:flex; flex-direction:column; gap:24px; }
    .recent-hero {
        border-radius:20px;
        padding:24px 28px;
        background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 65%,#3b82f6 100%);
        color:#fff;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:18px;
        flex-wrap:wrap;
    }
    .recent-hero h1 { margin:0; font-size:26px; font-weight:700; }
    .recent-hero p { margin:6px 0 0; opacity:0.85; max-width:520px; }
    .recent-hero__actions { display:flex; gap:12px; flex-wrap:wrap; }
    .recent-btn {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 16px;
        border-radius:12px;
        background:#fff;
        color:#1d4ed8;
        font-weight:600;
        border:none;
        text-decoration:none;
        transition:all .2s ease;
    }
    .recent-btn:hover { background:#e2e8ff; color:#1e3a8a; }

    .recent-card {
        border:1px solid #e3e8f5;
        border-radius:20px;
        background:#fff;
        padding:22px 24px;
        box-shadow:0 22px 48px -40px rgba(15,23,42,0.35);
    }
    .recent-card__header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        flex-wrap:wrap;
        gap:12px;
        margin-bottom:16px;
    }
    .recent-card__title { margin:0; font-size:18px; font-weight:600; color:#0f172a; }
    .recent-card__meta { margin:0; font-size:13px; color:#64748b; }

    .recent-table { width:100%; border-collapse:separate; border-spacing:0; }
    .recent-table thead th {
        text-transform:uppercase;
        letter-spacing:0.08em;
        font-size:11px;
        color:#94a3b8;
        padding:12px 0;
        border-bottom:1px solid #e5e9f6;
    }
    .recent-table tbody td {
        padding:14px 0;
        font-size:14px;
        color:#1f2937;
        border-bottom:1px solid #f1f5f9;
    }
    .recent-table tbody tr:last-child td { border-bottom:none; }

    .user-inline { display:flex; align-items:center; gap:12px; }
    .user-inline img {
        width:40px;
        height:40px;
        border-radius:50%;
        object-fit:cover;
        box-shadow:0 0 0 2px rgba(59,130,246,0.25);
    }
    .role-chip {
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        background:rgba(37,99,235,0.12);
        color:#1d4ed8;
        font-size:12px;
        font-weight:600;
    }
    .status-badge {
        padding:6px 10px;
        border-radius:10px;
        font-size:12px;
        font-weight:600;
        display:inline-block;
    }
    .status-badge--active { background:rgba(16,185,129,0.14); color:#047857; }
    .status-badge--inactive { background:rgba(248,113,113,0.14); color:#b91c1c; }

    .table-actions { display:flex; justify-content:center; gap:10px; }
    .table-actions a {
        width:32px; height:32px;
        border-radius:10px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:rgba(37,99,235,0.12);
        color:#1d4ed8;
        transition:all .2s ease;
    }
    .table-actions a:hover { background:rgba(37,99,235,0.18); }

    @media (max-width: 720px) {
        .recent-card { padding:18px; }
        .recent-table thead { display:none; }
        .recent-table tbody tr { display:block; border:1px solid #e4e9f4; border-radius:16px; padding:16px; margin-bottom:16px; }
        .recent-table tbody td {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            border:none;
            padding:6px 0;
        }
        .recent-table tbody td::before {
            content: attr(data-label);
            font-size:11px;
            font-weight:600;
            letter-spacing:0.06em;
            color:#94a3b8;
            text-transform:uppercase;
            margin-right:12px;
        }
        .table-actions { justify-content:flex-end; }
    }
</style>
@endpush

@section('content')
<div class="recent-shell">
    <section class="recent-hero">
        <div>
            <h1>Pengguna Terbaru</h1>
            <p>Daftar pengguna yang baru dibuat serta status keaktifannya. Gunakan halaman ini untuk memverifikasi hak akses dan aktivitas terakhir.</p>
        </div>
        <div class="recent-hero__actions">
            <a href="{{ route('admin.users.index') }}" class="recent-btn">
                <i class="fas fa-list"></i> Semua Users
            </a>
            @can('create users')
            <a href="{{ route('admin.users.create') }}" class="recent-btn">
                <i class="fas fa-user-plus"></i> Tambah User
            </a>
            @endcan
        </div>
    </section>

    <section class="recent-card">
        <div class="recent-card__header">
            <h2 class="recent-card__title">{{ $recentUsers->perPage() }} Pengguna terakhir</h2>
            <p class="recent-card__meta">Urutan berdasarkan tanggal dibuat (terbaru terlebih dahulu)</p>
        </div>
        @if($recentUsers->isEmpty())
            <div class="text-center text-muted py-4">Belum ada pengguna yang tercatat.</div>
        @else
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                        <th>Last Login</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentUsers as $user)
                    <tr>
                        <td data-label="Nama">
                            <div class="user-inline">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=0EA5E9&color=fff" alt="{{ $user->name }}">
                                <span>{{ $user->name }}</span>
                            </div>
                        </td>
                        <td data-label="Email">{{ $user->email }}</td>
                        <td data-label="Role">
                            @forelse($user->roles as $role)
                                <span class="role-chip">{{ $role->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                        <td data-label="Status">
                            <span class="status-badge {{ $user->is_active ? 'status-badge--active' : 'status-badge--inactive' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td data-label="Terdaftar">
                            <small class="text-muted">{{ optional($user->created_at)->format('d M Y H:i') ?? '-' }}</small>
                        </td>
                        <td data-label="Last Login">
                            <small class="text-muted">
                                {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                            </small>
                        </td>
                        <td data-label="Aksi">
                            <div class="table-actions">
                                @if($user->isAdmin())
                                    @if(Route::has('admin.admins.show'))
                                        <a href="{{ route('admin.admins.show', $user) }}" title="Detail Admin">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                @else
                                    @if(Route::has('admin.users.show'))
                                        <a href="{{ route('admin.users.show', $user) }}" title="Detail User">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $recentUsers->links() }}
        </div>
        @endif
    </section>
</div>
@endsection
