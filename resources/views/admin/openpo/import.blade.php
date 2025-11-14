{{-- resources/views/admin/openpo/import.blade.php --}}
@extends('layouts.admin')

@section('title', 'Import Open PO')
@section('page-title', 'Import Open PO')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item active">Import Open PO</li>
@endsection

@section('content')
<div class="op-shell container-fluid px-0">
  <div class="op-grid">
    <div class="op-main">
      <div class="op-card">
        <div class="op-card__header"><div class="op-card__title">Upload & Preview</div></div>
        <div class="op-card__body">
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
            <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          @endif

          <form method="POST" action="{{ route('admin.openpo.preview') }}" enctype="multipart/form-data" id="openpo-upload" novalidate>
            @csrf
            <div class="mb-3">
              <label for="file" class="op-label">Excel/CSV File</label>
              <input class="op-input" type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required>
              <div class="invalid-feedback d-block small text-danger" id="file_help" aria-live="polite"></div>
              <div class="form-text op-hint">Max 10MB. Types: .xlsx, .xls, .csv</div>
            </div>
            <button type="submit" class="op-btn op-btn--primary"><i class="fas fa-upload me-2"></i>Upload & Preview</button>
          </form>
        </div>
      </div>
    </div>
    <div class="op-aside">
      <div class="op-card">
        <div class="op-card__header"><div class="op-card__title">Required Format</div></div>
        <div class="op-card__body">
          <ul class="op-list">
            <li>Accepts "PO Listed" export or legacy <code>List PO</code>.</li>
            <li>PO Listed headers (case-insensitive): <code>Purchasing Document</code>, <code>Material</code>, <code>Order Quantity</code>, <code>Delivery Date</code>, <code>Document Date</code>, <code>Vendor/supplying plant</code>, <code>header text</code>, <code>Plant</code>, <code>Storage Location</code>, <code>Still to be invoiced (qty)</code>, <code>Still to be delivered (qty)</code>.</li>
            <li>Legacy headers still supported: <code>PO_DOC</code>, <code>CREATED_DATE</code>, <code>DELIV_DATE</code>, <code>LINE_NO</code>, <code>ITEM_CODE</code>, <code>ITEM_DESC</code>, <code>QTY</code>.</li>
            <li>HS Code is optional; if omitted it will be resolved from the existing Model → HS mapping.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
.op-shell{ display:block; }
.op-grid{ display:grid; grid-template-columns: minmax(0, 1fr) 360px; gap:16px; }
@media (max-width: 992px){ .op-grid{ grid-template-columns: 1fr; } }

.op-card{ border:1px solid #dfe4f3; border-radius:16px; background:#ffffff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); display:flex; flex-direction:column; }
.op-card__header{ padding:14px 16px; border-bottom:1px solid #eef2fb; display:flex; align-items:center; justify-content:space-between; }
.op-card__title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.op-card__body{ padding:16px; }

.op-label{ display:block; font-weight:600; margin-bottom:6px; color:#334155; }
.op-input{ display:block; width:100%; border:1px solid #cbd5f5; border-radius:12px; padding:10px 12px; font-size:13px; transition:border-color .2s ease, box-shadow .2s ease; }
.op-input:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); outline:none; }
.op-btn{ display:inline-flex; align-items:center; gap:8px; border:1px solid #3b82f6; color:#1d4ed8; background:rgba(59,130,246,.08); border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; }
.op-btn--primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
.op-btn:hover{ filter:brightness(0.98); }
.op-hint{ color:#64748b; }
.op-list{ margin:0; padding-left:18px; color:#334155; }
.op-main{ min-width:0; }
.op-aside{ min-width:0; }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const form = document.getElementById('openpo-upload');
  const file = document.getElementById('file');
  const fileHelp = document.getElementById('file_help');
  function validate(){
    let ok=true; file.classList.remove('is-invalid'); fileHelp.textContent='';
    if(!file.files || file.files.length===0){ ok=false; file.classList.add('is-invalid'); fileHelp.textContent='File is required (.xlsx/.xls/.csv, max 10MB).'; }
    else { const name=file.files[0].name.toLowerCase(); if(!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv')){ ok=false; file.classList.add('is-invalid'); fileHelp.textContent='Invalid file type (.xlsx/.xls/.csv).'; } }
    return ok;
  }
  form?.addEventListener('submit', function(e){ if(!validate()){ e.preventDefault(); e.stopPropagation(); } });
})();
</script>
@endpush
@endsection

