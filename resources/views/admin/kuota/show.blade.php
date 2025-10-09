{{-- resources/views/admin/kuota/show.blade.php --}}
@extends('layouts.admin')

@section('title', 'Detail Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.quotas.index') }}">Manajemen Kuota</a></li>
    <li class="breadcrumb-item active">Detail Kuota</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <i class="fas fa-file-alt me-2"></i>Informasi Kuota
                </h3>
                <a href="{{ route('admin.quotas.edit', $quota) }}" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted">Nomor Kuota</h6>
                            <h4>{{ $quota->quota_number }}</h4>
                            <p class="mb-0 text-muted">{{ $quota->name }}</p>
                            <p class="mb-0 text-muted">Kategori Pemerintah: {{ $quota->government_category }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted">Periode Berlaku</h6>
                            <h4>
                                {{ optional($quota->period_start)->format('d M Y') ?? 'Tidak diketahui' }}
                                &ndash;
                                {{ optional($quota->period_end)->format('d M Y') ?? 'Tidak diketahui' }}
                            </h4>
                            <p class="mb-0 text-muted">Status:
                                @php
                                    $statusMap = [
                                        \App\Models\Quota::STATUS_AVAILABLE => ['label' => 'Tersedia', 'class' => 'bg-success'],
                                        \App\Models\Quota::STATUS_LIMITED => ['label' => 'Hampir Habis', 'class' => 'bg-warning text-dark'],
                                        \App\Models\Quota::STATUS_DEPLETED => ['label' => 'Habis', 'class' => 'bg-danger'],
                                    ];
                                    $status = $statusMap[$quota->status] ?? $statusMap[\App\Models\Quota::STATUS_AVAILABLE];
                                @endphp
                                <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                            </p>
                            <p class="mb-0 text-muted">Aktif: {{ $quota->is_active ? 'Ya' : 'Tidak' }}</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 text-center">
                            <h6 class="text-muted">Total Allocation</h6>
                            <h3 class="text-primary mb-0">{{ number_format($quota->total_allocation) }}</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 text-center">
                            <h6 class="text-muted">Forecast Remaining</h6>
                            <h3 class="text-warning mb-0">{{ number_format($quota->forecast_remaining) }}</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 text-center">
                            <h6 class="text-muted">Actual Remaining</h6>
                            <h3 class="text-success mb-0">{{ number_format($quota->actual_remaining) }}</h3>
                        </div>
                    </div>
                </div>
                @if($quota->source_document || $quota->notes)
                    <hr>
                    <div class="row g-3">
                        @if($quota->source_document)
                            <div class="col-md-6">
                                <h6 class="text-muted">Dokumen Sumber</h6>
                                <p class="mb-0">{{ $quota->source_document }}</p>
                            </div>
                        @endif
                        @if($quota->notes)
                            <div class="col-md-6">
                                <h6 class="text-muted">Catatan</h6>
                                <p class="mb-0">{{ $quota->notes }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Riwayat Perubahan Kuota
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Perubahan</th>
                                <th>Qty</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quota->histories as $history)
                                <tr>
                                    <td>{{ $history->occurred_on?->format('d M Y') ?? $history->created_at->format('d M Y') }}</td>
                                    <td>{{ str_replace('_', ' ', ucfirst($history->change_type)) }}</td>
                                    <td class="text-end">{{ number_format($history->quantity_change) }}</td>
                                    <td>{{ $history->description ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada riwayat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fas fa-link me-2"></i>Mapping Produk
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    <span>Daftar produk difilter otomatis sesuai rentang PK <strong>{{ $quota->government_category }}</strong>.</span>
                </div>

                @if($availableProducts->isEmpty())
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Tidak ada produk aktif yang sesuai. Tambahkan produk baru atau aktifkan kembali produk pada kategori ini.
                    </div>
                @endif

                <form action="{{ route('admin.quotas.attach-product', $quota) }}" method="POST" class="mb-3">
                    @csrf
                    <div class="mb-2">
                        <label for="product_id" class="form-label">Pilih Produk</label>
                        <select class="form-select" id="product_id" name="product_id" required {{ $availableProducts->isEmpty() ? 'disabled' : '' }}>
                            <option value="">-- Pilih Produk --</option>
                            @foreach($availableProducts as $product)
                                <option value="{{ $product->id }}">{{ $product->code }} - {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-2">
                                <label for="priority" class="form-label">Prioritas</label>
                                <input type="number" class="form-control" id="priority" name="priority" value="1" min="1" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1">
                                <label class="form-check-label" for="is_primary">Set sebagai primary</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="notes" class="form-label">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Catatan mapping"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-1"></i>Tambah Mapping
                    </button>
                </form>

        <h6 class="text-muted">Daftar Mapping</h6>
        <ul class="list-group">
            @forelse($quota->products as $product)
                @php
                    $pivot = $product->pivot;
                @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $product->code }}</strong>
                        <div class="small text-muted">{{ $product->name }}</div>
                        <div class="small text-muted">Priority: {{ $pivot->priority }} {{ $pivot->is_primary ? '| Primary' : '' }}</div>
                    </div>
                    <form action="{{ route('admin.quotas.detach-product', [$quota, $product]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus mapping ini?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                    </form>
                </li>
            @empty
                <li class="list-group-item text-muted text-center">Belum ada mapping.</li>
            @endforelse
        </ul>
            </div>
        </div>
    </div>
</div>
@endsection
