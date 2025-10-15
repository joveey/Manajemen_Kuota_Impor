@extends('layouts.admin')

@section('content')
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
    <div class="row">
      <div class="col-md-3"><strong>PO:</strong> {{ optional($shipment->purchaseOrder)->id ?? '-' }}</div>
      <div class="col-md-3"><strong>Quota:</strong> {{ optional(optional($shipment->purchaseOrder)->quota)->id ?? '-' }}</div>
      <div class="col-md-3"><strong>Planned:</strong> {{ (int)$shipment->quantity_planned }}</div>
      <div class="col-md-3">
        @php $totalReceived = (int)$shipment->receipts->sum('quantity_received'); @endphp
        <strong>Received:</strong> {{ $totalReceived }}
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

