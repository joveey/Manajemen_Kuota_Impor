{{-- resources/views/admin/kuota/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Manajemen Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Manajemen Kuota</li>
@endsection

@push('styles')
<style>
    .quota-page {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    .quota-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }

    .quota-header__title {
        font-size: 26px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .quota-header__subtitle {
        margin-top: 6px;
        color: #64748b;
        font-size: 13px;
        max-width: 520px;
    }

    .quota-actions {
        display: flex;
        gap: 12px;
    }

    .action-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .action-pill--outline {
        background: rgba(148, 163, 184, 0.1);
        color: #1f2937;
        border-color: rgba(148, 163, 184, 0.35);
    }

    .action-pill--outline:hover {
        background: rgba(148, 163, 184, 0.16);
    }

    .action-pill--primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 18px 36px -28px rgba(37, 99, 235, 0.75);
    }

    .action-pill--primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .info-banner {
        display: flex;
        gap: 14px;
        background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 18px 22px;
        color: #334155;
    }

    .info-banner__icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(37, 99, 235, 0.16);
        color: #1d4ed8;
        display: grid;
        place-items: center;
        font-size: 16px;
    }

    .info-banner__title {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .table-shell {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid #e6ebf5;
        box-shadow: 0 32px 64px -48px rgba(15, 23, 42, 0.45);
        overflow: hidden;
    }

    .quota-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .quota-table thead th {
        background: #f8faff;
        padding: 16px 20px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        border-bottom: 1px solid #e6ebf5;
    }

    .quota-table tbody td {
        padding: 16px 20px;
        border-bottom: 1px solid #eef2fb;
        font-size: 13px;
        color: #1f2937;
    }

    .quota-table tbody tr:last-child td {
        border-bottom: none;
    }

    .quota-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.04);
    }

    .status-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .status-chip--available {
        background: rgba(34, 197, 94, 0.16);
        color: #166534;
    }

    .status-chip--limited {
        background: rgba(253, 224, 71, 0.16);
        color: #92400e;
    }

    .status-chip--depleted {
        background: rgba(248, 113, 113, 0.16);
        color: #b91c1c;
    }

    .table-actions {
        display: inline-flex;
        gap: 10px;
    }

    .action-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border: none;
        font-size: 13px;
    }

    .action-icon--view { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
    .action-icon--edit { background: rgba(251, 191, 36, 0.16); color: #d97706; }
    .action-icon--delete { background: rgba(248, 113, 113, 0.16); color: #dc2626; }

    .action-icon:hover {
        transform: translateY(-1px);
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }

    .stat-card-modern {
        border-radius: 20px;
        border: 1px solid #e6ebf5;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 20px;
        box-shadow: 0 28px 52px -46px rgba(15, 23, 42, 0.45);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .stat-card-modern__label {
        font-size: 12px;
        color: #94a3b8;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .stat-card-modern__value {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
    }
</style>
@endpush

@section('content')
<div class="quota-page">
    <div class="quota-header">
        <div>
            <h1 class="quota-header__title">Manajemen Kuota Impor</h1>
            <p class="quota-header__subtitle">Kelola alokasi kuota impor, pantau status forecast dan actual, serta ambil tindakan yang diperlukan.</p>
        </div>
        <div class="quota-actions">
            <a href="{{ route('admin.quotas.export') }}" class="action-pill action-pill--outline">
                <i class="fas fa-file-export"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.quotas.create') }}" class="action-pill action-pill--primary">
                <i class="fas fa-plus"></i>
                Tambah Kuota
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="info-banner" style="background: linear-gradient(135deg, #ecfdf5 0%, #fbfdfa 100%); border-color:#bbf7d0;">
            <div class="info-banner__icon" style="background: rgba(16, 185, 129, 0.18); color:#047857;">
                <i class="fas fa-check"></i>
            </div>
            <div>
                <div class="info-banner__title" style="color:#047857;">Berhasil</div>
                <div style="color:#065f46;">{{ session('status') }}</div>
            </div>
        </div>
    @endif

    <div class="info-banner">
        <div class="info-banner__icon">
            <i class="fas fa-info"></i>
        </div>
        <div>
            <div class="info-banner__title">Informasi Kuota</div>
            <div>Daftar kuota impor berikut akan diperbarui otomatis mengikuti realtime pemakaian. Gunakan tombol aksi untuk melihat detail, memperbarui, atau menghapus data kuota.</div>
        </div>
    </div>

    <div class="table-shell">
        <table class="quota-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>No. Kuota</th>
                    <th>Nama Kuota</th>
                    <th class="text-end">Qty Pemerintah</th>
                    <th class="text-end">Qty Forecast</th>
                    <th class="text-end">Qty Actual</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotas as $quota)
                    @php
                        $statusMap = [
                            \App\Models\Quota::STATUS_AVAILABLE => ['label' => 'Tersedia', 'class' => 'status-chip--available'],
                            \App\Models\Quota::STATUS_LIMITED => ['label' => 'Hampir Habis', 'class' => 'status-chip--limited'],
                            \App\Models\Quota::STATUS_DEPLETED => ['label' => 'Habis', 'class' => 'status-chip--depleted'],
                        ];
                        $status = $statusMap[$quota->status] ?? $statusMap[\App\Models\Quota::STATUS_AVAILABLE];
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $quota->quota_number }}</strong></td>
                        <td>{{ $quota->name }}</td>
                        <td class="text-end">{{ number_format($quota->total_allocation ?? 0) }}</td>
                        <td class="text-end">{{ number_format($quota->forecast_remaining ?? 0) }}</td>
                        <td class="text-end">{{ number_format($quota->actual_remaining ?? 0) }}</td>
                        <td>{{ optional($quota->period_start)->format('M Y') ?? '-' }} - {{ optional($quota->period_end)->format('M Y') ?? '-' }}</td>
                        <td>
                            <span class="status-chip {{ $status['class'] }}">{{ $status['label'] }}</span>
                        </td>
                        <td class="text-end">
                            <div class="table-actions">
                                <a href="{{ route('admin.quotas.show', $quota) }}" class="action-icon action-icon--view" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.quotas.edit', $quota) }}" class="action-icon action-icon--edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.quotas.destroy', $quota) }}" method="POST" onsubmit="return confirm('Hapus kuota ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-icon action-icon--delete" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">Belum ada data kuota.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="stat-grid">
        <div class="stat-card-modern">
            <span class="stat-card-modern__label">Total Kuota</span>
            <span class="stat-card-modern__value">{{ $summary['active_count'] }}</span>
        </div>
        <div class="stat-card-modern">
            <span class="stat-card-modern__label">Total Unit Tersedia</span>
            <span class="stat-card-modern__value">{{ number_format($summary['total_quota']) }}</span>
        </div>
        <div class="stat-card-modern">
            <span class="stat-card-modern__label">Unit Terpakai</span>
            <span class="stat-card-modern__value">{{ number_format($summary['total_quota'] - $summary['forecast_remaining']) }}</span>
        </div>
        <div class="stat-card-modern">
            @php
                $percent = $summary['total_quota'] > 0
                    ? (($summary['total_quota'] - $summary['forecast_remaining']) / $summary['total_quota']) * 100
                    : 0;
            @endphp
            <span class="stat-card-modern__label">Persentase Penggunaan</span>
            <span class="stat-card-modern__value">{{ number_format($percent, 1) }}%</span>
        </div>
    </div>
</div>
@endsection
