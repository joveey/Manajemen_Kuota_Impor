{{-- resources/views/admin/reports/final.blade.php --}}
@extends('layouts.admin')

@section('title', 'Laporan Gabungan')

@push('styles')
<style>
    .report-page { display:flex; flex-direction:column; gap:24px; }
    .report-header { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:18px; }
    .report-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .report-subtitle { color:#64748b; max-width:600px; }
    .filter-card { border-radius:18px; border:1px solid #dbe3f3; background:#ffffff; box-shadow:0 25px 55px -48px rgba(15,23,42,.35); }
    .filter-body { padding:18px 22px; }

    .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:16px; }
    .summary-card {
        border-radius:18px;
        border:1px solid #e2e8f0;
        background:linear-gradient(135deg,#ffffff 0%,#f8fafc 100%);
        padding:18px 20px;
        display:flex;
        flex-direction:column;
        gap:6px;
        box-shadow:0 26px 52px -48px rgba(15,23,42,.38);
    }
    .summary-card__label { font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; }
    .summary-card__value { font-size:24px; font-weight:700; color:#0f172a; }
    .summary-card__meta { font-size:12px; color:#64748b; }

    .highlight-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
    .highlight-card {
        border-radius:18px;
        border:1px solid rgba(37,99,235,.18);
        background:linear-gradient(135deg,rgba(37,99,235,.08) 0%, rgba(255,255,255,0.92) 100%);
        padding:16px 18px;
        box-shadow:0 28px 52px -46px rgba(37,99,235,.35);
        display:flex;
        flex-direction:column;
        gap:6px;
    }
    .highlight-card__title { font-weight:700; color:#1d4ed8; margin-bottom:4px; }
    .highlight-card__meta { font-size:12px; color:#475569; }
    .highlight-card__value { font-size:20px; font-weight:700; color:#111827; }
    .highlight-card__badge {
        display:inline-flex;
        align-items:center;
        gap:6px;
        border-radius:999px;
        background:rgba(34,197,94,.14);
        color:#15803d;
        padding:4px 10px;
        font-size:12px;
        font-weight:600;
    }

    .chart-card {
        border-radius:18px;
        border:1px solid #e1e8f8;
        background:#ffffff;
        padding:20px;
        height:100%;
        box-shadow:0 28px 60px -52px rgba(15,23,42,.42);
    }
    .chart-card__title {
        font-size:16px;
        font-weight:600;
        margin-bottom:12px;
        color:#0f172a;
    }
    .chart-container { min-height:260px; }

    .report-table-shell {
        border:1px solid #dde5f5;
        border-radius:20px;
        overflow:hidden;
        background:#ffffff;
        box-shadow:0 34px 68px -56px rgba(15,23,42,.45);
    }
    .report-table th {
        background:#f8fbff;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:0.08em;
        color:#64748b;
    }
    .report-table td { font-size:13px; color:#1f2937; }

    .top-shipments {
        border-radius:20px;
        border:1px solid #dbe3f3;
        background:#ffffff;
        padding:18px 22px;
        box-shadow:0 30px 60px -50px rgba(15,23,42,.35);
    }
    .top-shipments__header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
    .top-shipments__list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px; }
    .top-shipments__item { display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; border:1px solid rgba(148,163,184,.18); border-radius:14px; padding:12px 14px; background:linear-gradient(135deg,rgba(248,250,252,.65) 0%, #ffffff 100%); }
    .top-shipments__meta { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:#4b5563; }
    .badge-outstanding { background:rgba(248,113,113,.14); color:#b91c1c; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:600; }

    .status-card {
        border-radius:18px;
        border:1px solid #e2e8f0;
        background:#ffffff;
        padding:18px 22px;
        height:100%;
        box-shadow:0 28px 60px -50px rgba(15,23,42,.32);
    }
    .status-card__title { font-weight:600; color:#0f172a; margin-bottom:12px; }
    .status-card__list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; }
    .status-card__item { display:flex; justify-content:space-between; align-items:center; font-size:13px; color:#334155; }
    .status-card__pill { background:rgba(148,163,184,.16); color:#475569; border-radius:999px; padding:4px 10px; font-weight:600; }
</style>
@endpush

@section('content')
@php
    $filters = $filters ?? [];
    $summary = $summary ?? [];
    $rows = $rows ?? [];
    $topShipments = $topShipments ?? [];
    $highlights = $highlights ?? [];
    $poStatus = $po_status ?? [];
    $shipmentStatus = $shipment_status ?? [];
    $charts = $charts ?? [
        'quota_bar' => ['categories' => [], 'series' => []],
        'monthly_line' => ['categories' => [], 'series' => []],
        'po_status' => ['labels' => [], 'series' => []],
        'shipment_donut' => ['labels' => [], 'series' => []],
    ];
    $utilizationPct = ($summary['total_allocation'] ?? 0) > 0
        ? round((($summary['total_actual_consumed'] ?? 0) / max(1, $summary['total_allocation'])) * 100, 1)
        : 0;
@endphp
<div class="report-page">
    <div class="report-header">
        <div>
            <h1 class="report-title">Laporan Gabungan</h1>
            <p class="report-subtitle">Ringkasan menyeluruh yang menggabungkan data kuota, purchase order, dan pengiriman dalam satu tampilan.</p>
        </div>
        <div>
            <a href="{{ route('admin.reports.final.export.csv', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="filter-card">
        <div class="filter-body">
            <form method="GET" action="{{ route('admin.reports.final') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="start_date" class="form-label small text-uppercase text-muted">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4">
                    <label for="end_date" class="form-label small text-uppercase text-muted">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span class="summary-card__label">Total Kuota</span>
            <span class="summary-card__value">{{ number_format($summary['total_allocation'] ?? 0) }}</span>
            <span class="summary-card__meta">Total alokasi kuota aktif periode ini.</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Pemakaian Actual</span>
            <span class="summary-card__value">{{ number_format($summary['total_actual_consumed'] ?? 0) }}</span>
            <span class="summary-card__meta">Jumlah penerimaan barang (Good Receipt) selama periode.</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Outstanding PO</span>
            <span class="summary-card__value">{{ number_format($summary['po_outstanding'] ?? 0) }}</span>
            <span class="summary-card__meta">Qty PO belum terpenuhi.</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Outstanding Shipment</span>
            <span class="summary-card__value">{{ number_format($summary['shipment_outstanding'] ?? 0) }}</span>
            <span class="summary-card__meta">Selisih qty kirim vs penerimaan.</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Utilisasi Kuota</span>
            <span class="summary-card__value">{{ $utilizationPct }}%</span>
            <span class="summary-card__meta">Persentase konsumsi aktual terhadap total kuota.</span>
        </div>
    </div>

    @if(!empty($highlights))
        <div class="highlight-grid">
            @foreach($highlights as $item)
                <div class="highlight-card">
                    <div class="highlight-card__title">{{ $item['quota_number'] }}</div>
                    <div class="highlight-card__meta">{{ $item['quota_name'] }} — {{ $item['range_pk'] }}</div>
                    <div class="highlight-card__value">{{ number_format($item['shipment_received']) }} unit diterima</div>
                    <div class="highlight-card__meta">Outstanding {{ number_format($item['shipment_outstanding']) }} unit</div>
                    <span class="highlight-card__badge">
                        <i class="fas fa-chart-line"></i> {{ $item['actual_pct'] }}% Realisasi
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xxl-4">
            <div class="chart-card">
                <div class="chart-card__title">Shipment Received vs Outstanding per Kuota</div>
                <div id="final-report-bar" class="chart-container"></div>
            </div>
        </div>
        <div class="col-12 col-xxl-4">
            <div class="chart-card">
                <div class="chart-card__title">Trend Pemakaian Actual (Bulanan)</div>
                <div id="final-report-line" class="chart-container"></div>
            </div>
        </div>
        <div class="col-12 col-xxl-4">
            <div class="chart-card">
                <div class="chart-card__title">Distribusi Shipment (Received vs Outstanding)</div>
                <div id="final-report-donut" class="chart-container"></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="status-card">
                <h4 class="status-card__title">Status Purchase Order</h4>
                @if(empty($poStatus))
                    <div class="text-muted small">Tidak ada data PO pada rentang tanggal ini.</div>
                @else
                    <ul class="status-card__list">
                        @foreach($poStatus as $status => $total)
                            <li class="status-card__item">
                                <span>{{ ucfirst(str_replace('_',' ', $status)) }}</span>
                                <span class="status-card__pill">{{ number_format($total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="status-card">
                <h4 class="status-card__title">Status Pengiriman</h4>
                @if(empty($shipmentStatus))
                    <div class="text-muted small">Tidak ada data pengiriman pada rentang tanggal ini.</div>
                @else
                    <ul class="status-card__list">
                        @foreach($shipmentStatus as $status => $total)
                            <li class="status-card__item">
                                <span>{{ ucfirst(str_replace('_',' ', $status)) }}</span>
                                <span class="status-card__pill">{{ number_format($total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="report-table-shell">
        <div class="table-responsive">
            <table class="table report-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Quota</th>
                    <th>Range PK</th>
                    <th class="text-end">Alokasi</th>
                    <th class="text-end">Forecast</th>
                    <th class="text-end">Actual Remaining</th>
                    <th class="text-end">PO (Qty)</th>
                    <th class="text-end">PO (Received)</th>
                    <th class="text-end">PO Outstanding</th>
                    <th class="text-end">Shipment Planned</th>
                    <th class="text-end">Shipment Received</th>
                    <th class="text-end">Shipment Outstanding</th>
                    <th>Latest Receipt</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $row['quota_number'] }}</div>
                            <div class="text-muted small">{{ $row['quota_name'] }}</div>
                        </td>
                        <td>{{ $row['range_pk'] }}</td>
                        <td class="text-end">{{ number_format($row['total_allocation']) }}</td>
                        <td class="text-end">{{ number_format($row['forecast_remaining']) }}</td>
                        <td class="text-end">{{ number_format($row['actual_remaining']) }}</td>
                        <td class="text-end">{{ number_format($row['po_quantity']) }}<div class="text-muted small">{{ $row['po_count'] }} PO</div></td>
                        <td class="text-end">{{ number_format($row['po_received']) }}</td>
                        <td class="text-end text-danger">{{ number_format($row['po_outstanding']) }}</td>
                        <td class="text-end">{{ number_format($row['shipment_planned']) }}<div class="text-muted small">{{ $row['shipment_count'] }} pengiriman</div></td>
                        <td class="text-end">{{ number_format($row['shipment_received']) }}</td>
                        <td class="text-end text-danger">{{ number_format($row['shipment_outstanding']) }}</td>
                        <td>{{ $row['last_receipt_date'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">Tidak ada data pada rentang tanggal tersebut.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="top-shipments">
        <div class="top-shipments__header">
            <h4 class="mb-0">Pengiriman Dengan Outstanding Tertinggi</h4>
            <span class="text-muted small">{{ count($topShipments) }} data</span>
        </div>
        @if(empty($topShipments))
            <div class="text-muted">Seluruh pengiriman sudah terpenuhi. Tidak ada outstanding.</div>
        @else
            <ul class="top-shipments__list">
                @foreach($topShipments as $item)
                    <li class="top-shipments__item">
                        <div>
                            <div class="fw-semibold">{{ $item['shipment_number'] }}</div>
                            <div class="text-muted small">PO {{ $item['po_number'] }} — {{ $item['product_code'] }} ({{ $item['product_name'] }})</div>
                        </div>
                        <div class="top-shipments__meta">
                            <span>Planned: {{ number_format($item['quantity_planned']) }}</span>
                            <span>Received: {{ number_format($item['quantity_received']) }}</span>
                            <span>Status: {{ ucfirst(str_replace('_',' ', $item['status'])) }}</span>
                            <span>Ship: {{ $item['ship_date'] ?? '-' }}</span>
                            <span>ETA: {{ $item['eta_date'] ?? '-' }}</span>
                        </div>
                        <div class="badge-outstanding">Outstanding {{ number_format($item['outstanding']) }}</div>
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
    var chartData = @json($charts);

    function whenReady(fn){ document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }

    whenReady(function(){
        function renderCharts(){
            if (typeof ApexCharts === 'undefined') {
                setTimeout(renderCharts, 200);
                return;
            }

            var quotaBar = chartData.quota_bar || {categories: [], series: []};
            var barEl = document.getElementById('final-report-bar');
            if (barEl && quotaBar.series && quotaBar.series.length){
                try {
                    new ApexCharts(barEl, {
                        chart: { type: 'bar', height: 300, toolbar: { show:false } },
                        series: quotaBar.series,
                        xaxis: { categories: quotaBar.categories || [], labels: { rotate: -15, trim: true } },
                        plotOptions: { bar: { columnWidth: '45%', borderRadius: 6 } },
                        dataLabels: { enabled: true },
                        legend: { position:'top' },
                        colors: ['#2563eb', '#22c55e']
                    }).render();
                } catch (error) { console.warn('Failed to render quota bar chart', error); }
            }

            var monthly = chartData.monthly_line || {categories: [], series: []};
            var lineEl = document.getElementById('final-report-line');
            if (lineEl && monthly.series && monthly.series.length){
                try {
                    new ApexCharts(lineEl, {
                        chart: { type: 'area', height: 300, toolbar: { show:false } },
                        series: monthly.series,
                        xaxis: { categories: monthly.categories || [] },
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 3 },
                        colors: ['#f97316'],
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] } }
                    }).render();
                } catch (error) { console.warn('Failed to render monthly line chart', error); }
            }

            var shipmentDonut = chartData.shipment_donut || {labels: [], series: []};
            var donutEl = document.getElementById('final-report-donut');
            if (donutEl && shipmentDonut.series && shipmentDonut.series.length){
                try {
                    new ApexCharts(donutEl, {
                        chart: { type: 'donut', height: 300 },
                        series: shipmentDonut.series,
                        labels: shipmentDonut.labels || [],
                        legend: { position: 'bottom' },
                        dataLabels: { enabled: true, formatter: function(val){ return Math.round(val) + '%'; } },
                        colors: ['#22c55e', '#f97316']
                    }).render();
                } catch (error) { console.warn('Failed to render shipment donut chart', error); }
            }
        }

        if (!window.__apexInjected) {
            var script = document.createElement('script');
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
