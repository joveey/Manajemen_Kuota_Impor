@extends('layouts.admin')

@section('title','Analytics')

@section('page-title','Analytics')

@section('content')
@php
    $mode = $mode ?? 'actual';
    $isForecast = $mode === 'forecast';
    $modeLabel = $isForecast ? 'Forecast' : 'Actual';
    $badgeClass = $isForecast ? 'text-bg-warning' : 'text-bg-primary';
    $primaryLabel = $isForecast ? 'Forecast (Purchase Orders)' : 'Actual (Good Receipt)';
    $secondaryLabel = $isForecast ? 'Sisa Forecast' : 'Sisa Kuota';
    $percentageLabel = $isForecast ? 'Penggunaan Forecast %' : 'Pemakaian Actual %';
    $query = request()->query();
    $forecastUrl = route('analytics.index', array_merge($query, ['mode' => 'forecast']));
    $actualUrl = route('analytics.index', array_merge($query, ['mode' => 'actual']));
@endphp
<div class="app-analytics">
    <div class="card glass-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('analytics.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <div class="col-12 col-sm-4 col-lg-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $start_date }}" class="form-control">
                </div>
                <div class="col-12 col-sm-4 col-lg-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="{{ $end_date }}" class="form-control">
                </div>
                <div class="col-12 col-sm-4 col-lg-3">
                    <button type="submit" class="btn btn-primary w-100">Terapkan</button>
                </div>
            </form>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <div class="btn-group" role="group" aria-label="Pilih Mode Data">
                    <a href="{{ $forecastUrl }}" class="btn btn-outline-warning {{ $isForecast ? 'active' : '' }}">Forecast</a>
                    <a href="{{ $actualUrl }}" class="btn btn-outline-primary {{ $isForecast ? '' : 'active' }}">Actual</a>
                </div>
                <span class="badge rounded-pill {{ $badgeClass }}">{{ $modeLabel }} data</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card glass-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Perbandingan Kuota vs {{ $modeLabel }}</h5>
                        <span class="badge rounded-pill {{ $badgeClass }}">{{ $modeLabel }} Based</span>
                    </div>
                    <div id="analyticsBar" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card glass-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Proporsi Pemakaian {{ $modeLabel }}</h5>
                        <span class="badge rounded-pill text-bg-secondary">Donut</span>
                    </div>
                    <div id="analyticsDonut" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card glass-card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Detail {{ $modeLabel }} per Kuota</h5>
            <div class="btn-group">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('analytics.export.csv', request()->query()) }}">CSV</a>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('analytics.export.xlsx', request()->query()) }}">XLSX</a>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('analytics.export.pdf', request()->query()) }}">PDF</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
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
                        <tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <style>
        /* Prevent layout shift: fixed chart height */
        .chart-container { min-height: 20rem; height: 20rem; }
        @media (min-width: 768px) { .chart-container { height: 28rem; } }
    </style>
    <script src="{{ asset('js/analytics-charts.js') }}?v={{ time() }}"></script>
    <script>
        window.analyticsConfig = {
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
        (function(){
            function call(){ if (window.initAnalyticsCharts) window.initAnalyticsCharts(window.analyticsConfig); }
            if (!window.__apexInjected) {
                var s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/apexcharts'; s.async=true; s.onload=call; document.head.appendChild(s); window.__apexInjected=true;
            } else { call(); }
        })();
    </script>
@endpush
@endsection
