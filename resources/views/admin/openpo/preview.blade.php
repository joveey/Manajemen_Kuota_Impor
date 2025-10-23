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
          <form method="POST" action="{{ route('admin.openpo.publish') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Mode Publish</label>
              <select name="publish_mode" class="form-select">
                <option value="insert" {{ old('publish_mode')==='insert' ? 'selected' : '' }}>Insert add</option>
                <option value="replace" {{ old('publish_mode')==='replace' ? 'selected' : '' }}>Replace</option>
              </select>
              <small class="text-muted">Insert add: hanya menambah baris baru. Replace: hapus baris lama untuk PO di file, lalu tulis ulang sesuai file.</small>
            </div>
            <div class="col-md-8 d-flex gap-2">
              <button class="btn btn-primary" type="submit" {{ (($summary['error_count'] ?? 0) > 0) ? 'disabled' : '' }} title="{{ (($summary['error_count'] ?? 0) > 0) ? 'Perbaiki error sebelum publish' : '' }}">Publish</button>
              <a href="{{ route('admin.openpo.form') }}" class="btn btn-outline-secondary">Kembali</a>
            </div>
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
                    <th>WH_CODE</th><th>WH_NAME</th><th>WH_SOURCE</th>
                    <th>SUBINV_CODE</th><th>SUBINV_NAME</th><th>SUBINV_SOURCE</th>
                    <th>AMOUNT</th><th>CAT_PO</th><th>CAT_DESC</th><th>MAT_GRP</th><th>SAP_STATUS</th>
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
                      <td>{{ $ln['wh_code'] ?? '' }}</td>
                      <td>{{ $ln['wh_name'] ?? '' }}</td>
                      <td>{{ $ln['wh_source'] ?? '' }}</td>
                      <td>{{ $ln['subinv_code'] ?? '' }}</td>
                      <td>{{ $ln['subinv_name'] ?? '' }}</td>
                      <td>{{ $ln['subinv_source'] ?? '' }}</td>
                      <td>{{ isset($ln['amount']) ? (is_numeric($ln['amount']) ? number_format($ln['amount'], 2) : $ln['amount']) : '' }}</td>
                      <td>{{ $ln['cat_code'] ?? '' }}</td>
                      <td>{{ $ln['cat_desc'] ?? '' }}</td>
                      <td>{{ $ln['mat_grp'] ?? '' }}</td>
                      <td>{{ $ln['sap_status'] ?? '' }}</td>
                      <td>
                        <span class="badge {{ $ln['validation_status']==='ok' ? 'bg-success' : 'bg-danger' }}">{{ strtoupper($ln['validation_status']) }}</span>
                      </td>
                      <td>
                        {{ $ln['validation_notes'] }}
                        @php
                          $notes = strtolower((string)($ln['validation_notes'] ?? ''));
                          $needsModel = str_contains($notes, 'model_code belum punya hs mapping') || str_contains($notes, 'hs mapping');
                        @endphp
                        @if(($ln['validation_status'] ?? '') !== 'ok' && $needsModel && auth()->user()?->can('product.create'))
                          <div class="mt-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.master.quick_hs.create', [
                                'model' => $ln['model_code'],
                                'period_key' => request()->query('period_key') ?? '',
                                'return' => request()->fullUrl(),
                              ]) }}">
                              Tambah Model -> HS
                            </a>
                          </div>
                        @endif
                      </td>
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

