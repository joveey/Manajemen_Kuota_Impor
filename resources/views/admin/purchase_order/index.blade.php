{{-- resources/views/admin/purchase_order/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Purchase Orders')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Purchase Orders</li>
@endsection

@push('styles')
<style>
    body, html {
        overflow-x: visible !important;
    }

    .app-content {
        overflow-x: visible !important;
        overflow-y: visible !important;
        padding: 20px 36px 36px;
        padding-top: calc(var(--app-bar-height-computed, var(--app-bar-height)) + 20px);
        width: 100%;
    }

    .page-shell {
        overflow: visible !important;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .summary-tile {
        border-radius: 18px;
        border: 1px solid #e6ebf5;
        background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
        padding: 20px;
        box-shadow: 0 24px 48px -44px rgba(15,23,42,.45);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .summary-tile__label {
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .12em;
    }

    .summary-tile__value {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
    }

    .filter-panel {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e6ebf5;
        padding: 18px 22px;
        box-shadow: 0 20px 44px -40px rgba(15,23,42,.45);
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 20px;
    }

    .filter-panel__control {
        flex: 1 1 200px;
    }

    .filter-panel__control input,
    .filter-panel__control select {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 10px 14px;
        font-size: 13px;
        width: 100%;
    }

    .filter-panel__buttons {
        display: flex;
        gap: 10px;
        flex: 1 1 200px;
    }

    .filter-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border-radius: 12px;
        padding: 10px 16px;
        font-weight: 600;
        font-size: 13px;
        border: 1px solid transparent;
        width: 100%;
        cursor: pointer;
        text-decoration: none;
    }

    .filter-button--apply {
        background: #2563eb;
        color: #fff;
    }

    .filter-button--reset {
        background: rgba(148,163,184,.12);
        color: #1f2937;
        border-color: rgba(148,163,184,.32);
    }

    .table-wrapper {
        width: 100%;
        overflow: visible !important;
        position: relative;
    }

    .table-shell {
        background: #fff;
        border: 1px solid #e6ebf5;
        border-radius: 22px;
        box-shadow: 0 32px 64px -48px rgba(15,23,42,.45);
        overflow: visible !important;
        width: 100%;
        position: relative;
    }

    .table-scroll {
        overflow-x: auto !important;
        overflow-y: visible !important;
        width: 100% !important;
        -webkit-overflow-scrolling: touch;
        display: block !important;
        border-radius: 22px;
        position: relative;
    }

    .table-scroll::-webkit-scrollbar {
        height: 14px !important;
    }

    .table-scroll::-webkit-scrollbar-track {
        background: #f1f5f9 !important;
        border-radius: 0 0 22px 22px;
    }

    .table-scroll::-webkit-scrollbar-thumb {
        background: #94a3b8 !important;
        border-radius: 10px !important;
        border: 3px solid #f1f5f9;
    }

    .table-scroll::-webkit-scrollbar-thumb:hover {
        background: #64748b !important;
    }

    .table-scroll {
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
    }

    .po-table {
        width: 100% !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        min-width: 1200px !important; /* lebih rapat, kurangi scroll horizontal */
        margin: 0;
        table-layout: auto !important;
    }

    .po-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8faff;
        padding: 10px 12px; /* kurangi jarak header */
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
        border-bottom: 1px solid #e6ebf5;
        white-space: nowrap;
        font-weight: 600;
    }

    .po-table tbody td {
        padding: 10px 12px; /* kurangi jarak isi */
        border-bottom: 1px solid #eef2fb;
        font-size: 13px;
        color: #1f2937;
        vertical-align: top;
        white-space: nowrap;
    }

    .po-table tbody tr:hover {
        background: rgba(37,99,235,.04);
    }

    .po-table tbody tr:last-child td {
        border-bottom: none;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .status-badge--ordered {
        background: rgba(59,130,246,.16);
        color: #1d4ed8;
    }

    .status-badge--in-transit {
        background: rgba(251,191,36,.16);
        color: #92400e;
    }

    .status-badge--partial {
        background: rgba(96,165,250,.16);
        color: #1d4ed8;
    }

    .status-badge--completed {
        background: rgba(34,197,94,.16);
        color: #166534;
    }

    .po-table__subtext {
        font-size: 11.5px;
        color: #94a3b8;
    }

    .table-actions {
        display: inline-flex;
        gap: 10px;
    }

    .action-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        transition: all .2s ease;
        border: none;
        cursor: pointer;
    }

    .action-icon--view {
        background: rgba(37,99,235,.12);
        color: #2563eb;
    }

    .action-icon--delete {
        background: rgba(248,113,113,.16);
        color: #dc2626;
    }

    .action-icon:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,.1);
    }

    .pagination-modern {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .scroll-indicator {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: white;
        padding: 10px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        pointer-events: none;
        opacity: 0;
        transition: opacity .4s ease;
        z-index: 100;
        box-shadow: 0 8px 20px -8px rgba(37,99,235,.6);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .scroll-indicator.show {
        opacity: 1;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: translateY(-50%) scale(1); }
        50% { transform: translateY(-50%) scale(1.05); }
    }

    @media (max-width: 992px) {
        .filter-panel__buttons {
            flex-direction: column;
        }

        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .app-content {
            padding: 16px 20px 28px;
            padding-top: calc(var(--app-bar-height-computed, var(--app-bar-height)) + 16px);
        }
    }

    @media (max-width: 576px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .app-content {
            padding: 16px 16px 24px;
            padding-top: calc(var(--app-bar-height-computed, var(--app-bar-height)) + 16px);
        }

        .page-header__title {
            font-size: 20px;
        }

        .table-scroll::-webkit-scrollbar {
            height: 10px !important;
        }
    }
</style>
@endpush

@section('content')
@php
    $statusBadgeMap = [
        \App\Models\PurchaseOrder::STATUS_ORDERED => ['label' => 'Ordered', 'class' => 'status-badge--ordered'],
        \App\Models\PurchaseOrder::STATUS_PARTIAL => ['label' => 'In Progress', 'class' => 'status-badge--in-transit'],
        \App\Models\PurchaseOrder::STATUS_COMPLETED => ['label' => 'Completed', 'class' => 'status-badge--completed'],
    ];
@endphp
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Purchase Orders</h1>
            <p class="page-header__subtitle">Track purchase order status, shipment progress, and customer details in one concise view.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.purchase-orders.export', request()->query()) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-export"></i>
                Export CSV
            </a>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-tile">
            <span class="summary-tile__label">Total POs</span>
            <span class="summary-tile__value">{{ number_format($stats['total_po']) }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile__label">Status Ordered</span>
            <span class="summary-tile__value">{{ number_format($stats['ordered']) }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile__label">In Transit</span>
            <span class="summary-tile__value">{{ number_format($stats['in_transit']) }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile__label">Completed</span>
            <span class="summary-tile__value">{{ number_format($stats['completed']) }}</span>
        </div>
    </div>

    <form method="GET" class="filter-panel">
        <div class="filter-panel__control">
            <label class="form-label text-muted small mb-1">Period</label>
            <input type="text" name="period" value="{{ request('period') }}" class="form-control" placeholder="YYYY-MM">
        </div>
        <div class="filter-panel__control">
            <label class="form-label text-muted small mb-1">Status</label>
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
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
            <label class="form-label text-muted small mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search PO number, vendor, or item">
        </div>
        <div class="filter-panel__buttons">
            <button class="filter-button filter-button--apply" type="submit">
                <i class="fas fa-filter"></i>
                Apply
            </button>
            <a href="{{ route('admin.purchase-orders.index') }}" class="filter-button filter-button--reset">
                <i class="fas fa-sync"></i>
                Reset
            </a>
        </div>
    </form>

    <div class="table-wrapper">
        <div class="table-shell">
            <div class="scroll-indicator" id="scrollIndicator">
                <i class="fas fa-arrows-alt-h"></i>
                <span>Scroll sideways to see more</span>
            </div>
            <div class="table-scroll" id="tableScroll">
                <table class="po-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>PO Doc</th>
                            <th>Document Date</th>
                            <th>Delivery Date</th>
                            <th>Vendor</th>
                            <th>Storage Loc</th>
                            <th class="text-end">Total Line</th>
                            <th class="text-end">Order Qty</th>
                            <th class="text-end">To Invoice</th>
                            <th class="text-end">To Deliver</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Outstanding</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseOrders as $po)
                            @php
                                $orderDateLabel = '-';
                                if (!empty($po->latest_order_date)) {
                                    try {
                                        $latest = \Illuminate\Support\Carbon::parse($po->latest_order_date)->format('d M Y');
                                    } catch (\Throwable $e) {
                                        $latest = (string) $po->latest_order_date;
                                    }

                                    $orderDateLabel = $latest;

                                    if (!empty($po->first_order_date) && $po->first_order_date !== $po->latest_order_date) {
                                        try {
                                            $first = \Illuminate\Support\Carbon::parse($po->first_order_date)->format('d M Y');
                                        } catch (\Throwable $e) {
                                            $first = (string) $po->first_order_date;
                                        }
                                        $orderDateLabel = $first.' - '.$latest;
                                    }
                                }
                                // Delivery date label (from po_lines.eta_date min..max)
                                $delivDateLabel = '-';
                                if (!empty($po->latest_deliv_date)) {
                                    try { $latestD = \Illuminate\Support\Carbon::parse($po->latest_deliv_date)->format('d M Y'); } catch (\Throwable $e) { $latestD = (string) $po->latest_deliv_date; }
                                    $delivDateLabel = $latestD;
                                    if (!empty($po->first_deliv_date) && $po->first_deliv_date !== $po->latest_deliv_date) {
                                        try { $firstD = \Illuminate\Support\Carbon::parse($po->first_deliv_date)->format('d M Y'); } catch (\Throwable $e) { $firstD = (string) $po->first_deliv_date; }
                                        $delivDateLabel = $firstD.' - '.$latestD;
                                    }
                                }
                                $statusMeta = $statusBadgeMap[$po->status_key] ?? ['label' => ucfirst($po->status_key ?? 'Ordered'), 'class' => 'status-badge--ordered'];
                            @endphp
                            <tr>
                                <td>{{ $purchaseOrders->firstItem() + $loop->index }}</td>
                                <td>
                                    <strong>{{ $po->po_number }}</strong>
                                    <div class="po-table__subtext">Header: {{ (int) ($po->header_count ?? 0) }} • Vendor No: {{ $po->vendor_number ?? '-' }}</div>
                                </td>
                                <td>{{ $orderDateLabel }}</td>
                                <td>{{ $delivDateLabel }}</td>
                                <td>
                                    <div>{{ $po->vendor_name ?? '-' }}</div>
                                    @if(!empty($po->sap_statuses))
                                        <div class="po-table__subtext">SAP: {{ $po->sap_statuses }}</div>
                                    @endif
                                </td>
                                <td>{{ $po->storage_locations ?? '-' }}</td>
                                <td class="text-end">{{ number_format((int) $po->total_lines) }}</td>
                                <td class="text-end">{{ number_format((float) $po->total_qty_ordered, 0) }}</td>
                                <td class="text-end">{{ $po->total_qty_to_invoice !== null ? number_format((float) $po->total_qty_to_invoice, 0) : '-' }}</td>
                                <td class="text-end">{{ $po->total_qty_to_deliver !== null ? number_format((float) $po->total_qty_to_deliver, 0) : '-' }}</td>
                                <td class="text-end">{{ number_format((float) $po->total_qty_received, 0) }}</td>
                                <td class="text-end">{{ number_format((float) $po->total_qty_outstanding, 0) }}</td>
                                <td>
                                    <span class="status-badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="table-actions">
                                        <a href="{{ route('admin.purchase-orders.document', ['poNumber' => $po->po_number]) }}"
                                            class="action-icon action-icon--view" title="View PO details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No purchase orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="pagination-modern">
        {{ $purchaseOrders->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const tableScroll = document.querySelector('.table-scroll');
    const scrollIndicator = document.querySelector('.scroll-indicator');

    if (!tableScroll) {
        return;
    }

    const checkScrollable = () => {
        const isScrollable = tableScroll.scrollWidth > tableScroll.clientWidth;
        if (isScrollable && scrollIndicator) {
            scrollIndicator.classList.add('show');
            setTimeout(() => scrollIndicator.classList.remove('show'), 4000);
        }
    };

    window.addEventListener('load', () => setTimeout(checkScrollable, 300));
    setTimeout(checkScrollable, 500);

    let hasScrolled = false;
    tableScroll.addEventListener('scroll', () => {
        if (!hasScrolled && scrollIndicator) {
            hasScrolled = true;
            scrollIndicator.classList.remove('show');
        }
    });

    tableScroll.addEventListener('wheel', (e) => {
        if (e.shiftKey) {
            e.preventDefault();
            tableScroll.scrollLeft += e.deltaY;
        }
    }, { passive: false });

    let startX = 0;
    let scrollLeft = 0;
    let isDown = false;

    tableScroll.addEventListener('touchstart', (e) => {
        isDown = true;
        startX = e.touches[0].pageX - tableScroll.offsetLeft;
        scrollLeft = tableScroll.scrollLeft;
    }, { passive: true });

    tableScroll.addEventListener('touchend', () => {
        isDown = false;
    }, { passive: true });

    tableScroll.addEventListener('touchmove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.touches[0].pageX - tableScroll.offsetLeft;
        const walk = (x - startX) * 2;
        tableScroll.scrollLeft = scrollLeft - walk;
    }, { passive: false });
})();
</script>
@endpush
