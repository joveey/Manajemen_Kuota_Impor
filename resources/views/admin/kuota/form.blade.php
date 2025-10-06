{{-- resources/views/admin/kuota/form.blade.php --}}
@extends('layouts.admin')

@section('title', 'Form Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/kuota">Manajemen Kuota</a></li>
    <li class="breadcrumb-item active">Form</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit me-2"></i>Form Kuota Impor
                </h3>
            </div>
            <form action="#" method="POST">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nomor_kuota" class="form-label">Nomor Kuota <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="nomor_kuota" 
                               name="nomor_kuota" 
                               placeholder="Contoh: KTA-2024-001"
                               required>
                        <small class="form-text text-muted">Format: KTA-YYYY-XXX</small>
                    </div>

                    <div class="mb-3">
                        <label for="produk_id" class="form-label">Produk <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="produk_id" name="produk_id" required>
                            <option value="">-- Pilih Produk --</option>
                            <option value="1">PRD-001 - Honda Civic Type R</option>
                            <option value="2">PRD-002 - Toyota GR Supra</option>
                            <option value="3">PRD-003 - Mercedes-Benz AMG GT</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="qty_pemerintah" class="form-label">Quantity Pemerintah <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="qty_pemerintah" 
                               name="qty_pemerintah" 
                               placeholder="Contoh: 500"
                               min="1"
                               required>
                        <small class="form-text text-muted">Jumlah unit yang disetujui pemerintah</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="periode_mulai" class="form-label">Periode Mulai <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control datepicker" 
                                       id="periode_mulai" 
                                       name="periode_mulai" 
                                       placeholder="YYYY-MM-DD"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="periode_selesai" class="form-label">Periode Selesai <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control datepicker" 
                                       id="periode_selesai" 
                                       name="periode_selesai" 
                                       placeholder="YYYY-MM-DD"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" 
                                  id="keterangan" 
                                  name="keterangan" 
                                  rows="3"
                                  placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                    <a href="/admin/kuota" class="btn btn-secondary">
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
                <h6><strong>Nomor Kuota</strong></h6>
                <p class="text-muted small">Nomor unik kuota. Format: KTA-YYYY-XXX (XXX adalah nomor urut 3 digit)</p>

                <h6 class="mt-3"><strong>Produk</strong></h6>
                <p class="text-muted small">Pilih produk yang akan diberikan kuota impor.</p>

                <h6 class="mt-3"><strong>Quantity Pemerintah</strong></h6>
                <p class="text-muted small">Jumlah maksimal unit yang diizinkan pemerintah untuk diimpor dalam periode tertentu.</p>

                <h6 class="mt-3"><strong>Periode</strong></h6>
                <p class="text-muted small">Rentang waktu berlakunya kuota. Pastikan periode selesai lebih besar dari periode mulai.</p>

                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <small><strong>Perhatian:</strong> Setelah kuota dibuat, quantity pemerintah tidak dapat diubah. Pastikan data sudah benar.</small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calculator me-2"></i>Kalkulator Kuota
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small">Qty Pemerintah:</label>
                    <input type="number" class="form-control form-control-sm" id="calc_qty" value="500">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Forecast (%):</label>
                    <input type="number" class="form-control form-control-sm" id="calc_forecast" value="90">
                </div>
                <button type="button" class="btn btn-sm btn-primary w-100" onclick="calculateForecast()">
                    <i class="fas fa-calculator me-1"></i>Hitung
                </button>
                <div class="mt-3 p-2 bg-light rounded" id="calc_result" style="display: none;">
                    <small class="text-muted">Forecast Quantity:</small>
                    <h5 class="mb-0 text-primary" id="forecast_result">0</h5>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Initialize Flatpickr
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            allowInput: true,
            minDate: 'today'
        });

        // Form validation on submit
        $('form').on('submit', function(e) {
            e.preventDefault();
            
            // Validate dates
            const startDate = new Date($('#periode_mulai').val());
            const endDate = new Date($('#periode_selesai').val());
            
            if (endDate <= startDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Periode selesai harus lebih besar dari periode mulai.'
                });
                return;
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Data kuota berhasil disimpan.',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/admin/kuota';
                }
            });
        });
    });

    function calculateForecast() {
        const qty = parseFloat($('#calc_qty').val()) || 0;
        const forecastPercent = parseFloat($('#calc_forecast').val()) || 0;
        const result = Math.round(qty * (forecastPercent / 100));
        
        $('#forecast_result').text(result);
        $('#calc_result').slideDown();
    }
</script>
@endpush