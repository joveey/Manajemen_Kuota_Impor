{{-- resources/views/admin/purchase_order/show.blade.php --}}
@extends('layouts.admin')

@section('title', 'Detail Purchase Order')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Purchase Order</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Informasi PO</h3>
                <span class="badge bg-primary">{{ strtoupper($purchaseOrder->status) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <h6 class="text-muted">PO Doc</h6>
                        <h3>{{ $purchaseOrder->po_number }}</h3>
                        <p class="text-muted mb-0">Created: {{ $purchaseOrder->order_date?->format('d M Y') ?? '-' }}</p>
                        <p class="text-muted mb-0">Line No: {{ $purchaseOrder->line_number ?? '-' }}</p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted">Vendor</h6>
                        <p class="mb-1"><strong>{{ $purchaseOrder->vendor_number ?? '-' }}</strong></p>
                        <p class="text-muted mb-0">{{ $purchaseOrder->vendor_name ?? '-' }}</p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted">Qty & Amount</h6>
                        <p class="mb-1">{{ number_format($purchaseOrder->quantity) }} unit</p>
                        <p class="text-muted mb-0">Amount: {{ $purchaseOrder->amount !== null ? number_format($purchaseOrder->amount, 2) : '-' }}</p>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <h6 class="text-muted">Item SAP</h6>
                        <p class="mb-1"><strong>{{ $purchaseOrder->item_code ?? $purchaseOrder->product->code }}</strong> - {{ $purchaseOrder->item_description ?? $purchaseOrder->product->name }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Kategori</h6>
                        <p class="mb-1">Cat PO: {{ $purchaseOrder->category_code ?? '-' }}</p>
                        <p class="text-muted mb-0">Cat Desc: {{ $purchaseOrder->category ?? '-' }}</p>
                        <p class="text-muted mb-0">Mat Grp: {{ $purchaseOrder->material_group ?? '-' }}</p>
                        <p class="text-muted mb-0">SAP Status: {{ $purchaseOrder->sap_order_status ?? '-' }}</p>
                    </div>
                </div>
                <hr>
                <div class="row g-3 text-center">
                    <div class="col-md-4">
                        <h6 class="text-muted">Qty PO</h6>
                        <h3 class="text-primary mb-0">{{ number_format($purchaseOrder->quantity) }}</h3>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted">Qty Dikirim</h6>
                        <h3 class="text-info mb-0">{{ number_format($purchaseOrder->quantity_shipped) }}</h3>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted">Qty Diterima</h6>
                        <h3 class="text-success mb-0">{{ number_format($purchaseOrder->quantity_received) }}</h3>
                    </div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Warehouse</h6>
                        <p class="mb-1">WH: {{ $purchaseOrder->warehouse_code ?? '-' }} - {{ $purchaseOrder->warehouse_name ?? '-' }}</p>
                        <p class="text-muted mb-1">WH Source: {{ $purchaseOrder->warehouse_source ?? '-' }}</p>
                        <p class="text-muted mb-1">Subinv: {{ $purchaseOrder->subinventory_code ?? '-' }} - {{ $purchaseOrder->subinventory_name ?? '-' }}</p>
                        <p class="text-muted mb-0">Subinv Source: {{ $purchaseOrder->subinventory_source ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Produk Internal</h6>
                        <p class="mb-1"><strong>{{ $purchaseOrder->product->code }}</strong> - {{ $purchaseOrder->product->name }}</p>
                        <p class="text-muted mb-0">Quota Number: {{ $purchaseOrder->quota?->quota_number ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-shipping-fast me-2"></i>Shipment</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No Shipment</th>
                                <th>Qty</th>
                                <th>Tgl Kirim</th>
                                <th>ETA</th>
                                <th>Status</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseOrder->shipments as $shipment)
                                <tr>
                                    <td>{{ $shipment->shipment_number }}</td>
                                    <td>
                                        {{ number_format($shipment->quantity_planned) }}<br>
                                        <small class="text-muted">Diterima: {{ number_format($shipment->quantity_received) }}</small>
                                    </td>
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
                                        <span class="badge {{ $statusClass[$shipment->status] ?? 'bg-secondary' }}">{{ $statusMap[$shipment->status] ?? $shipment->status }}</span>
                                    </td>
                                    <td>
                                        @forelse($shipment->receipts as $receipt)
                                            <div class="mb-2">
                                                <strong>{{ $receipt->receipt_date?->format('d M Y') }}</strong>
                                                <span class="text-muted">({{ number_format($receipt->quantity_received) }} unit)</span>
                                                <div class="text-muted small">{{ $receipt->document_number ?? 'No Doc' }}</div>
                                            </div>
                                        @empty
                                            <span class="text-muted">Belum ada receipt</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Belum ada shipment untuk PO ini.</td>
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
                <h3 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Informasi Kuota</h3>
            </div>
            <div class="card-body">
                <h5 class="mb-1">{{ $purchaseOrder->quota->quota_number }}</h5>
                <p class="text-muted mb-2">{{ $purchaseOrder->quota->name }}</p>
                <ul class="list-unstyled mb-0 small">
                    <li>Total Allocation: <strong>{{ number_format($purchaseOrder->quota->total_allocation) }}</strong></li>
                    <li>Forecast Remaining: <strong>{{ number_format($purchaseOrder->quota->forecast_remaining) }}</strong></li>
                    <li>Actual Remaining: <strong>{{ number_format($purchaseOrder->quota->actual_remaining) }}</strong></li>
                    <li>Periode: {{ optional($purchaseOrder->quota->period_start)->format('M Y') ?? '-' }} - {{ optional($purchaseOrder->quota->period_end)->format('M Y') ?? '-' }}</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-diagram-project me-2"></i>Alokasi Kuota</h3>
            </div>
            <div class="card-body p-0">
                @php $allocs = $purchaseOrder->allocatedQuotas()->with('products')->get(); @endphp
                @if($allocs->isEmpty())
                    <div class="p-3 text-muted">Belum ada alokasi tercatat.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Quota</th>
                                    <th>Periode</th>
                                    <th class="text-end">Allocated Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allocs as $q)
                                    <tr>
                                        <td>{{ $q->quota_number }}</td>
                                        <td>{{ optional($q->period_start)->format('Y') }}{{ $q->period_end && $q->period_start && $q->period_end->format('Y') !== $q->period_start->format('Y') ? ' - '.optional($q->period_end)->format('Y') : '' }}</td>
                                        <td class="text-end">{{ fmt_qty($q->pivot->allocated_qty) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if($allocs->count() > 1)
                    <div class="p-3"><span class="badge text-bg-warning">Carry-over</span> PO ini dialokasikan lintas periode.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-stream me-2"></i>Timeline</h3>
            </div>
            <div class="card-body">
                <ul class="timeline-sm">
                    <li>
                        <span class="timeline-icon bg-primary"><i class="fas fa-file-invoice"></i></span>
                        <div class="timeline-content">
                            <h6 class="mb-1">PO Dibuat</h6>
                            <p class="text-muted small mb-0">{{ $purchaseOrder->order_date?->format('d M Y') }}</p>
                        </div>
                    </li>
                    @foreach($purchaseOrder->shipments as $shipment)
                        <li>
                            <span class="timeline-icon bg-info"><i class="fas fa-shipping-fast"></i></span>
                            <div class="timeline-content">
                                <h6 class="mb-1">Shipment {{ $shipment->shipment_number }}</h6>
                                <p class="text-muted small mb-0">Kirim: {{ optional($shipment->ship_date)->format('d M Y') ?? '-' }}</p>
                            </div>
                        </li>
                        @foreach($shipment->receipts as $receipt)
                            <li>
                                <span class="timeline-icon bg-success"><i class="fas fa-box-open"></i></span>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Barang Diterima ({{ $shipment->shipment_number }})</h6>
                                    <p class="text-muted small mb-0">{{ $receipt->receipt_date?->format('d M Y') }} - {{ number_format($receipt->quantity_received) }} unit</p>
                                </div>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .timeline-sm {
        list-style: none;
        padding: 0;
        position: relative;
    }
    .timeline-sm::before {
        content: '';
        position: absolute;
        left: 16px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }
    .timeline-sm li {
        position: relative;
        padding-left: 48px;
        margin-bottom: 16px;
    }
    .timeline-icon {
        position: absolute;
        left: 8px;
        top: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
    }
    .timeline-content {
        background: #f9fafb;
        padding: 12px;
        border-radius: 8px;
        border-left: 3px solid #5d87ff;
    }
</style>
@endpush
