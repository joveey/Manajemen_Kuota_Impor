@props(['quota'])
@php
    $q = $quota;
    $alloc = max((float) ($q->total_allocation ?? 0), 0);
    $actual = max((float) ($q->actual_consumed ?? 0), 0);
    $forecast = max((float) ($q->forecast_consumed ?? 0), 0);
    $inTransit = max($forecast - $actual, 0);
    $actRemain = max($alloc - $actual, 0);
    $foreRemain = max($alloc - $forecast, 0);
    $pctRaw = $alloc > 0 ? ($actual / $alloc) * 100 : 0;
    $pctDisplay = max(0, min(100, (int) round($pctRaw)));
    $progressClass = $pctDisplay >= 90 ? 'kpi-card__progress-fill--critical'
        : ($pctDisplay >= 60 ? 'kpi-card__progress-fill--warning' : '');
    $periodStart = optional($q->period_start)->format('M Y') ?? '-';
    $periodEnd = optional($q->period_end)->format('M Y') ?? '-';
    $category = $q->government_category ?? $q->display_number ?? 'Quota';
@endphp
<div class="kpi-card">
    <div class="kpi-card__header">
        <div>
            <div class="kpi-card__title">{{ $category }}</div>
            <div class="kpi-card__period">Periode: {{ $periodStart }} - {{ $periodEnd }}</div>
        </div>
    </div>
    <div class="kpi-card__progress" aria-hidden="true">
        <div class="kpi-card__progress-fill {{ $progressClass }}" style="width: {{ $pctDisplay }}%"></div>
    </div>
    <div class="kpi-card__progress-meta">
        <span>{{ $pctDisplay }}% konsumsi</span>
        <span>Total {{ number_format($alloc) }}</span>
    </div>
    <dl class="kpi-card__metrics">
        <div class="kpi-card__metric">
            <dt>Allocation</dt>
            <dd>{{ number_format($alloc) }}</dd>
        </div>
        <div class="kpi-card__metric kpi-card__metric--accent">
            <dt>Consumed</dt>
            <dd>{{ number_format($actual) }}</dd>
        </div>
        <div class="kpi-card__metric">
            <dt>In-Transit</dt>
            <dd>{{ number_format($inTransit) }}</dd>
        </div>
        <div class="kpi-card__metric">
            <dt>Forecast Rem.</dt>
            <dd>{{ number_format($foreRemain) }}</dd>
        </div>
        <div class="kpi-card__metric kpi-card__metric--safe">
            <dt>Actual Rem.</dt>
            <dd>{{ number_format($actRemain) }}</dd>
        </div>
    </dl>
</div>
