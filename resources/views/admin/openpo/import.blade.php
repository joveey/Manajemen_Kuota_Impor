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
              <label for="file" class="form-label">File Excel/CSV</label>
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
        <div class="card-header fw-semibold">Format yang dibutuhkan</div>
        <div class="card-body">
          <ul>
            <li>Sheet: <code>List PO</code></li>
            <li>Header wajib: <code>PO_DOC</code>, <code>CREATED_DATE</code>, <code>VENDOR_NO</code>, <code>VENDOR_NAME</code>, <code>LINE_NO</code>, <code>ITEM_CODE</code>, <code>ITEM_DESC</code>, <code>QTY</code></li>
            <li>Header lain yang boleh ada: <code>WH_CODE</code>, <code>WH_NAME</code>, <code>SUBINV_CODE</code>, <code>SUBINV_NAME</code>, <code>WH_SOURCE</code>, <code>SUBINV_SOURCE</code>, <code>AMOUNT</code>, <code>CAT_PO</code>, <code>CAT_DESC</code>, <code>MAT_GRP</code>, <code>HS_CODE</code>.</li>
            <li>HS_CODE boleh kosong; jika kosong akan diresolve dari mapping model→HS. Anda bisa sertakan sheet <code>mapping hs code by model</code> dengan header <code>MODEL</code>, <code>HS_CODE</code> untuk memperbarui mapping sekaligus dipakai saat validasi.</li>
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
    if(!file.files || file.files.length===0){ ok=false; file.classList.add('is-invalid'); fileHelp.textContent='File wajib (.xlsx/.xls/.csv, maks 10MB).'; }
    else { const name=file.files[0].name.toLowerCase(); if(!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv')){ ok=false; file.classList.add('is-invalid'); fileHelp.textContent='Tipe file salah (.xlsx/.xls/.csv).'; } }
    return ok;
  }
  form?.addEventListener('submit', function(e){ if(!validate()){ e.preventDefault(); e.stopPropagation(); } });
})();
</script>
@endpush
@endsection
