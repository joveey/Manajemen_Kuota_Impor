{{-- resources/views/admin/master_data/form.blade.php --}}
@extends('layouts.admin')

@section('title', 'Form Master Data')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/master-data">Master Data</a></li>
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
            <form action="#" method="POST">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="kode_produk" class="form-label">Kode Produk <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="kode_produk" 
                               name="kode_produk" 
                               placeholder="Contoh: PRD-001"
                               required>
                        <small class="form-text text-muted">Format: PRD-XXX (3 digit angka)</small>
                    </div>

                    <div class="mb-3">
                        <label for="nama_produk" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="nama_produk" 
                               name="nama_produk" 
                               placeholder="Contoh: Honda Civic Type R"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="tipe_model" class="form-label">Tipe Model <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipe_model" name="tipe_model" required>
                            <option value="">-- Pilih Tipe Model --</option>
                            <option value="CBU">CBU (Completely Built Up)</option>
                            <option value="CKD">CKD (Completely Knocked Down)</option>
                            <option value="IKD">IKD (Incompletely Knocked Down)</option>
                        </select>
                        <small class="form-text text-muted">Pilih kategori impor produk</small>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                    <a href="/admin/master-data" class="btn btn-secondary">
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
        // Form validation on submit
        $('form').on('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Data produk berhasil disimpan.',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/admin/master-data';
                }
            });
        });
    });
</script>
@endpush