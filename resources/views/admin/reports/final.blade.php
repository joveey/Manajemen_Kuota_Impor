{{-- resources/views/admin/reports/final.blade.php --}}
@extends('layouts.admin')

@section('title', 'Laporan Gabungan')
@section('page-title', 'Laporan Gabungan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Laporan Gabungan</li>
@endsection

@push('styles')
<style>
    .report-shell { display:flex; flex-direction:column; gap:24px; }
    .report-card {
        border:1px solid #e3e9f5;
        border-radius:16px;
        background:#ffffff;
        box-shadow:0 18px 42px -36px rgba(15,23,42,0.35);
        padding:20px 24px;
    }
    .report-card__title { font-size:16px; font-weight:600; margin:0 0 12px; color:#0f172a; }
    .report-filters .form-label { font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; }
    .report-filters .btn { font-weight:600; }

    .report-summary {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
        gap:16px;
    }
    .report-summary__item {
        border:1px solid #e5e9f7;
        border-radius:14px;
        background:linear-gradient(135deg,#ffffff 0%,#f8fbff 100%);
        padding:16px 18px;
        box-shadow:0 14px 32px -28px rgba(15,23,42,0.28);
        display:flex;
        flex-direction:column;
        gap:6px;
    }
    .report-summary__label { font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; }
    .report-summary__value { font-size:22px; font-weight:700; color:#0f172a; }
    .report-summary__meta { font-size:12px; color:#64748b; }

    .report-highlight-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:16px;
    }
    .report-highlight {
        border:1px solid rgba(37,99,235,0.18);
        border-radius:14px;
        padding:16px 18px;
        background:linear-gradient(135deg,rgba(37,99,235,0.08) 0%, #ffffff 100%);
        display:flex;
        flex-direction:column;
        gap:6px;
    }
    .report-highlight__title { font-weight:600; color:#1d4ed8; margin:0; }
    .report-highlight__meta { font-size:12px; color:#475569; }

    .report-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
        gap:20px;
    }
    .chart-container { min-height:300px; margin-top:12px; }

    .status-list {
        list-style:none;
        margin:0;
        padding:0;
        display:flex;
        flex-direction:column;
        gap:10px;
    }
    .status-list__item {
        display:flex;
        justify-content:space-between;
        align-items:center;
        border:1px solid #e5eaf5;
        border-radius:12px;
        padding:10px 14px;
        font-size:13px;
        color:#475569;
    }
    .status-list__badge { font-weight:600; color:#2563eb; }

    .report-table-wrapper {
        border:1px solid #e3e9f5;
        border-radius:16px;
        overflow:hidden;
        background:#ffffff;
    }
    .report-table thead th {
        background:#f8fbff;
        color:#64748b;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:0.08em;
        padding:12px 14px;
    }
    .report-table tbody td {
        padding:14px;
        font-size:13px;
        color:#1f2937;
        border-top:1px solid #e5eaf5;
    }
    .report-table tbody tr:hover { background:rgba(37,99,235,0.04); }

    .shipments-list {
        list-style:none;
        margin:0;
        padding:0;
        display:flex;
        flex-direction:column;
        gap:12px;
    }
    .shipments-item {
        border:1px solid rgba(148,163,184,0.18);
        border-radius:12px;
        padding:12px 14px;
        background:linear-gradient(135deg,#f8fbff 0%, #ffffff 100%);
        display:flex;
        flex-direction:column;
        gap:8px;
        font-size:13px;
        color:#475569;
    }
    .shipments-badge {
        align-self:flex-start;
        border-radius:999px;
        background:rgba(248,113,113,0.16);
        color:#b91c1c;
        font-weight:600;
        padding:4px 10px;
        font-size:12px;
    }

    @media (max-width: 640px) {
        .report-card { padding:18px; }
        .report-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@php
    $filters = $filters ?? [];
    $summary = $summary ?? [];
    $rows = $rows ?? [];
    $highlights = $highlights ?? [];
    $poStatus = $po_status ?? [];
    $shipmentStatus = $shipment_status ?? [];
    $charts = $charts ?? ['quota_bar' => ['categories' => [], 'series' => []], 'monthly_line' => ['categories' => [], 'series' => []], 'shipment_donut' => ['labels' => [], 'series' => []]];
    $topShipments = $topShipments ?? [];

    $summaryCards = [
        ['label' => 'Total PO', 'value' => number_format($summary['po_count'] ?? 0), 'meta' => 'dokumen'],
        ['label' => 'PO Outstanding', 'value' => number_format($summary['po_outstanding'] ?? 0), 'meta' => 'unit'],
        ['label' => 'Shipment Received', 'value' => number_format($summary['shipment_received'] ?? 0), 'meta' => 'unit diterima'],
        ['label' => 'Shipment Outstanding', 'value' => number_format($summary['shipment_outstanding'] ?? 0), 'meta' => 'unit'],
        ['label' => 'Total Allocation', 'value' => number_format($summary['total_allocation'] ?? 0), 'meta' => 'unit kuota'],
        ['label' => 'Actual Remaining', 'value' => number_format($summary['total_actual_remaining'] ?? 0), 'meta' => 'unit tersedia'],
    ];
@endphp

@section('content')
<div class="page-shell report-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Laporan Gabungan</h1>
            <p class="page-header__subtitle">Ringkasan performa kuota, purchase order, dan shipment pada periode yang dipilih.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.reports.final.export.csv', request()->query()) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </a>
        </div>
    </div>

    <div class="report-card report-filters">
        <h2 class="report-card__title">Filter Periode</h2>
        <form method="GET" action="{{ route('admin.reports.final') }}" class="row gy-3 gx-3 align-items-end">
            <div class="col-12 col-md-5">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="col-12 col-md-5">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Terapkan</button>
                <a href="{{ route('admin.reports.final') }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="report-card">
        <h2 class="report-card__title">Ringkasan</h2>
        <div class="report-summary">
            @foreach($summaryCards as $card)
                <div class="report-summary__item">
                    <span class="report-summary__label">{{ $card['label'] }}</span>
                    <span class="report-summary__value">{{ $card['value'] }}</span>
                    <span class="report-summary__meta">{{ $card['meta'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="report-card">
        <h2 class="report-card__title">Kuota dengan Realisasi Tertinggi</h2>
        @if(empty($highlights))
            <p class="text-muted mb-0">Belum ada kuota yang menonjol pada periode ini.</p>
        @else
            <div class="report-highlight-grid">
                @foreach($highlights as $item)
                    <div class="report-highlight">
                        <span class="report-highlight__meta">{{ $item['quota_number'] }} — {{ $item['range_pk'] }}</span>
                        <span class="report-highlight__title">{{ $item['quota_name'] }}</span>
                        <span class="report-highlight__meta">Realisasi: {{ number_format($item['shipment_received']) }} unit</span>
                        <span class="report-highlight__meta">Outstanding: {{ number_format($item['shipment_outstanding']) }} unit</span>
                        <span class="report-highlight__meta fw-semibold text-primary">{{ $item['actual_pct'] }}% dari alokasi</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="report-grid">
        <div class="report-card">
            <h2 class="report-card__title">Realisasi per Kuota</h2>
            <div id="report-quota-bar" class="chart-container"></div>
        </div>
        <div class="report-card">
            <h2 class="report-card__title">Realisasi Bulanan</h2>
            <div id="report-monthly-line" class="chart-container"></div>
        </div>
        <div class="report-card">
            <h2 class="report-card__title">Komposisi Shipment</h2>
            <div id="report-shipment-donut" class="chart-container"></div>
        </div>
    </div>

    <div class="report-grid">
        <div class="report-card">
            <h2 class="report-card__title">Status Purchase Order</h2>
            @if(empty($poStatus))
                <p class="text-muted mb-0">Tidak ada data PO pada periode ini.</p>
            @else
                <ul class="status-list">
                    @foreach($poStatus as $status => $total)
                        <li class="status-list__item">
                            <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="status-list__badge">{{ number_format($total) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="report-card">
            <h2 class="report-card__title">Status Shipment</h2>
            @if(empty($shipmentStatus))
                <p class="text-muted mb-0">Tidak ada data shipment pada periode ini.</p>
            @else
                <ul class="status-list">
                    @foreach($shipmentStatus as $status => $total)
                        <li class="status-list__item">
                            <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="status-list__badge">{{ number_format($total) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="report-table-wrapper">
        <table class="table report-table mb-0">
            <thead>
                <tr>
                    <th>Quota</th>
                    <th>Range PK</th>
                    <th>Total Allocation</th>
                    <th>Forecast Remaining</th>
                    <th>Actual Remaining</th>
                    <th>PO Count</th>
                    <th>PO Quantity</th>
                    <th>PO Received</th>
                    <th>Shipment Count</th>
                    <th>Shipment Planned</th>
                    <th>Shipment Received</th>
                    <th>Outstanding</th>
                    <th>Last Receipt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><strong>{{ $row['quota_number'] }}</strong><br><span class="text-muted small">{{ $row['quota_name'] }}</span></td>
                        <td>{{ $row['range_pk'] }}</td>
                        <td>{{ number_format($row['total_allocation']) }}</td>
                        <td>{{ number_format($row['forecast_remaining']) }}</td>
                        <td>{{ number_format($row['actual_remaining']) }}</td>
                        <td>{{ number_format($row['po_count']) }}</td>
                        <td>{{ number_format($row['po_quantity']) }}</td>
                        <td>{{ number_format($row['po_received']) }}</td>
                        <td>{{ number_format($row['shipment_count']) }}</td>
                        <td>{{ number_format($row['shipment_planned']) }}</td>
                        <td>{{ number_format($row['shipment_received']) }}</td>
                        <td>{{ number_format($row['shipment_outstanding']) }}</td>
                        <td>{{ $row['last_receipt_date'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted py-4">Belum ada data pada periode yang dipilih.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="report-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="report-card__title mb-0">Shipment Outstanding Terbesar</h2>
            <span class="text-muted small">{{ count($topShipments) }} catatan</span>
        </div>
        @if(empty($topShipments))
            <p class="text-muted mb-0">Seluruh shipment telah diterima sepenuhnya.</p>
        @else
            <ul class="shipments-list">
                @foreach($topShipments as $item)
                    <li class="shipments-item">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold">{{ $item['shipment_number'] }}</div>
                                <div class="text-muted small">PO {{ $item['po_number'] }} — {{ $item['product_code'] }} ({{ $item['product_name'] }})</div>
                            </div>
                            <span class="shipments-badge">Outstanding {{ number_format($item['outstanding']) }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-3 small text-muted">
                            <span>Planned: {{ number_format($item['quantity_planned']) }}</span>
                            <span>Received: {{ number_format($item['quantity_received']) }}</span>
                            <span>Status: {{ ucfirst(str_replace('_',' ', $item['status'])) }}</span>
                            <span>Ship: {{ $item['ship_date'] ?? '-' }}</span>
                            <span>ETA: {{ $item['eta_date'] ?? '-' }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const chartData = @json($charts);

    function whenReady(cb) {
        if (document.readyState !== 'loading') {
            cb();
        } else {
            document.addEventListener('DOMContentLoaded', cb, { once: true });
        }
    }

    whenReady(function(){
        function renderCharts(){
            if (typeof ApexCharts === 'undefined') {
                setTimeout(renderCharts, 200);
                return;
            }

            const quotaBar = chartData.quota_bar || { categories: [], series: [] };
            const barEl = document.getElementById('report-quota-bar');
            if (barEl && quotaBar.series && quotaBar.series.length) {
                new ApexCharts(barEl, {
                    chart: { type: 'bar', height: 320, toolbar: { show:false } },
                    series: quotaBar.series,
                    xaxis: { categories: quotaBar.categories || [], labels: { rotate:-15 } },
                    plotOptions: { bar: { columnWidth: '45%', borderRadius: 6 } },
                    dataLabels: { enabled: true },
                    legend: { position: 'top' },
                    colors: ['#2563eb', '#f97316']
                }).render();
            }

            const monthly = chartData.monthly_line || { categories: [], series: [] };
            const lineEl = document.getElementById('report-monthly-line');
            if (lineEl && monthly.series && monthly.series.length) {
                new ApexCharts(lineEl, {
                    chart: { type: 'area', height: 320, toolbar: { show:false } },
                    series: [{ name: 'Actual Received', data: monthly.series }],
                    xaxis: { categories: monthly.categories || [] },
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 3 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 95, 100] } },
                    colors: ['#0ea5e9']
                }).render();
            }

            const shipmentDonut = chartData.shipment_donut || { labels: [], series: [] };
            const donutEl = document.getElementById('report-shipment-donut');
            if (donutEl && shipmentDonut.series && shipmentDonut.series.length) {
                new ApexCharts(donutEl, {
                    chart: { type: 'donut', height: 320 },
                    series: shipmentDonut.series,
                    labels: shipmentDonut.labels || [],
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: true, formatter: val => Math.round(val) + '%' },
                    colors: ['#22c55e', '#f97316']
                }).render();
            }
        }

        if (!window.__apexInjected) {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
            script.async = true;
            script.onload = renderCharts;
            document.head.appendChild(script);
            window.__apexInjected = true;
        } else {
            renderCharts();
        }
    });
})();
</script>
@endpush
