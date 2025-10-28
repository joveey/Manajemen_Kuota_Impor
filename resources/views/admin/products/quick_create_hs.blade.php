{{-- resources/views/admin/products/quick_create_hs.blade.php --}}
@extends('layouts.admin')

@section('title', 'Tambah Model → HS')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.mapping.mapped.page') }}">Model → HS (Mapped)</a></li>
    <li class="breadcrumb-item active">Tambah Model → HS</li>
@endsection

@push('styles')
<style>
    .page-shell {
        display: flex;
        flex-direction: column;
        gap: 24px;
        width: 100%;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        flex-wrap: wrap;
    }

    .page-header__title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .page-header__subtitle {
        font-size: 14px;
        color: #64748b;
        margin-top: 6px;
        max-width: 540px;
    }

    .page-header__actions {
        display: inline-flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .page-header__button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 12px;
        border: 1px solid #cbd5f5;
        background: rgba(37, 99, 235, 0.08);
        color: #1d4ed8;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .page-header__button:hover {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 12px 30px -20px rgba(37, 99, 235, 0.7);
    }

    .form-card {
        background: #ffffff;
        border-radius: 22px;
        border: 1px solid #e6ebf5;
        box-shadow: 0 32px 64px -52px rgba(15, 23, 42, 0.55);
        padding: 32px;
        max-width: 760px;
    }

    .form-card__title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 18px;
    }

    .quick-hint {
        background: rgba(37, 99, 235, 0.06);
        border: 1px solid rgba(37, 99, 235, 0.12);
        border-radius: 16px;
        padding: 18px 22px;
        color: #1e3a8a;
        font-size: 13px;
    }

    @media (max-width: 768px) {
        .form-card {
            padding: 24px 20px;
        }
    }
</style>
@endpush

@section('content')
@php
    $canCreate = auth()->user()?->can('product.create');
    $backUrl = old('return', $returnUrl ?? route('admin.mapping.mapped.page'));
@endphp

<div class="page-shell">
    @if(!$canCreate)
        <div class="alert alert-danger mb-0">
            Akses Ditolak (403): Anda tidak memiliki izin untuk menambahkan HS mapping.
        </div>
    @else
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Tambah Model → HS</h1>
                <p class="page-header__subtitle">
                    Tambahkan atau perbarui pemetaan model/SKU ke HS Code secara manual. Data yang tersimpan akan langsung
                    digunakan pada proses mapping dan perhitungan kuota.
                </p>
            </div>
            <div class="page-header__actions">
                <a href="{{ $backUrl }}" class="page-header__button">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card__title">Form Model → HS</div>
            <div class="quick-hint mb-4">
                <strong>Tips:</strong> Isi minimal Model/SKU dan HS Code. Jika model sudah ada, HS Code akan diperbarui secara otomatis.
                Masukkan kapasitas PK dan kategori bila tersedia untuk meningkatkan akurasi matching kuota.
            </div>

            <form method="POST" action="{{ route('admin.master.quick_hs.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Model/SKU</label>
                    <input type="text" name="model" value="{{ old('model', $model ?? '') }}" class="form-control @error('model') is-invalid @enderror" maxlength="100" required>
                    @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">HS Code</label>
                    <input type="text" name="hs_code" value="{{ old('hs_code') }}" class="form-control @error('hs_code') is-invalid @enderror" maxlength="50" required>
                    @error('hs_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">PK Capacity (opsional)</label>
                    <input type="number" name="pk_capacity" value="{{ old('pk_capacity') }}" class="form-control @error('pk_capacity') is-invalid @enderror" step="0.01" min="0" placeholder="contoh: 1.5">
                    @error('pk_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Kategori (opsional)</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="form-control @error('category') is-invalid @enderror" maxlength="100">
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <input type="hidden" name="period_key" value="{{ old('period_key', $periodKey ?? '') }}">
                <input type="hidden" name="return" value="{{ $backUrl }}">

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Batal
                    </a>
                </div>
            </form>
        </div>

        @if(\Illuminate\Support\Facades\Route::has('admin.mapping.model_hs.upload'))
        <div class="form-card mt-4">
            <div class="form-card__title">Upload File (Excel/CSV)</div>
            <div class="quick-hint mb-3">
                <strong>Format:</strong> kolom <code>MODEL</code>, <code>HS_CODE</code>. HS wajib sudah ada pada HS→PK (punya PK). Baris yang konflik tidak akan di‑overwrite.
            </div>
            <form method="POST" action="{{ route('admin.mapping.model_hs.upload') }}" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-8">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <div class="invalid-feedback">File wajib (.xlsx/.xls/.csv)</div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-eye me-1"></i> Upload & Preview</button>
                    <a href="{{ route('admin.mapping.model_hs.index') }}" class="btn btn-outline-secondary">Halaman Import</a>
                </div>
            </form>
        </div>
        @endif
    @endif
</div>
@endsection
