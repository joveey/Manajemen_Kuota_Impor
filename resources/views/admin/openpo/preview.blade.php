{{-- resources/views/admin/openpo/preview.blade.php --}}
@extends('layouts.admin')

@section('title', 'Preview Open PO')
@section('page-title', 'Preview Open PO')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('admin.openpo.form') }}">Import Open PO</a></li>
  <li class="breadcrumb-item active">Preview</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between">
          <div>Ringkasan</div>
          <div>
            <span class="badge bg-secondary">PO: {{ (int)($summary['groups'] ?? 0) }}</span>
            <span class="badge bg-info text-dark">Rows: {{ (int)($summary['rows'] ?? 0) }}</span>
            <span class="badge {{ (($summary['error_count'] ?? 0) > 0) ? 'bg-danger' : 'bg-success' }}">Errors: {{ (int)($summary['error_count'] ?? 0) }}</span>
          </div>
        </div>
        <div class="card-body">
          @if (($summary['error_count'] ?? 0) > 0)
            <div class="alert alert-warning">Ada error pada data. Perbaiki sebelum publish.</div>
          @endif
          <form method="POST" action="{{ route('admin.openpo.publish') }}">
            @csrf
            <button class="btn btn-primary" type="submit" {{ (($summary['error_count'] ?? 0) > 0) ? 'disabled' : '' }} title="{{ (($summary['error_count'] ?? 0) > 0) ? 'Perbaiki error sebelum publish' : '' }}">Publish</button>
            <a href="{{ route('admin.openpo.form') }}" class="btn btn-outline-secondary">Kembali</a>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="accordion" id="acc-po">
    @php $i=0; @endphp
    @foreach ($result['groups'] as $po => $g)
      @php $i++; $lines = $g['lines'] ?? []; $totalQty = collect($lines)->sum('qty_ordered'); @endphp
      <div class="accordion-item mb-2">
        <h2 class="accordion-header" id="h{{ $i }}">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c{{ $i }}" aria-expanded="false" aria-controls="c{{ $i }}">
            <div class="w-100 d-flex justify-content-between">
              <div><strong>PO:</strong> {{ $po }} | <strong>Date:</strong> {{ $g['po_date'] }} | <strong>Supplier:</strong> {{ $g['supplier'] }}</div>
              <div><span class="badge bg-secondary">Lines: {{ count($lines) }}</span> <span class="badge bg-info text-dark">Qty: {{ (float)$totalQty }}</span></div>
            </div>
          </button>
        </h2>
        <div id="c{{ $i }}" class="accordion-collapse collapse" aria-labelledby="h{{ $i }}" data-bs-parent="#acc-po">
          <div class="accordion-body">
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead>
                  <tr>
                    <th>#</th><th>LINE_NO</th><th>ITEM_CODE</th><th>ITEM_DESC</th><th>HS_CODE</th><th>QTY</th><th>UOM</th><th>ETA_DATE</th>
                    <th>Status</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($lines as $ln)
                    <tr>
                      <td>{{ $ln['row'] }}</td>
                      <td>{{ $ln['line_no'] ?? '' }}</td>
                      <td>{{ $ln['model_code'] }}</td>
                      <td>{{ $ln['item_desc'] ?? '' }}</td>
                      <td>{{ $ln['hs_code'] }}</td>
                      <td>{{ $ln['qty_ordered'] }}</td>
                      <td>{{ $ln['uom'] }}</td>
                      <td>{{ $ln['eta_date'] }}</td>
                      <td>
                        <span class="badge {{ $ln['validation_status']==='ok' ? 'bg-success' : 'bg-danger' }}">{{ strtoupper($ln['validation_status']) }}</span>
                      </td>
                      <td>{{ $ln['validation_notes'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection


