{{-- resources/views/admin/purchase_order/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Daftar Purchase Order')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Daftar Purchase Order</li>
@endsection

@push('styles')
<style>
    .po-page { display:flex; flex-direction:column; gap:28px; }
    .po-header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:18px; align-items:flex-start; }
    .po-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .po-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:540px; }
    .po-actions { display:flex; gap:12px; }
    .po-action { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:14px; font-size:13px; font-weight:600; text-decoration:none; transition:all .2s ease; border:1px solid transparent; }
    .po-action--outline { background:rgba(148,163,184,.1); color:#1f2937; border-color:rgba(148,163,184,.35); }
    .po-action--outline:hover { background:rgba(148,163,184,.16); }
    .po-action--primary { background:#2563eb; color:#fff; box-shadow:0 18px 38px -30px rgba(37,99,235,.8); }
    .po-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }
    .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
    .summary-tile { border-radius:18px; border:1px solid #e6ebf5; background:linear-gradient(135deg,#fff 0%,#f8fafc 100%); padding:20px; box-shadow:0 24px 48px -44px rgba(15,23,42,.45); display:flex; flex-direction:column; gap:6px; }
    .summary-tile__label { font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:.12em; }
    .summary-tile__value { font-size:24px; font-weight:700; color:#0f172a; }
    .filter-panel { background:#fff; border-radius:20px; border:1px solid #e6ebf5; padding:18px 22px; box-shadow:0 20px 44px -40px rgba(15,23,42,.45); display:flex; flex-wrap:wrap; gap:14px; }
    .filter-panel__control { flex:1 1 200px; }
    .filter-panel__control input,
    .filter-panel__control select { border-radius:12px; border:1px solid #e2e8f0; padding:10px 14px; font-size:13px; }
    .filter-panel__buttons { display:flex; gap:10px; flex:1 1 200px; }
    .filter-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; border-radius:12px; padding:10px 16px; font-weight:600; font-size:13px; border:1px solid transparent; width:100%; }
    .filter-button--apply { background:#2563eb; color:#fff; }
    .filter-button--reset { background:rgba(148,163,184,.12); color:#1f2937; border-color:rgba(148,163,184,.32); }
    .table-shell { background:#fff; border:1px solid #e6ebf5; border-radius:22px; overflow:hidden; box-shadow:0 32px 64px -48px rgba(15,23,42,.45); }
    .po-table { width:100%; border-collapse:separate; border-spacing:0; }
    .po-table thead th { background:#f8faff; padding:15px 18px; font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5; }
    .po-table tbody td { padding:16px 18px; border-bottom:1px solid #eef2fb; font-size:13px; color:#1f2937; vertical-align:top; }
    .po-table tbody tr:hover { background:rgba(37,99,235,.04); }
    .status-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; }
    .status-badge--ordered { background:rgba(59,130,246,.16); color:#1d4ed8; }
    .status-badge--in-transit { background:rgba(251,191,36,.16); color:#92400e; }
    .status-badge--partial { background:rgba(96,165,250,.16); color:#1d4ed8; }
    .status-badge--completed { background:rgba(34,197,94,.16); color:#166534; }
    .po-table__subtext { font-size:11.5px; color:#94a3b8; }
    .table-actions { display:inline-flex; gap:10px; }
    .action-icon { width:32px; height:32px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; transition:all .2s ease; border:none; }
    .action-icon--view { background:rgba(37,99,235,.12); color:#2563eb; }
    .action-icon--delete { background:rgba(248,113,113,.16); color:#dc2626; }
    .action-icon:hover { transform:translateY(-1px); }
    .pagination-modern { display:flex; justify-content:flex-end; margin-top:20px; }
    @media (max-width: 992px) {
        .po-header { flex-direction:column; align-items:stretch; }
        .po-actions { justify-content:flex-start; }
        .filter-panel__buttons { flex-direction:column; }
    }
</style>
@endpush

@section('content')
<div class="po-page">
    <div class="po-header">
        <div>
            <h1 class="po-title">Daftar Purchase Order</h1>
            <p class="po-subtitle">Pantau status purchase order, progres pengiriman, dan detail pelanggan dalam satu tampilan ringkas.</p>
        </div>
        <div class="po-actions">
            <a href="{{ route('admin.purchase-orders.export', request()->query()) }}" class="po-action po-action--outline">
                <i class="fas fa-file-export"></i>
                Export CSV
            </a>
            @can('create purchase_orders')
                <a href="{{ route('admin.purchase-orders.create') }}" class="po-action po-action--primary">
                    <i class="fas fa-plus"></i>
                    Buat PO Baru
                </a>
            @endcan
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-tile">
            <span class="summary-tile__label">Total PO</span>
            <span class="summary-tile__value">{{ number_format($stats['total_po']) }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile__label">Status Ordered</span>
            <span class="summary-tile__value">{{ number_format($stats['ordered']) }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile__label">Sedang Dikirim</span>
            <span class="summary-tile__value">{{ number_format($stats['in_transit']) }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile__label">Selesai</span>
            <span class="summary-tile__value">{{ number_format($stats['completed']) }}</span>
        </div>
    </div>

    <form method="GET" class="filter-panel">
        <div class="filter-panel__control">
            <label class="form-label text-muted small mb-1">Periode</label>
            <input type="text" name="period" value="{{ request('period') }}" class="form-control" placeholder="YYYY-MM">
        </div>
        <div class="filter-panel__control">
            <label class="form-label text-muted small mb-1">Status</label>
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
        <div class="filter-panel__control" style="flex:2">
            <label class="form-label text-muted small mb-1">Pencarian</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nomor PO, pelanggan, atau branch">
        </div>
        <div class="filter-panel__buttons">
            <button class="filter-button filter-button--apply" type="submit">
                <i class="fas fa-filter"></i>
                Terapkan
            </button>
            <a href="{{ route('admin.purchase-orders.index') }}" class="filter-button filter-button--reset">
                <i class="fas fa-sync"></i>
                Reset
            </a>
        </div>
    </form>

    <div class="table-shell">
        <table class="po-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Period</th>
                    <th>P/O Number</th>
                    <th>PGI Branch</th>
                    <th>Customer</th>
                    <th>PIC</th>
                    <th>Status</th>
                    <th>Truck</th>
                    <th>MOQ</th>
                    <th>Category</th>
                    <th>Produk</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $po)
                    @php
                        $statusStyles = [
                            \App\Models\PurchaseOrder::STATUS_ORDERED => 'status-badge--ordered',
                            \App\Models\PurchaseOrder::STATUS_IN_TRANSIT => 'status-badge--in-transit',
                            \App\Models\PurchaseOrder::STATUS_PARTIAL => 'status-badge--partial',
                            \App\Models\PurchaseOrder::STATUS_COMPLETED => 'status-badge--completed',
                        ];
                        $statusClass = $statusStyles[$po->status] ?? 'status-badge--ordered';
                    @endphp
                    <tr>
                        <td>{{ $purchaseOrders->firstItem() + $loop->index }}</td>
                        <td>{{ $po->period }}</td>
                        <td>
                            <strong>{{ $po->po_number }}</strong><br>
                            <span class="po-table__subtext">{{ $po->order_date->format('d M Y') }}</span>
                        </td>
                        <td>{{ $po->pgi_branch ?? '-' }}</td>
                        <td>{{ $po->customer_name ?? '-' }}</td>
                        <td>{{ $po->pic_name ?? '-' }}</td>
                        <td>
                            <span class="status-badge {{ $statusClass }}">{{ $po->status_po_display ?? ucfirst($po->status) }}</span>
                        </td>
                        <td>{{ $po->truck ?? '-' }}</td>
                        <td>{{ $po->moq ?? '-' }}</td>
                        <td>{{ $po->category ?? '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $po->product->code }}</div>
                            <div class="po-table__subtext">{{ $po->product->name }}</div>
                        </td>
                        <td class="text-end">
                            {{ number_format($po->quantity) }}<br>
                            <span class="po-table__subtext">
                                Shipped: {{ number_format($po->quantity_shipped) }} | Received: {{ number_format($po->quantity_received) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="table-actions">
                                <a href="{{ route('admin.purchase-orders.show', $po) }}" class="action-icon action-icon--view" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('delete purchase_orders')
                                    <form action="{{ route('admin.purchase-orders.destroy', $po) }}" method="POST" onsubmit="return confirm('Hapus PO ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-icon action-icon--delete" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endcan
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

    <div class="pagination-modern">
        {{ $purchaseOrders->links() }}
    </div>
</div>
@endsection
