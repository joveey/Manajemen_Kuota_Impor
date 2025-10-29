@php
    $modelValue = old('model', $model ?? '');
    $hsValue = old('hs_code');
    $pkValue = old('pk_capacity');
    $categoryValue = old('category');
    $periodValue = old('period_key', $periodKey ?? '');
    $backDestination = $backUrl ?? route('admin.master.quick_hs.index');
    $showCancel = $showCancel ?? true;
@endphp

<div class="alert alert-info mb-4" role="alert">
    <strong>Tips:</strong> Isi minimal Model/SKU dan HS Code. Jika model sudah ada, HS Code akan diperbarui.
    Tambahkan kapasitas PK atau kategori bila tersedia untuk meningkatkan akurasi pemetaan.
</div>

<form method="POST" action="{{ route('admin.master.quick_hs.store') }}" class="row g-3">
    @csrf
    <div class="col-md-6">
        <label class="form-label">Model/SKU</label>
        <input type="text" name="model" value="{{ $modelValue }}" class="form-control @error('model') is-invalid @enderror" maxlength="100" required>
        @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">HS Code</label>
        <input type="text" name="hs_code" value="{{ $hsValue }}" class="form-control @error('hs_code') is-invalid @enderror" maxlength="50" required>
        @error('hs_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">PK Capacity (opsional)</label>
        <input type="number" name="pk_capacity" value="{{ $pkValue }}" class="form-control @error('pk_capacity') is-invalid @enderror" step="0.01" min="0" placeholder="contoh: 1.5">
        @error('pk_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Kategori (opsional)</label>
        <input type="text" name="category" value="{{ $categoryValue }}" class="form-control @error('category') is-invalid @enderror" maxlength="100">
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <input type="hidden" name="period_key" value="{{ $periodValue }}">
    <input type="hidden" name="return" value="{{ $backDestination }}">

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Simpan
        </button>
        @if($showCancel)
            <a href="{{ $backDestination }}" class="btn btn-outline-secondary">
                <i class="fas fa-rotate-left me-1"></i> Batal
            </a>
        @endif
    </div>
</form>
