@extends('layouts.admin')

@section('content')
<div class="card">
  <div class="card-header">Catat Penerimaan</div>
  <div class="card-body">
    <form method="POST" action="{{ route('admin.shipments.receipts.store', $shipment->id) }}">
      @csrf
      <div class="mb-3">
        <label class="form-label">Tanggal Penerimaan</label>
        <input type="date" name="receipt_date" class="form-control" value="{{ old('receipt_date', now()->toDateString()) }}">
        @error('receipt_date')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
      <div class="mb-3">
        <label class="form-label">Qty Diterima</label>
        <input type="number" min="1" name="quantity_received" class="form-control" value="{{ old('quantity_received') }}">
        @error('quantity_received')<div class="text-danger small">{{ $message }}</div>@enderror
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
      <button type="submit" class="btn btn-primary">Simpan</button>
      <a href="{{ route('admin.shipments.show', $shipment->id) }}" class="btn btn-secondary">Batal</a>
    </form>
  </div>
</div>
@endsection

