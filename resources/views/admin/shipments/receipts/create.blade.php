@extends('layouts.admin')

@section('content')
<div class="card">
  <div class="card-header">Catat Penerimaan</div>
  <div class="card-body">
    @php
      $totalReceived = (int) $shipment->receipts()->sum('quantity_received');
      $planned = (int) $shipment->quantity_planned;
      $remaining = max(0, $planned - $totalReceived);
    @endphp

    <form method="POST" action="{{ route('admin.shipments.receipts.store', $shipment->id) }}">
      @csrf
      <div class="mb-3">
        <label class="form-label">Tanggal Penerimaan</label>
        <input type="date" name="receipt_date" class="form-control" value="{{ old('receipt_date', now()->toDateString()) }}">
        @error('receipt_date')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
      <div class="mb-3">
        <label class="form-label d-flex align-items-center justify-content-between">
          <span>Qty Diterima</span>
          <small class="text-muted">Sisa tersedia: {{ $remaining }}</small>
        </label>
        <input
          type="number"
          min="1"
          @if($remaining > 0) max="{{ $remaining }}" @endif
          step="1"
          name="quantity_received"
          class="form-control"
          value="{{ old('quantity_received', $remaining > 0 ? $remaining : null) }}"
          @if($remaining === 0) disabled @endif
        >
        @error('quantity_received')<div class="text-danger small">{{ $message }}</div>@enderror
        @if($remaining === 0)
          <div class="text-warning small mt-1">Seluruh quantity sudah diterima untuk shipment ini.</div>
        @endif
      </div>
      <div class="mb-3">
        <label class="form-label">No. Dokumen (Customs/Container)</label>
        <input type="text" name="document_number" class="form-control" value="{{ old('document_number') }}">
        @error('document_number')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
      <div class="mb-3">
        <label class="form-label">Catatan</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
        @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
      <button type="submit" class="btn btn-primary" @if($remaining === 0) disabled @endif>Simpan</button>
      <a href="{{ route('admin.shipments.show', $shipment->id) }}" class="btn btn-secondary">Batal</a>
    </form>
  </div>
</div>
@endsection
