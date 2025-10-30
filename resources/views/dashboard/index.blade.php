@extends('layouts.app')

@section('header')
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">Dashboard</h2>
        <div class="flex items-center gap-3"></div>
    </div>
@endsection

@section('content')
@php
    // Fallback dummy data for demo if variables are missing
    $kpis = $kpis ?? [
        ['title' => 'Total Quotas', 'value' => 4, 'delta' => '+0%', 'delta_type' => null, 'helper' => 'Available: 4 | Limited: 0 | Depleted: 0', 'icon' => 'package'],
        ['title' => 'Forecast Remaining', 'value' => number_format(199000), 'delta' => '+3.2%', 'delta_type' => 'up', 'helper' => 'Actual Remaining: '.number_format(227600), 'icon' => 'percent'],
        ['title' => 'POs This Month', 'value' => 5, 'delta' => '+1', 'delta_type' => 'up', 'helper' => 'Need Shipment: 5 | In Transit: 4', 'icon' => 'package'],
        ['title' => 'Shipments', 'value' => 5, 'delta' => '-1', 'delta_type' => 'down', 'helper' => 'Delivered: 0 | Pending Receipt: 5', 'icon' => 'truck'],
    ];

    $quotaBatches = $quotaBatches ?? [
        ['gov_ref' => 'Batch-01', 'range' => 'PK-01', 'forecast' => 12000, 'actual' => 9000, 'consumed' => 8000, 'remaining_status' => 'Available'],
        ['gov_ref' => 'Batch-02', 'range' => 'PK-02', 'forecast' => 8000, 'actual' => 7000, 'consumed' => 6500, 'remaining_status' => 'Limited'],
        ['gov_ref' => 'Batch-03', 'range' => 'PK-03', 'forecast' => 6000, 'actual' => 5000, 'consumed' => 5800, 'remaining_status' => 'Depleted'],
    ];

    $recentPOs = $recentPOs ?? [
        ['po_number' => 'PO-2025-001', 'date' => '2025-10-01', 'model_short' => 'Model A', 'status' => 'Created', 'qty' => 2000],
        ['po_number' => 'PO-2025-002', 'date' => '2025-10-02', 'model_short' => 'Model B', 'status' => 'In Transit', 'qty' => 1500],
        ['po_number' => 'PO-2025-003', 'date' => '2025-10-05', 'model_short' => 'Model C', 'status' => 'Completed', 'qty' => 800],
    ];

    $recentShipments = $recentShipments ?? [
        ['ship_number' => 'SHP-0001', 'eta' => '2025-10-14', 'status' => 'In Transit', 'qty' => 1200],
        ['ship_number' => 'SHP-0002', 'eta' => '2025-10-18', 'status' => 'Received', 'qty' => 700],
        ['ship_number' => 'SHP-0003', 'eta' => '2025-10-22', 'status' => 'In Transit', 'qty' => 600],
    ];

    $quotaHistory = $quotaHistory ?? [
        ['title' => 'Forecast decrease', 'subtitle' => 'Batch-03 • -200', 'date' => '10 Okt 2025', 'badge' => 'Forecast', 'variant' => 'info'],
        ['title' => 'Actual increase', 'subtitle' => 'Batch-02 • +300', 'date' => '11 Okt 2025', 'badge' => 'Actual', 'variant' => 'success'],
        ['title' => 'Actual decrease', 'subtitle' => 'Batch-01 • -100', 'date' => '12 Okt 2025', 'badge' => 'Actual', 'variant' => 'danger'],
    ];

    $usersByRole = $usersByRole ?? [
        'Super Administrator' => 1,
        'Administrator' => 1,
        'Viewer' => 2,
    ];

    $recentUsers = $recentUsers ?? [
        ['name' => 'S Adi', 'email' => 'adi@example.com', 'role' => 'Super Administrator', 'status' => 'Active', 'last_login' => '2 hours ago'],
        ['name' => 'Budi', 'email' => 'budi@example.com', 'role' => 'Administrator', 'status' => 'Active', 'last_login' => '1 day ago'],
        ['name' => 'Dewi', 'email' => 'dewi@example.com', 'role' => 'Viewer', 'status' => 'Inactive', 'last_login' => '—'],
        ['name' => 'Ani', 'email' => 'ani@example.com', 'role' => 'Viewer', 'status' => 'Active', 'last_login' => '3 days ago'],
        ['name' => 'Joko', 'email' => 'joko@example.com', 'role' => 'Viewer', 'status' => 'Active', 'last_login' => '5 days ago'],
    ];

    // Chart data
    $donutSeries = [array_sum(array_column($quotaBatches, 'consumed')), max(0, array_sum(array_column($quotaBatches, 'actual')) - array_sum(array_column($quotaBatches, 'consumed')) )];
    $donutLabels = ['Consumed', 'Remaining'];

    $barCategories = array_column($quotaBatches, 'range');
    $barForecast = array_map(fn($i) => $i['forecast'], $quotaBatches);
    $barActual = array_map(fn($i) => $i['actual'], $quotaBatches);
@endphp

