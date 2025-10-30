{{-- resources/views/admin/mapping/model_hs/index.blade.php --}}
@extends('layouts.admin')

@section('title','Import Model > HS')
@section('page-title','Import Model > HS')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('admin.mapping.mapped.page') }}">Mapping</a></li>
  <li class="breadcrumb-item active">Import Model > HS</li>
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

          <form method="POST" action="{{ route('admin.mapping.model_hs.upload') }}" enctype="multipart/form-data" id="modelhs-upload" novalidate>
            @csrf
            <div class="mb-3">
              <label for="file" class="form-label">Excel/CSV file (columns: MODEL, HS_CODE)</label>
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
        <div class="card-header fw-semibold">Guidelines</div>
        <div class="card-body">
          <ul class="mb-0">
            <li>Do not <strong>overwrite</strong> HS that already exist on the product.</li>
            <li>HS must already exist in <code>hs_code_pk_mappings</code> (has PK).</li>
            <li>The optional period selects which HS+PK record is used to display PK/Category.</li>
            <li>The product (MODEL) must already exist in the master.</li>
            <li>Rows that violate the rules become <strong>errors</strong> and are not published.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  </div>

@push('scripts')
<script>
(function(){
  const form = document.getElementById('modelhs-upload');
  const file = document.getElementById('file');
  const fileHelp = document.getElementById('file_help');
  function validate(){
    let ok=true; file.classList.remove('is-invalid'); fileHelp.textContent='';
    if(!file.files || file.files.length===0){ ok=false; file.classList.add('is-invalid'); fileHelp.textContent='File is required (.xlsx/.xls/.csv, max 10MB).'; }
    else { const name=file.files[0].name.toLowerCase(); if(!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv')){ ok=false; file.classList.add('is-invalid'); fileHelp.textContent='Invalid file type (.xlsx/.xls/.csv).'; } }
    return ok;
  }
  form?.addEventListener('submit', function(e){ if(!validate()){ e.preventDefault(); e.stopPropagation(); }});
})();
</script>
@endpush
@endsection
