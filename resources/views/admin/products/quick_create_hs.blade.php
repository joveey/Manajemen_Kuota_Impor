{{-- resources/views/admin/products/quick_create_hs.blade.php --}}
@extends('layouts.admin')

@section('title', 'Tambah Model → HS (Manual)')
@section('page-title', 'Tambah Model → HS (Manual)')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('admin.openpo.form') }}">Import Open PO</a></li>
  <li class="breadcrumb-item active">Tambah Model → HS</li>
@endsection

@section('content')
@php $canCreate = auth()->user()?->can('product.create'); @endphp
@if(!$canCreate)
  <div class="alert alert-danger">Akses Ditolak (403): Anda tidak memiliki izin untuk menambahkan HS mapping.</div>
@else
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header">Form Model → HS</div>
        <div class="card-body">
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
            <input type="hidden" name="return" value="{{ old('return', $returnUrl ?? route('admin.openpo.form')) }}">

            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Simpan</button>
              <a href="{{ old('return', $returnUrl ?? route('admin.openpo.form')) }}" class="btn btn-outline-secondary">Batal</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

