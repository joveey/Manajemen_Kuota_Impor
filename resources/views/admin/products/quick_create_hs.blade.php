{{-- resources/views/admin/products/quick_create_hs.blade.php --}}
@extends('layouts.admin')

@section('title', 'Tambah Model > HS - Input Manual')
@section('page-title', 'Input Manual Model > HS')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.master.quick_hs.index') }}">Tambah Model > HS</a></li>
    <li class="breadcrumb-item active">Input Manual</li>
@endsection

@section('content')
@php
    $canCreate = auth()->user()?->can('product.create');
    $backUrl = old('return', $returnUrl ?? route('admin.master.quick_hs.index'));
@endphp

<div class="container-fluid px-0">
    @if(!$canCreate)
        <div class="alert alert-danger">
            Akses Ditolak (403): Anda tidak memiliki izin untuk menambahkan HS mapping.
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Input Manual Model &gt; HS</h5>
                    <div class="text-muted small">
                        Tambahkan atau perbarui pemetaan model/SKU ke HS Code secara manual. Perubahan langsung tersimpan ke master produk.
                    </div>
                </div>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @include('admin.products.partials.quick_hs_manual_form', [
                    'model' => $model ?? null,
                    'periodKey' => $periodKey ?? null,
                    'backUrl' => $backUrl,
                    'showCancel' => true,
                ])
            </div>
        </div>
    @endif
</div>
@endsection
