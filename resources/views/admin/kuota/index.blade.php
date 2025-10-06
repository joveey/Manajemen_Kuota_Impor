{{-- resources/views/admin/kuota/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Manajemen Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Manajemen Kuota</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie me-2"></i>Manajemen Kuota Impor
                </h3>
                <div>
                    <a href="/admin/kuota/create" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah Kuota
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Halaman ini menampilkan daftar kuota impor yang tersedia. Status kuota akan otomatis diperbarui berdasarkan penggunaan.
                </div>

                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>No. Kuota</th>
                                <th>Produk</th>
                                <th>Qty Pemerintah</th>
                                <th>Qty Forecast</th>
                                <th>Qty Actual</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th style="width: 150px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><strong>KTA-2024-001</strong></td>
                                <td>Honda Civic Type R</td>
                                <td class="text-center">500</td>
                                <td class="text-center">450</td>
                                <td class="text-center">120</td>
                                <td>Jan 2024 - Dec 2024</td>
                                <td><span class="badge bg-success">Tersedia</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/kuota/1" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/admin/kuota/edit/1" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteKuota(1)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td><strong>KTA-2024-002</strong></td>
                                <td>Toyota GR Supra</td>
                                <td class="text-center">300</td>
                                <td class="text-center">280</td>
                                <td class="text-center">275</td>
                                <td>Jan 2024 - Dec 2024</td>
                                <td><span class="badge bg-warning text-dark">Hampir Habis</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/kuota/2" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/admin/kuota/edit/2" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteKuota(2)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td><strong>KTA-2024-003</strong></td>
                                <td>Mercedes-Benz AMG GT</td>
                                <td class="text-center">200</td>
                                <td class="text-center">200</td>
                                <td class="text-center">200</td>
                                <td>Jan 2024 - Dec 2024</td>
                                <td><span class="badge bg-danger">Habis</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/kuota/3" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/admin/kuota/edit/3" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteKuota(3)" title="Delete">
                                            <i class="fas fa-trash"></i>
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
                <h3 class="text-primary">3</h3>
                <p class="text-muted mb-0">Total Kuota</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-success">1,000</h3>
                <p class="text-muted mb-0">Total Unit Tersedia</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-warning">595</h3>
                <p class="text-muted mb-0">Unit Terpakai</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-info">59.5%</h3>
                <p class="text-muted mb-0">Persentase Penggunaan</p>
            </div>
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
                    title: 'Data Kuota Impor'
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-sm btn-danger',
                    title: 'Data Kuota Impor'
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
    });

    function deleteKuota(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data kuota akan dihapus permanen!",
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
                    text: 'Data kuota berhasil dihapus.',
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