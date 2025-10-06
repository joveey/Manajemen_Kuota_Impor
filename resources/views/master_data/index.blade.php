{{-- resources/views/admin/master_data/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Master Data Produk')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Master Data</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-database me-2"></i>Master Data Produk
                </h3>
                <div>
                    <a href="/admin/master-data/create" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah Data
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Data produk digunakan sebagai referensi untuk manajemen kuota dan purchase order.
                </div>

                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Kode Produk</th>
                                <th>Nama Produk</th>
                                <th>Tipe Model</th>
                                <th>Created At</th>
                                <th style="width: 150px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><span class="badge bg-primary">PRD-001</span></td>
                                <td>Honda Civic Type R</td>
                                <td>CBU</td>
                                <td>2024-01-15</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/master-data/edit/1" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteData(1)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td><span class="badge bg-primary">PRD-002</span></td>
                                <td>Toyota GR Supra</td>
                                <td>CBU</td>
                                <td>2024-01-20</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/master-data/edit/2" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteData(2)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td><span class="badge bg-primary">PRD-003</span></td>
                                <td>Mercedes-Benz AMG GT</td>
                                <td>CBU</td>
                                <td>2024-02-01</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/admin/master-data/edit/3" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteData(3)" title="Delete">
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
                    className: 'btn btn-sm btn-success'
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-sm btn-danger'
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

    function deleteData(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data produk akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Simulate delete action
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data produk berhasil dihapus.',
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