@extends('layouts.admin')

@section('content')
@php
  $totalReceived = (int) $shipment->receipts->sum('quantity_received');
  $planned = max(0, (int) $shipment->quantity_planned);
  $percentage = $planned > 0 ? min(100, (int) round(($totalReceived / $planned) * 100)) : 0;
  $quota = optional(optional($shipment->purchaseOrder)->quota);
  $quotaStatus = $quota?->status ?? 'unknown';
  $statusClass = match ($quotaStatus) {
    'depleted' => 'bg-danger',
    'limited' => 'bg-warning text-dark',
    'available' => 'bg-success',
    default => 'bg-secondary',
  };
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Detail Shipment #{{ $shipment->id }}</h4>
  <a href="{{ route('admin.shipments.receipts.create', $shipment->id) }}" class="btn btn-success">
    Catat Penerimaan
  </a>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-3">
        <div class="fw-semibold text-muted text-uppercase small">PO</div>
        <div>{{ optional($shipment->purchaseOrder)->id ?? '-' }}</div>
      </div>
      <div class="col-md-3">
        <div class="fw-semibold text-muted text-uppercase small">Quota</div>
        <div>
          {{ $quota?->id ?? '-' }}
          @if($quota)
            <span class="badge {{ $statusClass }} ms-2 text-uppercase">{{ $quotaStatus }}</span>
          @endif
        </div>
      </div>
      <div class="col-md-3">
        <div class="fw-semibold text-muted text-uppercase small">Planned</div>
        <div>{{ $planned }}</div>
      </div>
      <div class="col-md-3">
        <div class="fw-semibold text-muted text-uppercase small d-flex justify-content-between">
          <span>Received</span>
          <span>{{ $totalReceived }} / {{ $planned }}</span>
        </div>
        <div class="progress" style="height: 8px;">
          <div
            class="progress-bar {{ $percentage >= 100 ? 'bg-success' : 'bg-primary' }}"
            role="progressbar"
            style="width: {{ $percentage }}%;"
            aria-valuenow="{{ $percentage }}"
            aria-valuemin="0"
            aria-valuemax="100">
          </div>
        </div>
        <small class="text-muted">{{ $percentage }}% terpenuhi</small>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Penerimaan (Receipts)</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Qty</th>
          <th>No. Dokumen</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipment->receipts->sortByDesc('receipt_date') as $r)
          <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($r->receipt_date)->toDateString() }}</td>
            <td>{{ (int)$r->quantity_received }}</td>
            <td>{{ $r->document_number ?? '-' }}</td>
            <td>{{ $r->notes ?? '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted">Belum ada penerimaan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
