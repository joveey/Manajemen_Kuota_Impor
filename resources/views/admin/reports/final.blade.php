{{-- resources/views/admin/reports/final.blade.php --}}
@extends('layouts.admin')

@section('title', 'Combined Report')
@section('page-title', 'Combined Report')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Combined Report</li>
@endsection

@php
    $formatInt = function ($value) {
        return number_format((float) ($value ?? 0), 0);
    };
    $formatQty = function ($value) {
        return fmt_qty($value);
    };
    $filters = $filters ?? ['start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->toDateString()];
    $poStatusLabels = $charts['po_status']['labels'] ?? [];
    $poStatusSeries = $charts['po_status']['series'] ?? [];
@endphp

@push('styles')
<style>
    .report-shell { display:flex; flex-direction:column; gap:24px; }
    .report-card {
        border:1px solid #e2e8f0;
        border-radius:18px;
        background:#ffffff;
        box-shadow:0 24px 46px -38px rgba(15,23,42,0.35);
        padding:22px 24px;
        display:flex;
        flex-direction:column;
        gap:18px;
    }
    .report-card--flat { gap:12px; }
    .report-card__title { font-size:18px; font-weight:600; color:#0f172a; margin:0; }
    .report-card__subtitle { font-size:13px; color:#64748b; margin:0; }
    .report-card__meta { font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; }

    .report-filters {
        display:flex;
        flex-wrap:wrap;
        gap:16px;
        align-items:flex-end;
    }
    .report-filters__group {
        flex:1 1 200px;
        display:flex;
        flex-direction:column;
        gap:6px;
    }
    .report-filters__label {
        font-size:12px;
        color:#94a3b8;
        text-transform:uppercase;
        letter-spacing:0.08em;
    }
    .report-filters__input {
        border-radius:12px;
        border:1px solid #cbd5f5;
        padding:10px 14px;
        font-size:13px;
    }
    .report-filters__actions {
        display:flex;
        gap:10px;
        flex-wrap:wrap;
    }

    .report-summary {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
        gap:16px;
    }
    .report-summary__item {
        border:1px solid #e5e9f7;
        border-radius:16px;
        background:linear-gradient(135deg,#ffffff 0%,#f8fbff 100%);
        padding:16px 18px;
        display:flex;
        flex-direction:column;
        gap:6px;
    }
    .report-summary__label {
        font-size:12px;
        color:#94a3b8;
        text-transform:uppercase;
        letter-spacing:0.08em;
    }
    .report-summary__value {
        font-size:24px;
        font-weight:700;
        color:#0f172a;
    }
    .report-summary__meta {
        font-size:12px;
        color:#64748b;
    }

    .report-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
        gap:18px;
    }
    .chart-container { min-height:300px; }

    .status-list {
        list-style:none;
        padding:0;
        margin:0;
        display:flex;
        flex-direction:column;
        gap:10px;
    }
    .status-list__item {
        display:flex;
        justify-content:space-between;
        align-items:center;
        border:1px solid #e2e8f0;
        border-radius:12px;
        padding:10px 14px;
        font-size:13px;
        color:#475569;
    }
    .status-list__badge {
        font-weight:600;
        color:#2563eb;
    }

    .report-table-wrapper {
        border:1px solid #e2e8f0;
        border-radius:18px;
        overflow:hidden;
    }
    .report-table {
        width:100%;
        border-collapse:separate;
        border-spacing:0;
    }
    .report-table thead th {
        background:#f8fbff;
        padding:12px 14px;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:0.08em;
        color:#64748b;
        border-bottom:1px solid #e2e8f0;
    }
    .report-table tbody td {
        padding:14px;
        font-size:13px;
        color:#1f2937;
        border-bottom:1px solid #f1f5f9;
    }
    .report-table tbody tr:last-child td { border-bottom:none; }
    .report-table tbody tr:hover { background:rgba(37,99,235,0.04); }

    .outstanding-list {
        list-style:none;
        padding:0;
        margin:0;
        display:flex;
        flex-direction:column;
        gap:12px;
    }
    .outstanding-item {
        border:1px solid rgba(148,163,184,0.16);
        border-radius:12px;
        padding:12px 14px;
        background:linear-gradient(135deg,#f8fbff 0%, #ffffff 100%);
        display:flex;
        flex-direction:column;
        gap:8px;
    }
    .outstanding-item__header {
        display:flex;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:8px;
        font-size:13px;
        color:#1f2937;
    }
    .outstanding-item__badge {
        background:rgba(248,113,113,0.15);
        color:#b91c1c;
        border-radius:999px;
        padding:4px 10px;
        font-weight:600;
        font-size:12px;
    }
    .outstanding-item__meta {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        font-size:12px;
        color:#64748b;
    }

    @media (max-width: 640px) {
        .report-card { padding:18px; }
    }
</style>
@endpush

@section('content')
<div class="report-shell">
    <div class="report-card report-card--flat">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="report-card__title mb-1">Combined Report</h1>
                <p class="report-card__subtitle mb-0">
                    Overview of POs and Good Receipt realization from the PO header/line pipeline and the latest GR.
                </p>
            </div>
            <div class="report-filters__actions">
                <a href="{{ route('admin.reports.final.export.csv', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-file-csv me-2"></i>Export CSV
                </a>
            </div>
        </div>
        @php
          $currentYear = (int) now()->year;
          $selectedYear = (int) ($filters['year'] ?? $currentYear);
          $years = range($currentYear - 5, $currentYear + 5);
        @endphp
        <form method="GET" action="{{ route('admin.reports.final') }}" class="report-filters">
            <div class="report-filters__group">
                <label for="year" class="report-filters__label">Year</label>
                <select id="year" name="year" class="report-filters__input">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y === $selectedYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="report-filters__actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply
                </button>
                <a href="{{ route('admin.reports.final') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="report-summary">
        <div class="report-summary__item">
            <span class="report-summary__label">Total PO</span>
            <span class="report-summary__value">{{ $formatInt($summary['po_total'] ?? 0) }}</span>
            <span class="report-summary__meta">Qty ordered {{ $formatQty($summary['po_ordered_total'] ?? 0) }}</span>
        </div>
        <div class="report-summary__item">
            <span class="report-summary__label">Outstanding PO</span>
            <span class="report-summary__value">{{ $formatQty($summary['po_outstanding_total'] ?? 0) }}</span>
            <span class="report-summary__meta">Not yet realized (GR)</span>
        </div>
        <div class="report-summary__item">
            <span class="report-summary__label">GR Quantity</span>
            <span class="report-summary__value">{{ $formatQty($summary['gr_total_qty'] ?? 0) }}</span>
            <span class="report-summary__meta">Within date range</span>
        </div>
        <div class="report-summary__item">
            <span class="report-summary__label">GR Documents</span>
            <span class="report-summary__value">{{ $formatInt($summary['gr_document_total'] ?? 0) }}</span>
            <span class="report-summary__meta">Unique (GR Unique/PO-Line-Date)</span>
        </div>
        <div class="report-summary__item">
            <span class="report-summary__label">Quota (Total vs Remaining)</span>
            <span class="report-summary__value">{{ $formatQty($summary['quota_total_allocation'] ?? 0) }}</span>
            <span class="report-summary__meta">Actual remaining {{ $formatQty($summary['quota_total_remaining'] ?? 0) }}</span>
        </div>
    </div>

    <div class="report-grid">
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="report-card__title">Quota: Total vs Remaining</h2>
                <span class="report-card__meta">Bar Chart</span>
            </div>
            <div id="report-quota-bar" class="chart-container"></div>
        </div>
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="report-card__title">GR Received vs Outstanding</h2>
                <span class="report-card__meta">Donut Chart</span>
            </div>
            <div id="report-receipt-donut" class="chart-container"></div>
        </div>
    </div>

    <div class="report-grid">
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="report-card__title">Monthly GR Trend</h2>
                <span class="report-card__meta">Area Chart</span>
            </div>
            <div id="report-gr-trend" class="chart-container"></div>
        </div>
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="report-card__title mb-0">Status PO (SAP)</h2>
                <span class="report-card__meta">Summary</span>
            </div>
            @if(empty($poStatusLabels))
                <p class="text-muted mb-0">No statuses recorded.</p>
            @else
                <ul class="status-list">
                    @foreach($poStatusLabels as $index => $label)
                        @php $value = $poStatusSeries[$index] ?? 0; @endphp
                        <li class="status-list__item">
                            <span>{{ $label }}</span>
                            <span class="status-list__badge">{{ $formatInt($value) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="report-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="report-card__title mb-0">Top Outstanding (PO Line)</h2>
            <span class="text-muted small">{{ count($outstanding ?? []) }} records</span>
        </div>
        @if(empty($outstanding))
            <p class="text-muted mb-0">All PO lines in this period have been fulfilled.</p>
        @else
            <ul class="outstanding-list">
                @foreach($outstanding as $item)
                    <li class="outstanding-item">
                        <div class="outstanding-item__header">
                            <div>
                                <strong>{{ $item['po_number'] }}</strong> · Line {{ $item['line_no'] }}
                                @if(!empty($item['model_code']))
                                    <span class="text-muted">({{ $item['model_code'] }})</span>
                                @endif
                            </div>
                            <span class="outstanding-item__badge">Outstanding {{ $formatQty($item['outstanding']) }}</span>
                        </div>
                        <div class="outstanding-item__meta">
                            <span>Ordered: {{ $formatQty($item['qty_ordered']) }}</span>
                            <span>Received: {{ $formatQty($item['qty_received']) }}</span>
                            <span>Status: {{ $item['sap_order_status'] ?? 'Unknown' }}</span>
                            <span>ETA: {{ $item['eta_date'] ?? '-' }}</span>
                            <span>Last GR: {{ $item['last_receipt_date'] ?? '-' }}</span>
                        </div>
                        @if(!empty($item['item_desc']))
                            <div class="text-muted" style="font-size:12px;">{{ $item['item_desc'] }}</div>
                        @endif
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

    function renderCharts(){
        if (typeof ApexCharts === 'undefined') {
            setTimeout(renderCharts, 200);
            return;
        }

        const quotaBar = chartData.quota_bar || { categories: [], series: [] };
        const quotaEl = document.getElementById('report-quota-bar');
        if (quotaEl && Array.isArray(quotaBar.series) && quotaBar.series.length) {
            new ApexCharts(quotaEl, {
                chart: { type:'bar', height:320, toolbar:{ show:false } },
                series: quotaBar.series,
                xaxis: { categories: quotaBar.categories || [], labels: { rotate:-15 } },
                plotOptions: { bar:{ columnWidth:'45%', borderRadius:6 } },
                dataLabels: { enabled:false },
                legend: { position:'top' },
                colors: ['#2563eb','#10b981']
            }).render();
        }

        const trend = chartData.gr_trend || { categories: [], series: [] };
        const trendEl = document.getElementById('report-gr-trend');
        if (trendEl && Array.isArray(trend.series) && trend.series.length) {
            new ApexCharts(trendEl, {
                chart: { type:'area', height:320, toolbar:{ show:false } },
                series: trend.series,
                xaxis: { categories: trend.categories || [] },
                dataLabels: { enabled:false },
                stroke: { curve:'smooth', width:3 },
                fill: { type:'gradient', gradient:{ shadeIntensity:1, opacityFrom:0.45, opacityTo:0.05, stops:[0,90,100] } },
                colors: ['#0ea5e9']
            }).render();
        }

        const donut = chartData.receipt_donut || { labels: [], series: [] };
        const donutEl = document.getElementById('report-receipt-donut');
        if (donutEl && Array.isArray(donut.series) && donut.series.length) {
            new ApexCharts(donutEl, {
                chart: { type:'donut', height:320 },
                series: donut.series,
                labels: donut.labels || [],
                legend: { position:'bottom' },
                dataLabels: { enabled:true, formatter:(val) => Math.round(val) + '%' },
                colors: ['#22c55e','#f97316']
            }).render();
        }
    }

    function initCharts() {
        renderCharts();
    }

    whenReady(function(){
        if (typeof ApexCharts === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
            script.async = true;
            script.onload = initCharts;
            document.head.appendChild(script);
        } else {
            initCharts();
        }
    });
})();
</script>
@endpush

