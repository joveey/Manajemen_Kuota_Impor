{{-- resources/views/admin/purchase_order/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Daftar Purchase Order')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Daftar Purchase Order</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shopping-cart me-2"></i>Daftar Purchase Order
                </h3>
                <div>
                    <a href="/admin/purchase-order/create" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Buat PO Baru
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Halaman ini menampilkan semua Purchase Order. PO dengan status "Dibuat" dapat diproses untuk pengiriman.
                </div>

                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>PO Number</th>
                                <th>Produk</th>
                                <th>Qty PO</th>
                                <th>Tanggal PO</th>
                                <th>Status</th>
                                <th style="width: 200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><strong>PO-2024-001</strong></td>
                                <td>Honda Civic Type R</td>
                                <td class="text-center">50</td>
                                <td>2024-03-15</td>
                                <td><span class="badge bg-primary">Dibuat</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/purchase-order/1" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" onclick="prosesKirim(1, 'PO-2024-001', 50)" title="Proses Kirim">
                                            <i class="fas fa-shipping-fast"></i> Kirim
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deletePO(1)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td><strong>PO-2024-002</strong></td>
                                <td>Toyota GR Supra</td>
                                <td class="text-center">30</td>
                                <td>2024-03-18</td>
                                <td><span class="badge bg-primary">Dibuat</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/purchase-order/2" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" onclick="prosesKirim(2, 'PO-2024-002', 30)" title="Proses Kirim">
                                            <i class="fas fa-shipping-fast"></i> Kirim
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deletePO(2)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td><strong>PO-2024-003</strong></td>
                                <td>Mercedes-Benz AMG GT</td>
                                <td class="text-center">20</td>
                                <td>2024-03-20</td>
                                <td><span class="badge bg-warning text-dark">Dalam Pengiriman</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/purchase-order/3" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Sedang Dikirim">
                                            <i class="fas fa-clock"></i> Proses
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td><strong>PO-2024-004</strong></td>
                                <td>Honda Civic Type R</td>
                                <td class="text-center">25</td>
                                <td>2024-03-10</td>
                                <td><span class="badge bg-success">Selesai</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/purchase-order/4" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Sudah Selesai">
                                            <i class="fas fa-check"></i> Selesai
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-primary">4</h3>
                <p class="text-muted mb-0">Total PO</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-info">2</h3>
                <p class="text-muted mb-0">PO Dibuat</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-warning">1</h3>
                <p class="text-muted mb-0">Dalam Pengiriman</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-success">1</h3>
                <p class="text-muted mb-0">Selesai</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Proses Kirim -->
<div class="modal fade" id="modalProsesKirim" tabindex="-1" aria-labelledby="modalProsesKirimLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProsesKirimLabel">
                    <i class="fas fa-shipping-fast me-2"></i>Proses Pengiriman
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formProsesKirim">
                <div class="modal-body">
                    <input type="hidden" id="po_id" name="po_id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>PO Number:</strong> <span id="modal_po_number">-</span><br>
                        <strong>Quantity PO:</strong> <span id="modal_po_qty">-</span> unit
                    </div>

                    <div class="mb-3">
                        <label for="no_pengiriman" class="form-label">No. Pengiriman <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="no_pengiriman" 
                               name="no_pengiriman" 
                               placeholder="Contoh: SHIP-2024-001"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="qty_dikirim" class="form-label">Qty Dikirim <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="qty_dikirim" 
                               name="qty_dikirim" 
                               placeholder="Masukkan jumlah unit"
                               min="1"
                               required>
                        <small class="form-text text-muted">Maksimal: <span id="max_qty">0</span> unit</small>
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_kirim" class="form-label">Tanggal Pengiriman <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control datepicker-modal" 
                               id="tanggal_kirim" 
                               name="tanggal_kirim" 
                               placeholder="YYYY-MM-DD"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="estimasi_tiba" class="form-label">Estimasi Tiba <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control datepicker-modal" 
                               id="estimasi_tiba" 
                               name="estimasi_tiba" 
                               placeholder="YYYY-MM-DD"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="detail_pengiriman" class="form-label">Detail Pengiriman</label>
                        <textarea class="form-control" 
                                  id="detail_pengiriman" 
                                  name="detail_pengiriman" 
                                  rows="3"
                                  placeholder="Nama kapal, pelabuhan, dll (opsional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Proses Kirim
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#datatable').DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'copy',
                    className: 'btn btn-sm btn-secondary'
                },
                {
                    extend: 'excel',
                    className: 'btn btn-sm btn-success',
                    title: 'Daftar Purchase Order'
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-sm btn-danger',
                    title: 'Daftar Purchase Order'
                },
                {
                    extend: 'print',
                    className: 'btn btn-sm btn-info'
                }
            ],
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Data tidak ditemukan",
                info: "Menampilkan halaman _PAGE_ dari _PAGES_",
                infoEmpty: "Tidak ada data yang tersedia",
                infoFiltered: "(difilter dari _MAX_ total data)",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            }
        });

        // Initialize Flatpickr for modal
        flatpickr('.datepicker-modal', {
            dateFormat: 'Y-m-d',
            allowInput: true,
            minDate: 'today'
        });

        // Handle form submit
        $('#formProsesKirim').on('submit', function(e) {
            e.preventDefault();
            
            const qtyDikirim = parseInt($('#qty_dikirim').val());
            const maxQty = parseInt($('#max_qty').text());
            
            if (qtyDikirim > maxQty) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: `Quantity dikirim tidak boleh melebihi ${maxQty} unit.`
                });
                return;
            }
            
            $('#modalProsesKirim').modal('hide');
            
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Pengiriman berhasil diproses.',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/admin/shipment';
                }
            });
        });
    });

    function prosesKirim(id, poNumber, qty) {
        $('#po_id').val(id);
        $('#modal_po_number').text(poNumber);
        $('#modal_po_qty').text(qty);
        $('#max_qty').text(qty);
        $('#qty_dikirim').attr('max', qty);
        $('#qty_dikirim').val(qty);
        
        // Reset form
        $('#formProsesKirim')[0].reset();
        $('#po_id').val(id);
        $('#modal_po_number').text(poNumber);
        $('#modal_po_qty').text(qty);
        $('#max_qty').text(qty);
        $('#qty_dikirim').val(qty);
        
        $('#modalProsesKirim').modal('show');
    }

    function deletePO(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Purchase Order akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Purchase Order berhasil dihapus.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    }
</script>
@endpush