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
          <div>Summary</div>
          <div>
            <span class="badge bg-secondary">PO: {{ (int)($summary['groups'] ?? 0) }}</span>
            <span class="badge bg-info text-dark">Rows: {{ (int)($summary['rows'] ?? 0) }}</span>
            <span class="badge {{ (($summary['error_count'] ?? 0) > 0) ? 'bg-danger' : 'bg-success' }}">Errors: {{ (int)($summary['error_count'] ?? 0) }}</span>
          </div>
        </div>
        <div class="card-body">
          @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          @endif
          @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              {{ session('status') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          @endif
          @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
              {{ session('warning') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          @endif
          @if (($summary['error_count'] ?? 0) > 0)
            <div class="alert alert-warning">There are errors in the data. Fix them before publishing.</div>
          @endif
          <form method="POST" action="{{ route('admin.openpo.publish') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Publish Mode</label>
              <select name="publish_mode" class="form-select">
                <option value="insert" {{ old('publish_mode')==='insert' ? 'selected' : '' }}>Insert add</option>
                <option value="replace" {{ old('publish_mode')==='replace' ? 'selected' : '' }}>Replace</option>
              </select>
              <small class="text-muted">Insert add: only adds new rows. Replace: deletes old rows for POs in the file, then rewrites according to the file.</small>
            </div>
            <div class="col-md-8 d-flex gap-2">
              <button class="btn btn-primary" type="submit" {{ (($summary['error_count'] ?? 0) > 0) ? 'disabled' : '' }} title="{{ (($summary['error_count'] ?? 0) > 0) ? 'Fix errors before publishing' : '' }}">Publish</button>
              <a href="{{ route('admin.openpo.form') }}" class="btn btn-outline-secondary">Back</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="accordion" id="acc-po">
    @php $i=0; @endphp
    @foreach ($result['groups'] as $po => $g)
      @php
        $i++;
        $lines = $g['lines'] ?? [];
        $totalQty = collect($lines)->sum('qty_ordered');
        $errorCount = collect($lines)->filter(function($ln){
            return strtolower((string)($ln['validation_status'] ?? '')) !== 'ok';
        })->count();
      @endphp
      <div class="accordion-item mb-2">
        <h2 class="accordion-header" id="h{{ $i }}">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c{{ $i }}" aria-expanded="false" aria-controls="c{{ $i }}">
            <div class="w-100 d-flex justify-content-between">
              <div><strong>PO:</strong> {{ $po }} | <strong>Date:</strong> {{ $g['po_date'] }} | <strong>Supplier:</strong> {{ $g['supplier'] }}</div>
              <div>
                <span class="badge bg-secondary">Lines: {{ count($lines) }}</span>
                <span class="badge bg-info text-dark">Qty: {{ (float)$totalQty }}</span>
                @if($errorCount > 0)
                  <span class="badge bg-danger" title="Number of rows with errors">Err: {{ $errorCount }}</span>
                @endif
              </div>
            </div>
          </button>
        </h2>
        <div id="c{{ $i }}" class="accordion-collapse collapse" aria-labelledby="h{{ $i }}" data-bs-parent="#acc-po">
          <div class="accordion-body">
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>LINE_NO</th>
                    <th>ITEM_CODE</th>
                    <th>HEADER TEXT</th>
                    <th>STORAGE LOCATION</th>
                    <th>ORDER QTY</th>
                    <th>TO INVOICE</th>
                    <th>TO DELIVER</th>
                    <th>DELIVERY DATE</th>
                    <th>PLANT</th>
                    <th>HS_CODE</th>
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
                      <td>{{ $ln['storage_location'] ?? '' }}</td>
                      <td>{{ $ln['qty_ordered'] }}</td>
                      <td>{{ $ln['qty_to_invoice'] ?? '' }}</td>
                      <td>{{ $ln['qty_to_deliver'] ?? '' }}</td>
                      <td>{{ $ln['eta_date'] }}</td>
                      <td>{{ $ln['plant_code'] ?? '' }}</td>
                      <td>{{ $ln['hs_code'] }}</td>
                      <td>
                        <span class="badge {{ $ln['validation_status']==='ok' ? 'bg-success' : 'bg-danger' }}">{{ strtoupper($ln['validation_status']) }}</span>
                      </td>
                      <td>
                        {{ $ln['validation_notes'] }}
                        @php
                          $notes = strtolower((string)($ln['validation_notes'] ?? ''));
                          $needsModel = str_contains($notes, 'model_code belum punya hs mapping') || str_contains($notes, 'hs mapping');
                        @endphp
                        @if(($ln['validation_status'] ?? '') !== 'ok' && $needsModel && auth()->user()?->can('product.create') && Route::has('admin.master.quick_hs.index'))
                          <div class="mt-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.master.quick_hs.index', [
                                'model' => $ln['model_code'],
                                'period_key' => request()->query('period_key') ?? '',
                                'return' => request()->fullUrl(),
                              ]) }}">
                              Add Model -> HS
                            </a>
                          </div>
                        @elseif(($ln['validation_status'] ?? '') !== 'ok' && $needsModel)
                          <div class="mt-2">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.imports.hs_pk.index') }}">Open Import HS -> PK</a>
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
