{{-- resources/views/admin/purchase_order/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Input Order (PO)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="/admin/purchase-order">Purchase Order</a></li>
    <li class="breadcrumb-item active">Input Order</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice me-2"></i>Form Input Purchase Order
                </h3>
            </div>
            <form action="#" method="POST">
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Petunjuk:</strong> Pastikan kuota untuk produk yang dipilih masih tersedia. Sistem akan otomatis memvalidasi ketersediaan kuota.
                    </div>

                    <div class="mb-3">
                        <label for="po_number" class="form-label">PO Number <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="po_number" 
                               name="po_number" 
                               placeholder="Contoh: PO-2024-001"
                               required>
                        <small class="form-text text-muted">Format: PO-YYYY-XXX (XXX adalah nomor urut 3 digit)</small>
                    </div>

                    <div class="mb-3">
                        <label for="produk_id" class="form-label">Produk <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="produk_id" name="produk_id" required onchange="checkKuota(this.value)">
                            <option value="">-- Pilih Produk --</option>
                            <option value="1" data-kuota="380" data-nama="Honda Civic Type R">PRD-001 - Honda Civic Type R (Kuota: 380 unit)</option>
                            <option value="2" data-kuota="5" data-nama="Toyota GR Supra">PRD-002 - Toyota GR Supra (Kuota: 5 unit)</option>
                            <option value="3" data-kuota="0" data-nama="Mercedes-Benz AMG GT">PRD-003 - Mercedes-Benz AMG GT (Kuota: Habis)</option>
                        </select>
                        <div id="kuota-info" class="mt-2" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="qty_po" class="form-label">Quantity PO <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="qty_po" 
                               name="qty_po" 
                               placeholder="Masukkan jumlah unit"
                               min="1"
                               required
                               onkeyup="validateQty()">
                        <small class="form-text text-muted">Jumlah unit yang akan di-order</small>
                        <div id="qty-warning" class="mt-2" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_po" class="form-label">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control datepicker" 
                               id="tanggal_po" 
                               name="tanggal_po" 
                               placeholder="YYYY-MM-DD"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="pabrik_nama" class="form-label">Nama Pabrik <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="pabrik_nama" 
                               name="pabrik_nama" 
                               placeholder="Contoh: Honda Manufacturing Japan"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="detail_pabrik" class="form-label">Detail Pabrik <span class="text-danger">*</span></label>
                        <textarea class="form-control" 
                                  id="detail_pabrik" 
                                  name="detail_pabrik" 
                                  rows="4"
                                  placeholder="Masukkan alamat lengkap pabrik, contact person, dll"
                                  required></textarea>
                        <small class="form-text text-muted">Sertakan informasi lengkap untuk keperluan pengiriman</small>
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
                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                        <i class="fas fa-save me-2"></i>Buat Purchase Order
                    </button>
                    <a href="/admin/purchase-order" class="btn btn-secondary">
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
                    <i class="fas fa-chart-pie me-2"></i>Ringkasan Kuota
                </h3>
            </div>
            <div class="card-body">
                <div id="kuota-summary" style="display: none;">
                    <dl class="row mb-0">
                        <dt class="col-sm-6">Produk:</dt>
                        <dd class="col-sm-6" id="summary-produk">-</dd>
                        
                        <dt class="col-sm-6">Kuota Tersedia:</dt>
                        <dd class="col-sm-6"><span class="badge bg-success" id="summary-kuota">0</span></dd>
                        
                        <dt class="col-sm-6">Qty PO:</dt>
                        <dd class="col-sm-6"><span class="badge bg-primary" id="summary-qty">0</span></dd>
                        
                        <dt class="col-sm-6">Sisa Kuota:</dt>
                        <dd class="col-sm-6"><span class="badge bg-info" id="summary-sisa">0</span></dd>
                    </dl>
                </div>
                <div id="kuota-empty" class="text-center text-muted">
                    <i class="fas fa-info-circle fa-3x mb-3 opacity-50"></i>
                    <p>Pilih produk untuk melihat ringkasan kuota</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle me-2"></i>Panduan
                </h3>
            </div>
            <div class="card-body">
                <h6><strong>PO Number</strong></h6>
                <p class="text-muted small">Nomor unik untuk identifikasi Purchase Order.</p>

                <h6 class="mt-3"><strong>Produk</strong></h6>
                <p class="text-muted small">Pilih produk dari master data. Informasi kuota akan ditampilkan otomatis.</p>

                <h6 class="mt-3"><strong>Quantity PO</strong></h6>
                <p class="text-muted small">Jumlah unit yang akan di-order. Pastikan tidak melebihi kuota yang tersedia.</p>

                <h6 class="mt-3"><strong>Detail Pabrik</strong></h6>
                <p class="text-muted small">Informasi lengkap pabrik asal untuk keperluan pengiriman dan dokumentasi.</p>

                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <small><strong>Penting:</strong> PO yang sudah dibuat tidak dapat diubah. Pastikan semua informasi sudah benar.</small>
                </div>
            </div>
        </div>

        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-check-circle me-2"></i>Status Validasi
                </h3>
            </div>
            <div class="card-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check1" disabled>
                    <label class="form-check-label small" for="check1">
                        PO Number terisi
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check2" disabled>
                    <label class="form-check-label small" for="check2">
                        Produk dipilih
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check3" disabled>
                    <label class="form-check-label small" for="check3">
                        Kuota tersedia
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check4" disabled>
                    <label class="form-check-label small" for="check4">
                        Quantity valid
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="check5" disabled>
                    <label class="form-check-label small" for="check5">
                        Detail pabrik lengkap
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentKuota = 0;
    let currentProdukNama = '';

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
            defaultDate: 'today'
        });

        // Real-time validation
        $('#po_number').on('keyup', function() {
            $('#check1').prop('checked', $(this).val().length > 0);
        });

        $('#detail_pabrik').on('keyup', function() {
            $('#check5').prop('checked', $(this).val().length > 10);
        });

        // Form validation on submit
        $('form').on('submit', function(e) {
            e.preventDefault();
            
            const qty = parseInt($('#qty_po').val());
            
            if (currentKuota === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kuota Habis!',
                    text: 'Produk yang dipilih tidak memiliki kuota tersedia.'
                });
                return;
            }
            
            if (qty > currentKuota) {
                Swal.fire({
                    icon: 'error',
                    title: 'Quantity Melebihi Kuota!',
                    text: `Quantity PO (${qty}) melebihi kuota tersedia (${currentKuota}).`
                });
                return;
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Purchase Order berhasil dibuat.',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/admin/purchase-order';
                }
            });
        });
    });

    function checkKuota(produkId) {
        const option = $('#produk_id option:selected');
        currentKuota = parseInt(option.data('kuota')) || 0;
        currentProdukNama = option.data('nama') || '';
        
        if (!produkId) {
            $('#kuota-info').hide();
            $('#kuota-summary').hide();
            $('#kuota-empty').show();
            $('#check2').prop('checked', false);
            $('#check3').prop('checked', false);
            return;
        }
        
        $('#check2').prop('checked', true);
        
        // Show kuota info
        let alertClass = 'alert-success';
        let icon = 'fa-check-circle';
        let message = `Kuota tersedia: <strong>${currentKuota} unit</strong>`;
        
        if (currentKuota === 0) {
            alertClass = 'alert-danger';
            icon = 'fa-times-circle';
            message = '<strong>Kuota habis!</strong> Produk ini tidak dapat di-order.';
            $('#check3').prop('checked', false);
            $('#btnSubmit').prop('disabled', true);
        } else if (currentKuota < 10) {
            alertClass = 'alert-warning';
            icon = 'fa-exclamation-triangle';
            message = `<strong>Perhatian!</strong> Kuota hampir habis. Tersisa: <strong>${currentKuota} unit</strong>`;
            $('#check3').prop('checked', true);
            $('#btnSubmit').prop('disabled', false);
        } else {
            $('#check3').prop('checked', true);
            $('#btnSubmit').prop('disabled', false);
        }
        
        $('#kuota-info').html(`
            <div class="alert ${alertClass} mb-0">
                <i class="fas ${icon}"></i> ${message}
            </div>
        `).show();
        
        // Update summary
        $('#summary-produk').text(currentProdukNama);
        $('#summary-kuota').text(currentKuota);
        $('#kuota-empty').hide();
        $('#kuota-summary').show();
        
        validateQty();
    }

    function validateQty() {
        const qty = parseInt($('#qty_po').val()) || 0;
        
        if (qty === 0) {
            $('#qty-warning').hide();
            $('#summary-qty').text(0);
            $('#summary-sisa').text(currentKuota);
            $('#check4').prop('checked', false);
            return;
        }
        
        $('#summary-qty').text(qty);
        const sisa = currentKuota - qty;
        $('#summary-sisa').text(sisa);
        
        if (qty > currentKuota) {
            $('#qty-warning').html(`
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-times-circle"></i>
                    <strong>Error!</strong> Quantity melebihi kuota tersedia (${currentKuota} unit).
                </div>
            `).show();
            $('#check4').prop('checked', false);
            $('#btnSubmit').prop('disabled', true);
        } else if (sisa < 10 && sisa > 0) {
            $('#qty-warning').html(`
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Perhatian!</strong> Sisa kuota setelah order ini: <strong>${sisa} unit</strong>
                </div>
            `).show();
            $('#check4').prop('checked', true);
            $('#btnSubmit').prop('disabled', false);
        } else if (sisa === 0) {
            $('#qty-warning').html(`
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i>
                    Order ini akan menghabiskan seluruh kuota yang tersedia.
                </div>
            `).show();
            $('#check4').prop('checked', true);
            $('#btnSubmit').prop('disabled', false);
        } else {
            $('#qty-warning').hide();
            $('#check4').prop('checked', true);
            $('#btnSubmit').prop('disabled', false);
        }
    }
</script>
@endpush