{{-- Row 1: KPI --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($kpis as $k)
        <x-kpi :icon="($k['icon'] ?? null)" :title="$k['title']" :value="$k['value']" :delta="($k['delta'] ?? null)" :delta-type="($k['delta_type'] ?? null)" :helper="($k['helper'] ?? null)" />
    @endforeach
    @if(count($kpis) < 4)
        @for($i = count($kpis); $i < 4; $i++)
            <x-kpi title="—" value="—" />
        @endfor
    @endif
  </div>

{{-- Row 2: Charts --}}
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <x-card>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold">Quota per Batch</h3>
            <x-badge variant="info">Consumed vs Remaining</x-badge>
        </div>
        <div id="donutQuota" class="h-64 md:h-80" data-series='@json($donutSeries)' data-labels='@json($donutLabels)'></div>
    </x-card>
    <x-card>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold">Forecast vs Actual by Range PK</h3>
            <x-badge variant="neutral">Monthly</x-badge>
        </div>
        <div id="barForecastActual" class="h-64 md:h-80" data-categories='@json($barCategories)' data-forecast='@json($barForecast)' data-actual='@json($barActual)'></div>
    </x-card>
  </div>

{{-- Row 3: Three lists --}}
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <x-card>
        <h3 class="mb-3 text-lg font-semibold">Quota Alerts</h3>
        <ul class="space-y-3">
            @forelse($quotaBatches as $qb)
                <li class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-slate-800">{{ $qb['gov_ref'] }} • {{ $qb['range'] }}</div>
                        <div class="text-xs text-slate-500">Forecast: {{ number_format($qb['forecast']) }} • Actual: {{ number_format($qb['actual']) }}</div>
                    </div>
                    @php
                        $variant = ['Available'=>'success','Limited'=>'warning','Depleted'=>'danger'][$qb['remaining_status']] ?? 'neutral';
                    @endphp
                    <x-badge :variant="$variant">{{ $qb['remaining_status'] }}</x-badge>
                </li>
            @empty
                <li class="text-sm text-slate-500">No alerts.</li>
            @endforelse
        </ul>
    </x-card>
    <x-card>
        <h3 class="mb-3 text-lg font-semibold">Recent Purchase Orders</h3>
        <x-table zebra>
            <x-slot:head>
                <th class="px-4 py-2 text-left">PO#</th>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Model</th>
                <th class="px-4 py-2 text-left">Status</th>
                <th class="px-4 py-2 text-right">Qty</th>
            </x-slot:head>
            <x-slot:body>
                @foreach($recentPOs as $po)
                    @php
                        $variant = match($po['status']) { 'Completed' => 'success', 'In Transit' => 'warning', default => 'info' };
                    @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-800">{{ $po['po_number'] }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $po['date'] }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $po['model_short'] }}</td>
                        <td class="px-4 py-2"><x-badge :variant="$variant">{{ $po['status'] }}</x-badge></td>
                        <td class="px-4 py-2 text-right">{{ number_format($po['qty']) }}</td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-table>
    </x-card>
    <x-card>
        <h3 class="mb-3 text-lg font-semibold">Recent Shipments</h3>
        <x-table zebra>
            <x-slot:head>
                <th class="px-4 py-2 text-left">Shipment#</th>
                <th class="px-4 py-2 text-left">ETA</th>
                <th class="px-4 py-2 text-left">Status</th>
                <th class="px-4 py-2 text-right">Qty</th>
            </x-slot:head>
            <x-slot:body>
                @foreach($recentShipments as $sp)
                    @php
                        $variant = match($sp['status']) { 'Received' => 'success', 'In Transit' => 'warning', default => 'neutral' };
                    @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-800">{{ $sp['ship_number'] }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $sp['eta'] }}</td>
                        <td class="px-4 py-2"><x-badge :variant="$variant">{{ $sp['status'] }}</x-badge></td>
                        <td class="px-4 py-2 text-right">{{ number_format($sp['qty']) }}</td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-table>
    </x-card>
  </div>

{{-- Row 4: Timeline + Users --}}
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <x-card>
        <h3 class="mb-3 text-lg font-semibold">Latest Quota History</h3>
        <x-timeline :items="$quotaHistory" />
    </x-card>
    <div class="grid grid-cols-1 gap-6">
        <x-card>
            <h3 class="mb-3 text-lg font-semibold">Users by Role</h3>
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-3">
                @foreach($usersByRole as $role => $count)
                    <li class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2">
                        <span class="text-sm text-slate-700">{{ $role }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-lg font-semibold">Recent Users</h3>
            <x-table zebra>
                <x-slot:head>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Role</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Last Login</th>
                </x-slot:head>
                <x-slot:body>
                    @foreach($recentUsers as $u)
                        @php $variant = $u['status'] === 'Active' ? 'success' : 'danger'; @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-800">
                                <div>{{ $u['name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $u['email'] }}</div>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $u['role'] }}</td>
                            <td class="px-4 py-2"><x-badge :variant="$variant">{{ $u['status'] }}</x-badge></td>
                            <td class="px-4 py-2 text-slate-600">{{ $u['last_login'] }}</td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>
        </x-card>
    </div>
  </div>

@push('scripts')
    <script src="{{ asset('js/dashboard-charts.js') }}?v={{ time() }}"></script>
    <script>
        window.dashboardChartData = {
            donut: { series: @json($donutSeries), labels: @json($donutLabels) },
            bar: { categories: @json($barCategories), forecast: @json($barForecast), actual: @json($barActual) }
        };
        (function(){
            var data = window.dashboardChartData || {};
            function callInit(){ if (window.initDashboardCharts) window.initDashboardCharts(data); }
            if (!window.__apexInjected) {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
                s.async = true; s.onload = callInit;
                document.head.appendChild(s);
                window.__apexInjected = true;
            } else { callInit(); }
        })();
    </script>
@endpush
@endsection

