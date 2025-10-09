{{-- resources/views/admin/master_data/form.blade.php --}}
@extends('layouts.admin')

@section('title', 'Form Master Data')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.master-data.index') }}">Master Data</a></li>
    <li class="breadcrumb-item active">Form</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit me-2"></i>Form Master Data Produk
                </h3>
            </div>
            <form action="{{ $product->exists ? route('admin.master-data.update', $product) : route('admin.master-data.store') }}" method="POST">
                @csrf
                @if($product->exists)
                    @method('PUT')
                @endif
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="code" class="form-label">Kode Produk <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="code" 
                               name="code" 
                               value="{{ old('code', $product->code) }}"
                               placeholder="Contoh: PRD-001"
                               required>
                        <small class="form-text text-muted">Format: PRD-XXX (3 digit angka)</small>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $product->name) }}"
                               placeholder="Contoh: AC Panasonic Inverter"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="sap_model" class="form-label">Model SAP</label>
                        <input type="text"
                               class="form-control"
                               id="sap_model"
                               name="sap_model"
                               value="{{ old('sap_model', $product->sap_model) }}"
                               placeholder="Contoh: CS-LN5WKJ">
                        <small class="form-text text-muted">Kode model sesuai SAP / sistem manufaktur.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Kategori</label>
                                <input type="text"
                                       class="form-control"
                                       id="category"
                                       name="category"
                                       value="{{ old('category', $product->category) }}"
                                       placeholder="Contoh: Scheme XX1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pk_capacity" class="form-label">PK Capacity</label>
                                <input type="number"
                                       step="0.1"
                                       class="form-control"
                                       id="pk_capacity"
                                       name="pk_capacity"
                                       value="{{ old('pk_capacity', $product->pk_capacity) }}"
                                       placeholder="Contoh: 1.0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Keterangan tambahan produk">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Produk Aktif</label>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                    <a href="{{ route('admin.master-data.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle me-2"></i>Panduan Pengisian
                </h3>
            </div>
            <div class="card-body">
                <h6><strong>Kode Produk</strong></h6>
                <p class="text-muted small">Kode unik untuk identifikasi produk. Format: PRD-XXX</p>

                <h6 class="mt-3"><strong>Nama Produk</strong></h6>
                <p class="text-muted small">Nama lengkap produk yang akan diimpor.</p>

                <h6 class="mt-3"><strong>Tipe Model</strong></h6>
                <ul class="text-muted small">
                    <li><strong>CBU:</strong> Kendaraan impor utuh</li>
                    <li><strong>CKD:</strong> Impor komponen lengkap untuk dirakit</li>
                    <li><strong>IKD:</strong> Impor komponen tidak lengkap</li>
                </ul>

                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <small>Pastikan semua field yang bertanda (*) wajib diisi dengan benar.</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#code').trigger('focus');
    });
</script>
@endpush
