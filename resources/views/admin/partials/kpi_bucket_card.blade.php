@props(['quota'])
@php
  $q = $quota;
  $alloc = (float)($q->total_allocation ?? 0);
  $actual = (float)($q->actual_consumed ?? 0);
  $forecast = (float)($q->forecast_consumed ?? 0);
  $inTransit = max($forecast - $actual, 0);
  $actRemain = max($alloc - $actual, 0);
  $foreRemain = max($alloc - $forecast, 0);
  $pct = $alloc > 0 ? round(($actual / $alloc) * 100) : 0;
@endphp
<div class="p-3 border rounded-3" style="background:#fff">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <strong>{{ $q->government_category }}</strong>
    <a href="{{ route('admin.quotas.show', $q) }}" class="btn btn-sm btn-outline-primary">Detail</a>
  </div>
  <div class="small text-muted mb-2">Periode: {{ optional($q->period_start)->format('M Y') ?? '-' }} - {{ optional($q->period_end)->format('M Y') ?? '-' }}</div>
  <div class="row g-2 small">
    <div class="col"><div>Allocation</div><div class="fw-bold">{{ number_format($alloc) }}</div></div>
    <div class="col"><div>Consumed</div><div class="fw-bold">{{ number_format($actual) }}</div></div>
    <div class="col"><div>In-Transit</div><div class="fw-bold">{{ number_format($inTransit) }}</div></div>
    <div class="col"><div>Forecast Rem.</div><div class="fw-bold">{{ number_format($foreRemain) }}</div></div>
    <div class="col"><div>Actual Rem.</div><div class="fw-bold">{{ number_format($actRemain) }}</div></div>
  </div>
  <div class="progress mt-2" style="height:8px;">
    <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
  </div>
</div>

