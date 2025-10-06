{{-- resources/views/admin/shipment/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Pengiriman (Shipment)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Pengiriman (Shipment)</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shipping-fast me-2"></i>Daftar Pengiriman
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Halaman ini menampilkan semua pengiriman yang sedang dalam proses. Klik tombol "Barang Diterima" setelah barang tiba di tujuan.
                </div>

                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>No. Pengiriman</th>
                                <th>PO Number</th>
                                <th>Produk</th>
                                <th>Qty Dikirim</th>
                                <th>Tanggal Kirim</th>
                                <th>Estimasi Tiba</th>
                                <th>Status</th>
                                <th style="width: 200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><strong>SHIP-2024-001</strong></td>
                                <td>PO-2024-003</td>
                                <td>Mercedes-Benz AMG GT</td>
                                <td class="text-center">20</td>
                                <td>2024-03-20</td>
                                <td>2024-04-15</td>
                                <td><span class="badge bg-warning text-dark">Dalam Perjalanan</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/shipment/1" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" onclick="barangDiterima(1, 'SHIP-2024-001', 20)" title="Barang Diterima">
                                            <i class="fas fa-check"></i> Diterima
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td><strong>SHIP-2024-002</strong></td>
                                <td>PO-2024-005</td>
                                <td>Toyota GR Supra</td>
                                <td class="text-center">15</td>
                                <td>2024-03-22</td>
                                <td>2024-04-18</td>
                                <td><span class="badge bg-warning text-dark">Dalam Perjalanan</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/shipment/2" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" onclick="barangDiterima(2, 'SHIP-2024-002', 15)" title="Barang Diterima">
                                            <i class="fas fa-check"></i> Diterima
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td><strong>SHIP-2024-003</strong></td>
                                <td>PO-2024-006</td>
                                <td>Honda Civic Type R</td>
                                <td class="text-center">30</td>
                                <td>2024-03-25</td>
                                <td>2024-04-20</td>
                                <td><span class="badge bg-primary">Baru Dikirim</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/shipment/3" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" onclick="barangDiterima(3, 'SHIP-2024-003', 30)" title="Barang Diterima">
                                            <i class="fas fa-check"></i> Diterima
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td><strong>SHIP-2024-004</strong></td>
                                <td>PO-2024-004</td>
                                <td>Honda Civic Type R</td>
                                <td class="text-center">25</td>
                                <td>2024-03-10</td>
                                <td>2024-04-05</td>
                                <td><span class="badge bg-success">Selesai</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/shipment/4" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Sudah Diterima">
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
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="fas fa-shipping-fast fa-2x text-primary mb-2"></i>
                <h3 class="text-primary mb-0">4</h3>
                <p class="text-muted mb-0">Total Pengiriman</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fas fa-truck fa-2x text-warning mb-2"></i>
                <h3 class="text-warning mb-0">3</h3>
                <p class="text-muted mb-0">Dalam Perjalanan</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h3 class="text-success mb-0">1</h3>
                <p class="text-muted mb-0">Sudah Diterima</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="fas fa-boxes fa-2x text-info mb-2"></i>
                <h3 class="text-info mb-0">90</h3>
                <p class="text-muted mb-0">Total Unit</p>
            </div>
        </div>
    </div>
</div>

<!-- Timeline Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history me-2"></i>Timeline Pengiriman Terbaru
                </h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-badge bg-primary">
                            <i class="fas fa-ship"></i>
                        </div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Pengiriman SHIP-2024-003 Dimulai</h6>
                            <p class="text-muted mb-0 small">30 unit Honda Civic Type R dikirim dari pabrik</p>
                            <small class="text-muted">25 Mar 2024, 10:30 AM</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-badge bg-warning">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Pengiriman SHIP-2024-002 Dalam Perjalanan</h6>
                            <p class="text-muted mb-0 small">15 unit Toyota GR Supra sedang dalam perjalanan</p>
                            <small class="text-muted">22 Mar 2024, 02:15 PM</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-badge bg-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Pengiriman SHIP-2024-004 Diterima</h6>
                            <p class="text-muted mb-0 small">25 unit Honda Civic Type R berhasil diterima</p>
                            <small class="text-muted">10 Mar 2024, 09:00 AM</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .timeline {
        position: relative;
        padding: 20px 0;
    }

    .timeline-item {
        position: relative;
        padding-left: 60px;
        margin-bottom: 30px;
    }

    .timeline-item:last-child {
        margin-bottom: 0;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 40px;
        bottom: -30px;
        width: 2px;
        background: #e5eaef;
    }

    .timeline-item:last-child::before {
        display: none;
    }

    .timeline-badge {
        position: absolute;
        left: 0;
        top: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        z-index: 1;
    }

    .timeline-content {
        background: #f9fafb;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #5D87FF;
    }
</style>
@endpush

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
                    title: 'Daftar Pengiriman'
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-sm btn-danger',
                    title: 'Daftar Pengiriman',
                    orientation: 'landscape'
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
            },
            order: [[0, 'desc']]
        });
    });

    function barangDiterima(id, shipmentNo, qty) {
        Swal.fire({
            title: 'Konfirmasi Penerimaan Barang',
            html: `
                <div class="text-start">
                    <p><strong>No. Pengiriman:</strong> ${shipmentNo}</p>
                    <p><strong>Quantity:</strong> ${qty} unit</p>
                    <hr>
                    <p class="text-muted">Apakah Anda yakin semua barang telah diterima dengan baik?</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Ya, Barang Diterima',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return new Promise((resolve) => {
                    setTimeout(() => {
                        resolve();
                    }, 1000);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    html: `
                        <p>Penerimaan barang berhasil dikonfirmasi.</p>
                        <p class="text-muted small">Kuota akan otomatis diperbarui.</p>
                    `,
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Simulate page reload
                    Swal.fire({
                        icon: 'info',
                        title: 'Memperbarui Data...',
                        text: 'Mohon tunggu sebentar',
                        timer: 1500,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    }).then(() => {
                        location.reload();
                    });
                });
            }
        });
    }
</script>
@endpush