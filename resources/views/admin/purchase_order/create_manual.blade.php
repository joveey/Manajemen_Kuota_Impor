{{-- resources/views/admin/purchase_order/create_manual.blade.php --}}
@extends('layouts.admin')

@section('title', 'Tambah PO Manual')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Purchase Orders</a></li>
    <li class="breadcrumb-item active">Tambah PO Manual</li>
@endsection

@section('content')
@php $canCreate = auth()->user()?->can('po.create'); @endphp
@if(!$canCreate)
    <div class="alert alert-danger">Forbidden (403): Anda tidak memiliki akses untuk membuat PO manual.</div>
@else
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Tambah Purchase Order Manual</h1>
            <p class="page-header__subtitle">Gunakan form berikut untuk mencatat PO yang tidak tersedia di Excel.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.purchase-orders.store-manual') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label class="form-label">Nomor PO</label>
                    <input type="text" name="po_number" value="{{ old('po_number') }}" class="form-control @error('po_number') is-invalid @enderror" maxlength="50" required>
                    @error('po_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tanggal PO</label>
                    <input type="date" name="order_date" value="{{ old('order_date', now()->toDateString()) }}" class="form-control @error('order_date') is-invalid @enderror" required>
                    @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Model/SKU</label>
                    <input type="text" name="product_model" value="{{ old('product_model') }}" class="form-control @error('product_model') is-invalid @enderror" maxlength="100" required>
                    @error('product_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Qty</label>
                    <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" step="1" class="form-control @error('quantity') is-invalid @enderror" required>
                    @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Unit Price (opsional)</label>
                    <input type="number" name="unit_price" value="{{ old('unit_price') }}" min="0" step="0.01" class="form-control @error('unit_price') is-invalid @enderror">
                    @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" maxlength="500">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="create_product" name="create_product" value="1" {{ old('create_product', '1') ? 'checked' : '' }}>
                    <label class="form-check-label" for="create_product">Buat Produk Minimal jika Belum Ada</label>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
