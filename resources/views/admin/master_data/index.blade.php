{{-- resources/views/admin/master_data/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Master Data Produk')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Master Data</li>
@endsection

@push('styles')
<style>
    .product-page { display:flex; flex-direction:column; gap:28px; }
    .product-header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:flex-start; }
    .product-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .product-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:520px; }
    .product-actions { display:flex; gap:12px; }
    .product-action {
        display:inline-flex; align-items:center; gap:8px;
        padding:10px 18px; border-radius:14px; font-size:13px; font-weight:600;
        text-decoration:none; transition:all .2s ease; border:1px solid transparent;
    }
    .product-action--primary { background:#2563eb; color:#ffffff; box-shadow:0 18px 38px -32px rgba(37,99,235,.78); }
    .product-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }

    .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
    .summary-card {
        border-radius:18px; border:1px solid #e6ebf5;
        background:linear-gradient(135deg,#ffffff 0%,#f8fafc 100%);
        padding:20px; box-shadow:0 24px 48px -44px rgba(15,23,42,.45);
        display:flex; flex-direction:column; gap:6px;
    }
    .summary-card__label { font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:.12em; }
    .summary-card__value { font-size:24px; font-weight:700; color:#0f172a; }

    .info-banner {
        display:flex; gap:14px;
        background:linear-gradient(135deg,#ecfeff 0%, #eff6ff 100%);
        border:1px solid #e2e8f0; border-radius:20px;
        padding:18px 22px; color:#334155;
    }
    .info-banner__icon {
        width:38px; height:38px; border-radius:12px;
        background:rgba(14,165,233,.16); color:#0ea5e9;
        display:grid; place-items:center;
    }
    .info-banner__title { font-weight:600; margin-bottom:4px; }

    .table-shell {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:22px;
        overflow:hidden; box-shadow:0 32px 64px -48px rgba(15,23,42,.45);
    }
    .product-table { width:100%; border-collapse:separate; border-spacing:0; }
    .product-table thead th {
        background:#f8faff; padding:15px 18px; font-size:12px; color:#64748b;
        text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5;
    }
    .product-table tbody td {
        padding:16px 18px; border-bottom:1px solid #eef2fb;
        font-size:13px; color:#1f2937; vertical-align:top;
    }
    .product-table tbody tr:hover { background:rgba(37,99,235,.04); }

    .code-chip {
        display:inline-flex; align-items:center; gap:8px;
        padding:6px 12px; border-radius:12px;
        background:rgba(37,99,235,.12); color:#1d4ed8;
        font-weight:600; font-size:12px; letter-spacing:.04em;
    }

    .status-pill {
        display:inline-flex; align-items:center; gap:6px;
        padding:6px 12px; border-radius:999px; font-size:12px;
        font-weight:600; letter-spacing:.04em; text-transform:uppercase;
    }
    .status-pill--active { background:rgba(34,197,94,.16); color:#166534; }
    .status-pill--inactive { background:rgba(148,163,184,.16); color:#475569; }

    .quota-chip {
        display:inline-flex; align-items:center;
        padding:6px 10px; border-radius:999px;
        background:rgba(14,165,233,.12); color:#0284c7;
        font-size:11px; font-weight:600; margin:2px 6px 2px 0;
    }
    .quota-chip small { margin-left:4px; color:#0f172a; opacity:.65; }

    .table-actions { display:inline-flex; gap:10px; }
    .action-icon {
        width:32px; height:32px; border-radius:10px;
        display:inline-flex; align-items:center; justify-content:center;
        font-size:13px; transition:all .2s ease; border:none;
    }
    .action-icon--edit { background:rgba(250,204,21,.16); color:#b45309; }
    .action-icon--delete { background:rgba(248,113,113,.16); color:#dc2626; }
    .action-icon:hover { transform:translateY(-1px); }

    .pagination-modern { margin-top:20px; display:flex; justify-content:flex-end; }

    @media (max-width: 992px) {
        .product-header { flex-direction:column; align-items:stretch; }
        .product-actions { justify-content:flex-start; }
    }
</style>
@endpush

@section('content')
@php
    $totalProducts = $products->count();
    $activeProducts = $products->where('is_active', true)->count();
    $inactiveProducts = $totalProducts - $activeProducts;
    $linkedQuota = $products->sum(fn($item) => $item->quotaMappings->count());
@endphp
<div class="product-page">
    <div class="product-header">
        <div>
            <h1 class="product-title">Master Data Produk</h1>
            <p class="product-subtitle">Daftar produk yang digunakan sebagai referensi alokasi kuota dan purchase order.</p>
        </div>
        <div class="product-actions">
            @if(auth()->user()->hasPermission('create master_data'))
                <a href="{{ route('admin.master-data.create') }}" class="product-action product-action--primary">
                    <i class="fas fa-plus"></i>
                    Tambah Produk
                </a>
            @endif
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span class="summary-card__label">Total Produk</span>
            <span class="summary-card__value">{{ number_format($totalProducts) }}</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Produk Aktif</span>
            <span class="summary-card__value">{{ number_format($activeProducts) }}</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Produk Nonaktif</span>
            <span class="summary-card__value">{{ number_format($inactiveProducts) }}</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Relasi Kuota</span>
            <span class="summary-card__value">{{ number_format($linkedQuota) }}</span>
        </div>
    </div>

    <div class="info-banner">
        <div class="info-banner__icon">
            <i class="fas fa-info"></i>
        </div>
        <div>
            <div class="info-banner__title">Informasi Produk</div>
            <div>Gunakan produk ini sebagai referensi pada modul kuota dan purchase order. Status aktif menentukan ketersediaan produk di formulir.</div>
        </div>
    </div>

    <div class="table-shell">
        <table class="product-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Tipe Model</th>
                    <th class="text-end">PK</th>
                    <th>Status</th>
                    <th>Kuota Terhubung</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><span class="code-chip">{{ $product->code }}</span></td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->sap_model ?? '-' }}</td>
                        <td class="text-end">{{ $product->pk_capacity ? number_format($product->pk_capacity, 1) : '-' }}</td>
                        <td>
                            <span class="status-pill {{ $product->is_active ? 'status-pill--active' : 'status-pill--inactive' }}">
                                {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            @forelse($product->quotaMappings as $mapping)
                                <span class="quota-chip">
                                    {{ $mapping->quota->quota_number }}
                                    @if($mapping->is_primary)<small>Primary</small>@endif
                                </span>
                            @empty
                                <span class="text-muted">Belum dimapping</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            @if(auth()->user()->hasPermission('update master_data') || auth()->user()->hasPermission('delete master_data'))
                                <div class="table-actions">
                                    @if(auth()->user()->hasPermission('update master_data'))
                                        <a href="{{ route('admin.master-data.edit', $product) }}" class="action-icon action-icon--edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('delete master_data'))
                                        <form action="{{ route('admin.master-data.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-icon action-icon--delete" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Belum ada data produk.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
