{{-- resources/views/admin/purchase_order/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Daftar Purchase Order')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Daftar Purchase Order</li>
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
        min-width: 2400px !important;
        margin: 0;
        table-layout: auto !important;
    }

    .po-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8faff;
        padding: 15px 18px;
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
        border-bottom: 1px solid #e6ebf5;
        white-space: nowrap;
        font-weight: 600;
    }

    .po-table tbody td {
        padding: 16px 18px;
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
        }
    }

    @media (max-width: 576px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .app-content {
            padding: 16px 16px 24px;
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
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Daftar Purchase Order</h1>
            <p class="page-header__subtitle">Pantau status purchase order, progres pengiriman, dan detail pelanggan dalam satu tampilan ringkas.</p>
        </div>
        <div class="page-header__actions">
            @if(auth()->user()?->can('product.create'))
            <a href="{{ route('admin.master.quick_hs.create') }}" class="page-header__button">
                <i class="fas fa-plus"></i>
                Tambah Model -> HS
            </a>
            @endif
            <a href="{{ route('admin.purchase-orders.export', request()->query()) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-export"></i>
                Export CSV
            </a>
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
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nomor PO, vendor, atau item">
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

    <div class="table-wrapper">
        <div class="table-shell">
            <div class="scroll-indicator" id="scrollIndicator">
                <i class="fas fa-arrows-alt-h"></i>
                <span>Geser ke samping untuk melihat lebih banyak</span>
            </div>
            <div class="table-scroll" id="tableScroll">
                <table class="po-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>PO Doc</th>
                            <th>Created Date</th>
                            <th>Vendor No</th>
                            <th>Vendor Name</th>
                            <th>Line No</th>
                            <th>Item Code</th>
                            <th>Item Desc</th>
                            <th>WH Code</th>
                            <th>WH Name</th>
                            <th>WH Source</th>
                            <th>Subinv Code</th>
                            <th>Subinv Name</th>
                            <th>Subinv Source</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>Cat PO</th>
                            <th>Cat Desc</th>
                            <th>Mat Grp</th>
                            <th>SAP Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseOrders as $po)
                            <tr>
                                <td>{{ $purchaseOrders->firstItem() + $loop->index }}</td>
                                <td><strong>{{ $po->po_number }}</strong></td>
                                <td>
                                    @php
                                        $dt = $po->order_date ?? null;
                                        try { $val = $dt ? \Illuminate\Support\Carbon::parse($dt)->format('d M Y') : null; } catch (\Throwable $e) { $val = (string) $dt; }
                                    @endphp
                                    {{ $val ?: '-' }}
                                </td>
                                <td>{{ $po->vendor_number ?? '-' }}</td>
                                <td>{{ $po->vendor_name ?? '-' }}</td>
                                <td>{{ $po->line_number ?? '-' }}</td>
                                <td>{{ $po->item_code ?? '-' }}</td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Str::limit($po->item_description ?? '-', 36) }}</td>
                                <td>{{ $po->warehouse_code ?? '-' }}</td>
                                <td>{{ $po->warehouse_name ?? '-' }}</td>
                                <td>{{ $po->warehouse_source ?? '-' }}</td>
                                <td>{{ $po->subinventory_code ?? '-' }}</td>
                                <td>{{ $po->subinventory_name ?? '-' }}</td>
                                <td>{{ $po->subinventory_source ?? '-' }}</td>
                                <td class="text-end">
                                    {{ number_format($po->quantity) }}
                                </td>
                                <td class="text-end">
                                    {{ $po->amount !== null ? number_format($po->amount, 2) : '-' }}
                                </td>
                                <td>{{ $po->category_code ?? '-' }}</td>
                                <td>{{ $po->category ?? '-' }}</td>
                                <td>{{ $po->material_group ?? '-' }}</td>
                                <td>{{ $po->sap_order_status ?? '-' }}</td>
                                <td class="text-end">
                                    <div class="table-actions">
                                        <span class="action-icon action-icon--view" title="Detail tidak tersedia untuk data Open PO">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                        @can('delete purchase_orders')
                                            @if(isset($po->id))
                                            <form action="{{ route('admin.purchase-orders.destroy', $po->id) }}" method="POST" onsubmit="return confirm('Hapus PO ini?');" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="action-icon action-icon--delete" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="21" class="text-center text-muted py-4">Belum ada purchase order.</td>
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
    console.log('🚀 Purchase Order Table Scroll Script Initialized');

    const tableScroll = document.querySelector('.table-scroll');
    const scrollIndicator = document.querySelector('.scroll-indicator');

    if (!tableScroll) {
        console.error('❌ Element .table-scroll tidak ditemukan!');
        return;
    }

    console.log('✅ Table scroll element found');
    console.log('📊 Dimensions:', {
        scrollWidth: tableScroll.scrollWidth,
        clientWidth: tableScroll.clientWidth,
        isScrollable: tableScroll.scrollWidth > tableScroll.clientWidth
    });

    function checkScrollable() {
        const isScrollable = tableScroll.scrollWidth > tableScroll.clientWidth;
        console.log('🔍 Is scrollable:', isScrollable);

        if (isScrollable && scrollIndicator) {
            scrollIndicator.classList.add('show');
            console.log('👉 Showing scroll indicator');

            setTimeout(() => {
                scrollIndicator.classList.remove('show');
                console.log('👈 Hiding scroll indicator');
            }, 4000);
        }
    }

    window.addEventListener('load', () => {
        console.log('📄 Page fully loaded, checking scrollability...');
        setTimeout(checkScrollable, 300);
    });

    setTimeout(checkScrollable, 500);

    let hasScrolled = false;
    tableScroll.addEventListener('scroll', function() {
        if (!hasScrolled && scrollIndicator) {
            console.log('🖱️ User started scrolling');
            hasScrolled = true;
            scrollIndicator.classList.remove('show');
        }

        if (tableScroll.scrollLeft > 0 && tableScroll.scrollLeft % 100 < 5) {
            console.log('📍 Scroll position:', tableScroll.scrollLeft);
        }
    });

    tableScroll.addEventListener('wheel', function(e){
        if (e.shiftKey) {
            e.preventDefault();
            const scrollAmount = e.deltaY;
            tableScroll.scrollLeft += scrollAmount;
            console.log('⌨️ Shift+wheel scroll:', scrollAmount);
        }
    }, { passive: false });

    let startX = 0;
    let scrollLeft = 0;
    let isDown = false;

    tableScroll.addEventListener('touchstart', function(e) {
        isDown = true;
        startX = e.touches[0].pageX - tableScroll.offsetLeft;
        scrollLeft = tableScroll.scrollLeft;
        console.log('👆 Touch start:', startX);
    }, { passive: true });

    tableScroll.addEventListener('touchend', function() {
        isDown = false;
        console.log('👆 Touch end');
    }, { passive: true });

    tableScroll.addEventListener('touchmove', function(e) {
        if (!isDown) return;
        e.preventDefault();
        const x = e.touches[0].pageX - tableScroll.offsetLeft;
        const walk = (x - startX) * 2;
        tableScroll.scrollLeft = scrollLeft - walk;
    }, { passive: false });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                console.log('👁️ Table is now visible in viewport');
                setTimeout(checkScrollable, 100);
            }
        });
    }, { threshold: 0.1 });

    observer.observe(tableScroll);

    console.log('✅ All event listeners attached successfully');
})();
</script>
@endpush
