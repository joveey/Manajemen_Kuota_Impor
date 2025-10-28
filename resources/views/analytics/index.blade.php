{{-- resources/views/analytics/index.blade.php --}}
@extends('layouts.admin')

@section('title','Analytics')
@section('page-title','Analytics')

@php
    $mode = $mode ?? 'actual';
    $isForecast = $mode === 'forecast';
    $modeLabel = $isForecast ? 'Forecast' : 'Actual';
    $primaryLabel = $isForecast ? 'Forecast (Consumption)' : 'Actual (Good Receipt)';
    $secondaryLabel = $isForecast ? 'Sisa Forecast' : 'Sisa Kuota';
    $percentageLabel = $isForecast ? 'Penggunaan Forecast %' : 'Pemakaian Actual %';
    $query = request()->query();
    $forecastUrl = route('analytics.index', array_merge($query, ['mode' => 'forecast']));
    $actualUrl = route('analytics.index', array_merge($query, ['mode' => 'actual']));
@endphp

@section('content')
<div class="page-shell analytics-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Analytics</h1>
            <p class="page-header__subtitle">
                Lihat perbandingan kuota terhadap realisasi pengiriman dan status forecast untuk tiap periode.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('analytics.export.csv', request()->query()) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-csv me-2"></i>CSV
            </a>
            <a href="{{ route('analytics.export.xlsx', request()->query()) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-excel me-2"></i>XLSX
            </a>
            <a href="{{ route('analytics.export.pdf', request()->query()) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </a>
        </div>
    </div>

    <section class="analytics-card analytics-card--filters">
        @php
            $currentYear = (int) now()->year;
            $selectedYear = (int) ($year ?? $currentYear);
            $years = range($currentYear - 5, $currentYear + 5);
        @endphp
        <form method="GET" action="{{ route('analytics.index') }}" class="analytics-filters">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <div class="analytics-filters__group">
                <label for="year" class="analytics-filters__label">Tahun</label>
                <select id="year" name="year" class="analytics-filters__input">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y === $selectedYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="analytics-filters__submit">
                <i class="fas fa-filter me-2"></i>Terapkan
            </button>
        </form>
        <div class="analytics-mode">
            <a href="{{ $forecastUrl }}" class="analytics-mode__chip {{ $isForecast ? 'analytics-mode__chip--active' : '' }}">Forecast</a>
            <a href="{{ $actualUrl }}" class="analytics-mode__chip {{ $isForecast ? '' : 'analytics-mode__chip--active' }}">Actual</a>
        </div>
    </section>

    <section class="analytics-card">
        <header class="analytics-card__header">
            <h2 class="analytics-card__title">KPI Kuota</h2>
            <span class="analytics-card__badge analytics-card__badge--muted">Summary</span>
        </header>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-3"><div class="kpi-tile"><div class="kpi-label">Allocation</div><div class="kpi-value" id="kpiAllocation">-</div></div></div>
            <div class="col-12 col-md-6 col-lg-3"><div class="kpi-tile"><div class="kpi-label">Forecast Consumed</div><div class="kpi-value" id="kpiForecast">-</div></div></div>
            <div class="col-12 col-md-6 col-lg-3"><div class="kpi-tile"><div class="kpi-label">Actual Consumed</div><div class="kpi-value" id="kpiActual">-</div></div></div>
            <div class="col-12 col-md-6 col-lg-3"><div class="kpi-tile"><div class="kpi-label">In-Transit</div><div class="kpi-value" id="kpiInTransit">-</div></div></div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12 col-md-6 col-lg-3"><div class="kpi-sub">Forecast Remaining: <span id="kpiForecastRem">-</span></div></div>
            <div class="col-12 col-md-6 col-lg-3"><div class="kpi-sub">Actual Remaining: <span id="kpiActualRem">-</span></div></div>
        </div>
    </section>

    <section class="analytics-grid">
        <article class="analytics-card">
            <header class="analytics-card__header">
                <h2 class="analytics-card__title">Perbandingan Kuota vs {{ $modeLabel }}</h2>
                <span class="analytics-card__badge">{{ $modeLabel }} Based</span>
            </header>
            <div id="analyticsBar" class="analytics-card__chart"></div>
        </article>

        <article class="analytics-card">
            <header class="analytics-card__header">
                <h2 class="analytics-card__title">Proporsi Pemakaian {{ $modeLabel }}</h2>
                <span class="analytics-card__badge analytics-card__badge--muted">Donut</span>
            </header>
            <div id="analyticsDonut" class="analytics-card__chart"></div>
        </article>
    </section>

    <section class="analytics-card">
        <header class="analytics-card__header">
            <div>
                <h2 class="analytics-card__title">Detail {{ $modeLabel }} per Kuota</h2>
                <p class="analytics-card__subtitle">Angka-angka berikut memudahkan tim operasional memantau sisa kuota dan realisasi shipment.</p>
            </div>
            <span class="analytics-card__badge analytics-card__badge--muted">{{ $modeLabel }} data</span>
        </header>
        <div class="table-responsive">
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Nomor Kuota</th>
                        <th>Range PK</th>
                        <th class="text-end">Kuota Awal</th>
                        <th class="text-end">{{ $primaryLabel }}</th>
                        <th class="text-end">{{ $secondaryLabel }}</th>
                        <th class="text-end">{{ $percentageLabel }}</th>
                    </tr>
                </thead>
                <tbody id="analyticsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
    .analytics-shell { display:flex; flex-direction:column; gap:24px; }
    .analytics-card {
        border:1px solid #dfe4f3;
        border-radius:16px;
        background:#ffffff;
        box-shadow:0 20px 45px -36px rgba(15,23,42,.35);
        padding:22px 24px;
        display:flex;
        flex-direction:column;
        gap:18px;
    }
    .analytics-card--filters { gap:16px; }
    .analytics-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .analytics-card__title { font-size:18px; font-weight:600; color:#0f172a; margin:0; }
    .analytics-card__subtitle { font-size:13px; color:#64748b; margin:6px 0 0; max-width:520px; }
    .analytics-card__badge { border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600; color:#2563eb; background:rgba(37,99,235,0.12); }
    .analytics-card__badge--muted { color:#475569; background:rgba(148,163,184,0.16); }
    .analytics-card__chart { min-height:320px; }

    .analytics-filters { display:flex; flex-wrap:wrap; gap:16px; }
    .analytics-filters__group { display:flex; flex-direction:column; gap:6px; flex:1 1 200px; }
    .analytics-filters__label { font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; }
    .analytics-filters__input {
        border-radius:12px;
        border:1px solid #cbd5f5;
        padding:10px 14px;
        font-size:13px;
        transition:border-color .2s ease, box-shadow .2s ease;
    }
    .analytics-filters__input:focus {
        border-color:#2563eb;
        box-shadow:0 0 0 3px rgba(37,99,235,0.15);
        outline:none;
    }
    .analytics-filters__submit {
        background:#2563eb;
        color:#fff;
        border:none;
        border-radius:12px;
        padding:10px 18px;
        font-size:13px;
        font-weight:600;
        display:inline-flex;
        align-items:center;
        gap:8px;
    }
    .analytics-mode { display:flex; flex-wrap:wrap; gap:12px; }
    .analytics-mode__chip {
        border:1px solid #cbd5f5;
        border-radius:999px;
        padding:8px 16px;
        font-size:13px;
        font-weight:600;
        color:#1f2937;
        text-decoration:none;
        transition:all .2s ease;
    }
    .analytics-mode__chip:hover { border-color:#2563eb; color:#2563eb; }
    .analytics-mode__chip--active { background:#2563eb; color:#fff; border-color:#2563eb; }

    .analytics-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; }

    .analytics-table { width:100%; border-collapse:separate; border-spacing:0; }
    .analytics-table thead th {
        background:#f8fbff;
        padding:12px 14px;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:0.08em;
        color:#64748b;
    }
    .analytics-table tbody td {
        padding:14px;
        font-size:13px;
        color:#1f2937;
        border-top:1px solid #e5eaf5;
    }
    .analytics-table tbody tr:hover { background:rgba(37,99,235,0.04); }

    .kpi-tile { background:#f8fbff; border:1px solid #dfe4f3; border-radius:14px; padding:14px 16px; height:100%; }
    .kpi-label { font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.08em; }
    .kpi-value { font-size:22px; font-weight:700; color:#0f172a; }
    .kpi-sub { font-size:13px; color:#475569; }

    @media (max-width: 640px) {
        .analytics-card,
        .analytics-card--filters { padding:18px; }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/analytics-charts.js') }}?v={{ time() }}"></script>
<script>
(function(){
    const config = {
        dataUrl: @json(route('analytics.data', request()->query())),
        tableBodyId: 'analyticsTableBody',
        barElId: 'analyticsBar',
        donutElId: 'analyticsDonut',
        mode: @json($mode),
        labels: {
            primary: @json($primaryLabel),
            secondary: @json($secondaryLabel),
            percentage: @json($percentageLabel)
        }
    };

    function initCharts() {
        if (typeof window.initAnalyticsCharts === 'function') {
            window.initAnalyticsCharts(config);
        }
    }

    if (!window.__apexInjected) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
        script.async = true;
        script.onload = initCharts;
        document.head.appendChild(script);
        window.__apexInjected = true;
    } else {
        initCharts();
    }
})();
</script>
@endpush
