{{-- resources/views/admin/openpo/import.blade.php --}}
@extends('layouts.admin')

@section('title', 'Import Open PO')
@section('page-title', 'Import Open PO')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item active">Import Open PO</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Upload & Preview</div>
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
            <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
          @endif

          <form method="POST" action="{{ route('admin.openpo.preview') }}" enctype="multipart/form-data" id="openpo-upload" novalidate>
            @csrf
            <div class="mb-3">
              <label for="file" class="form-label">Excel/CSV File</label>
              <input class="form-control" type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required>
              <div class="invalid-feedback" id="file_help" aria-live="polite"></div>
            </div>
            <button type="submit" class="btn btn-primary">Upload & Preview</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Required Format</div>
        <div class="card-body">
          <ul>
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
