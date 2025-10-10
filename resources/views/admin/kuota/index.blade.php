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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Manajemen Kuota Impor
                </h3>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.quotas.export') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-file-csv me-2"></i>Export CSV
                    </a>
                    <a href="{{ route('admin.quotas.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah Kuota
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
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
                                <th>Nama Kuota</th>
                                <th>Qty Pemerintah</th>
                                <th>Qty Forecast</th>
                                <th>Qty Actual</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th style="width: 150px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quotas as $quota)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $quota->quota_number }}</strong></td>
                                    <td>{{ $quota->name }}</td>
                                    <td class="text-end">{{ number_format($quota->total_allocation ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($quota->forecast_remaining ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($quota->actual_remaining ?? 0) }}</td>
                                    <td>
                                        {{ optional($quota->period_start)->format('M Y') ?? '-' }} -
                                        {{ optional($quota->period_end)->format('M Y') ?? '-' }}
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                \App\Models\Quota::STATUS_AVAILABLE => ['label' => 'Tersedia', 'class' => 'bg-success'],
                                                \App\Models\Quota::STATUS_LIMITED => ['label' => 'Hampir Habis', 'class' => 'bg-warning text-dark'],
                                                \App\Models\Quota::STATUS_DEPLETED => ['label' => 'Habis', 'class' => 'bg-danger'],
                                            ];
                                            $status = $statusMap[$quota->status] ?? $statusMap[\App\Models\Quota::STATUS_AVAILABLE];
                                        @endphp
                                        <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.quotas.show', $quota) }}" class="btn btn-sm btn-info" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.quotas.edit', $quota) }}" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('admin.quotas.destroy', $quota) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kuota ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Belum ada data kuota.</td>
                                </tr>
                            @endforelse
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
                <h3 class="text-primary">{{ $summary['active_count'] }}</h3>
                <p class="text-muted mb-0">Total Kuota</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-success">{{ number_format($summary['total_quota']) }}</h3>
                <p class="text-muted mb-0">Total Unit Tersedia</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="text-warning">{{ number_format($summary['total_quota'] - $summary['forecast_remaining']) }}</h3>
                <p class="text-muted mb-0">Unit Terpakai</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                @php
                    $percent = $summary['total_quota'] > 0
                        ? (($summary['total_quota'] - $summary['forecast_remaining']) / $summary['total_quota']) * 100
                        : 0;
                @endphp
                <h3 class="text-info">{{ number_format($percent, 1) }}%</h3>
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
</script>
@endpush
