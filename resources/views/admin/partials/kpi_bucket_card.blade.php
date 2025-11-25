@props(['quota'])
@php
    $q = $quota;
    $alloc = max((float) ($q->total_allocation ?? 0), 0);
    // Derive consumption directly from remaining so it always reflects GR-linked state
    $foreRemain = max((float) ($q->forecast_remaining ?? $alloc), 0);
    $actRemain = max((float) ($q->actual_remaining ?? $alloc), 0);
    $forecast = max($alloc - min($foreRemain, $alloc), 0);
    $actual = max($alloc - min($actRemain, $alloc), 0);
    // In-Transit per spec: prefer precomputed outstanding (PO - GR) from controller,
    // fallback to (forecast - actual) if not provided
    $inTransit = isset($q->in_transit) ? max((float) $q->in_transit, 0) : max($forecast - $actual, 0);
    $pctRaw = $alloc > 0 ? ($actual / $alloc) * 100 : 0;
    $pctDisplay = max(0, min(100, (int) round($pctRaw)));
    $progressClass = $pctDisplay >= 90 ? 'kpi-card__progress-fill--critical'
        : ($pctDisplay >= 60 ? 'kpi-card__progress-fill--warning' : '');
    $periodStart = optional($q->period_start)->format('M Y') ?? '-';
    $periodEnd = optional($q->period_end)->format('M Y') ?? '-';
    $category = $q->government_category ?? $q->display_number ?? 'Quota';
    $displayCategory = trim((string) $category);
    if ($displayCategory !== '') {
        $upper = strtoupper($displayCategory);
        if (stripos($displayCategory, 'PK') === false
            && $upper !== 'ACC'
            && !str_contains($upper, 'ACCESSORY')
            && !str_contains($upper, 'ACCESORY')) {
            $displayCategory .= ' PK';
        }
    }
@endphp
<div class="kpi-card">
    <div class="kpi-card__header">
        <div>
            <div class="kpi-card__title">{{ $displayCategory }}</div>
            <div class="kpi-card__period">Period: {{ $periodStart }} - {{ $periodEnd }}</div>
            <div style="font-size:11px;color:#64748b;">
                Debug: alloc={{ $alloc }}, actual_rem={{ $actRemain }}
            </div>
        </div>
    </div>
    <div class="kpi-card__progress" aria-hidden="true">
        <div class="kpi-card__progress-fill {{ $progressClass }}" style="width: {{ $pctDisplay }}%"></div>
    </div>
    <div class="kpi-card__progress-meta">
        <span>{{ $pctDisplay }}% consumed</span>
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
