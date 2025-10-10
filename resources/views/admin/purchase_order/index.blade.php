{{-- resources/views/admin/purchase_order/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Daftar Purchase Order')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Daftar Purchase Order</li>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-primary">{{ number_format($stats['total_po']) }}</h3>
                <p class="text-muted mb-0">Total PO</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-info">{{ number_format($stats['ordered']) }}</h3>
                <p class="text-muted mb-0">Status Ordered</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-warning">{{ number_format($stats['in_transit']) }}</h3>
                <p class="text-muted mb-0">Sedang Dikirim</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h3 class="text-success">{{ number_format($stats['completed']) }}</h3>
                <p class="text-muted mb-0">Selesai</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title mb-0">
            <i class="fas fa-shopping-cart me-2"></i>Daftar Purchase Order
        </h3>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.purchase-orders.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </a>
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Buat PO Baru
            </a>
        </div>
    </div>
    <div class="card-body">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2">
                <input type="text" name="period" value="{{ request('period') }}" class="form-control" placeholder="Periode (YYYY-MM)">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach([
                        \App\Models\PurchaseOrder::STATUS_ORDERED => 'Ordered',
                        \App\Models\PurchaseOrder::STATUS_IN_TRANSIT => 'In Transit',
                        \App\Models\PurchaseOrder::STATUS_PARTIAL => 'Partial',
                        \App\Models\PurchaseOrder::STATUS_COMPLETED => 'Completed',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari PO, customer, atau branch">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
            <div class="col-md-2 d-grid">
                <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary"><i class="fas fa-sync me-1"></i>Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Period</th>
                        <th>P/O Number</th>
                        <th>PGI Branch</th>
                        <th>Customer Name</th>
                        <th>PIC</th>
                        <th>Status P/O</th>
                        <th>Truck</th>
                        <th>MOQ</th>
                        <th>Category</th>
                        <th>Produk</th>
                        <th class="text-end">Qty</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $po)
                        <tr>
                            <td>{{ $purchaseOrders->firstItem() + $loop->index }}</td>
                            <td>{{ $po->period }}</td>
                            <td>
                                <strong>{{ $po->po_number }}</strong><br>
                                <span class="text-muted small">{{ $po->order_date->format('d M Y') }}</span>
                            </td>
                            <td>{{ $po->pgi_branch ?? '-' }}</td>
                            <td>{{ $po->customer_name ?? '-' }}</td>
                            <td>{{ $po->pic_name ?? '-' }}</td>
                            <td><span class="badge bg-info text-dark">{{ $po->status_po_display ?? ucfirst($po->status) }}</span></td>
                            <td>{{ $po->truck ?? '-' }}</td>
                            <td>{{ $po->moq ?? '-' }}</td>
                            <td>{{ $po->category ?? '-' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $po->product->code }}</div>
                                <small class="text-muted">{{ $po->product->name }}</small>
                            </td>
                            <td class="text-end">
                                {{ number_format($po->quantity) }}<br>
                                <small class="text-muted">Shipped: {{ number_format($po->quantity_shipped) }} | Received: {{ number_format($po->quantity_received) }}</small>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.purchase-orders.show', $po) }}" class="btn btn-sm btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form action="{{ route('admin.purchase-orders.destroy', $po) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus PO ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted">Belum ada purchase order.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end">
            {{ $purchaseOrders->links() }}
        </div>
    </div>
</div>
@endsection
