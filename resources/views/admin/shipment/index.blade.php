{{-- resources/views/admin/shipment/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Pengiriman (Shipment)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Pengiriman (Shipment)</li>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-primary">{{ number_format($summary['total']) }}</h3>
                <p class="text-muted mb-0">Total Pengiriman</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-warning">{{ number_format($summary['in_transit']) }}</h3>
                <p class="text-muted mb-0">Dalam Perjalanan</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-success">{{ number_format($summary['delivered']) }}</h3>
                <p class="text-muted mb-0">Sudah Diterima</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-info">{{ number_format($summary['quantity_total']) }}</h3>
                <p class="text-muted mb-0">Total Unit Dikirim</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-shipping-fast me-2"></i>Daftar Pengiriman</h3>
        <a href="{{ route('admin.shipments.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Buat Shipment</a>
    </div>
    <div class="card-body">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>No. Pengiriman</th>
                        <th>PO Number</th>
                        <th>Produk</th>
                        <th class="text-end">Qty Dikirim</th>
                        <th class="text-end">Qty Diterima</th>
                        <th>Tgl Kirim</th>
                        <th>ETA</th>
                        <th>Status</th>
                        <th class="text-center">Konfirmasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shipment)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $shipment->shipment_number }}</strong></td>
                            <td>
                                <strong>{{ $shipment->purchaseOrder->po_number }}</strong><br>
                                <small class="text-muted">Qty PO: {{ number_format($shipment->purchaseOrder->quantity) }}</small>
                            </td>
                            <td>
                                {{ $shipment->purchaseOrder->product->code }}<br>
                                <small class="text-muted">{{ $shipment->purchaseOrder->product->name }}</small>
                            </td>
                            <td class="text-end">{{ number_format($shipment->quantity_planned) }}</td>
                            <td class="text-end">{{ number_format($shipment->quantity_received) }}</td>
                            <td>{{ optional($shipment->ship_date)->format('d M Y') ?? '-' }}</td>
                            <td>{{ optional($shipment->eta_date)->format('d M Y') ?? '-' }}</td>
                            <td>
                                @php
                                    $statusMap = [
                                        \App\Models\Shipment::STATUS_PENDING => 'Menunggu',
                                        \App\Models\Shipment::STATUS_IN_TRANSIT => 'Dalam Perjalanan',
                                        \App\Models\Shipment::STATUS_PARTIAL => 'Parsial',
                                        \App\Models\Shipment::STATUS_DELIVERED => 'Selesai',
                                    ];
                                    $statusClass = [
                                        \App\Models\Shipment::STATUS_PENDING => 'bg-secondary',
                                        \App\Models\Shipment::STATUS_IN_TRANSIT => 'bg-warning text-dark',
                                        \App\Models\Shipment::STATUS_PARTIAL => 'bg-info text-dark',
                                        \App\Models\Shipment::STATUS_DELIVERED => 'bg-success',
                                    ];
                                @endphp
                                <span class="badge {{ $statusClass[$shipment->status] ?? 'bg-secondary' }}">
                                    {{ $statusMap[$shipment->status] ?? $shipment->status }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($shipment->status === \App\Models\Shipment::STATUS_DELIVERED)
                                    <span class="text-muted">Selesai</span>
                                @else
                                    <button class="btn btn-sm btn-success" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#receipt-form-{{ $shipment->id }}"
                                            aria-expanded="false" aria-controls="receipt-form-{{ $shipment->id }}">
                                        <i class="fas fa-box-open me-1"></i>Konfirmasi
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if($shipment->status !== \App\Models\Shipment::STATUS_DELIVERED)
                            <tr class="collapse" id="receipt-form-{{ $shipment->id }}">
                                <td colspan="10">
                                    <form action="{{ route('admin.shipments.receive', $shipment) }}" method="POST" class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-md-3">
                                            <label class="form-label small">Tanggal Receipt</label>
                                            <input type="date" name="receipt_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Qty Diterima</label>
                                            <input type="number" name="quantity_received" class="form-control form-control-sm" min="1" max="{{ $shipment->quantity_planned - $shipment->quantity_received }}" value="{{ $shipment->quantity_planned - $shipment->quantity_received }}" required>
                                            <small class="text-muted">Sisa: {{ number_format($shipment->quantity_planned - $shipment->quantity_received) }}</small>
                                        </div>
        <div class="col-md-3">
            <label class="form-label small">No Dokumen</label>
            <input type="text" name="document_number" class="form-control form-control-sm" placeholder="Optional">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Catatan</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
        </div>
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check me-1"></i>Simpan Penerimaan</button>
        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">Belum ada data pengiriman.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
