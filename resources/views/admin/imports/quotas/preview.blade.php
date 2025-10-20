@extends('layouts.admin')

@section('title', "Preview Import Kuota #{$import->id}")
@section('page-title', 'Preview Import Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.imports.quotas.index') }}">Import Kuota</a></li>
    <li class="breadcrumb-item active">Preview #{{ $import->id }}</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-md-12">
      @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Quotas Import #{{ $import->id }}</strong>
            <span class="text-muted">Period: {{ $import->period_key }}</span>
          </div>
          <div>
            <span class="badge bg-secondary">Status: {{ $import->status }}</span>
          </div>
        </div>
        <div class="card-body">
          <button class="btn btn-outline-secondary btn-sm mb-3" id="btn-refresh">Refresh Summary</button>
          <div class="row mb-2">
            <div class="col-sm-3"><strong>Total Rows:</strong> <span id="sum-total">{{ (int)($import->total_rows ?? 0) }}</span></div>
            <div class="col-sm-3"><strong>Valid:</strong> <span id="sum-valid">{{ (int)($import->valid_rows ?? 0) }}</span></div>
            <div class="col-sm-3"><strong>Error:</strong> <span id="sum-error">{{ (int)($import->error_rows ?? 0) }}</span></div>
            <div class="col-sm-3"><strong>Updated:</strong> {{ optional($import->updated_at)->format('Y-m-d H:i') }}</div>
          </div>
          <button class="btn btn-outline-secondary btn-sm" id="btn-refresh">Refresh Summary</button>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Publish</div>
        <div class="card-body">
          <button class="btn btn-outline-secondary btn-sm mb-3" id="btn-refresh">Refresh Summary</button>
          <form method="POST" action="{{ route('admin.imports.quotas.publish.form', $import) }}">
            @csrf
            <button class="btn btn-primary" type="submit" {{ $import->status !== 'ready' ? 'disabled' : '' }}>Publish Kuota</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Preview Data</span>
          <div>
            <button class="btn btn-sm btn-outline-secondary" id="btn-copy">Copy Summary</button>
            <button class="btn btn-sm btn-outline-secondary" id="btn-download">Download Errors</button>
          </div>
        </div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0" id="preview-table">
            <thead>
              <tr>
                <th>#</th>
                <th class="text-nowrap">Quota Code</th>
                <th class="text-nowrap">Product</th>
                <th class="text-nowrap">Status</th>
                <th class="text-nowrap">Message</th>
              </tr>
            </thead>
            <tbody>
              @foreach($import->rows as $row)
                <tr>
                  <td>{{ $row->row_number }}</td>
                  <td>{{ $row->quota_code }}</td>
                  <td>{{ $row->product_name }}</td>
                  <td>{{ $row->status }}</td>
                  <td>{{ $row->message }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const table = document.getElementById('preview-table');

    document.getElementById('btn-refresh')?.addEventListener('click', function () {
        window.location.reload();
    });

    document.getElementById('btn-copy')?.addEventListener('click', function () {
        const summary = `Valid: {{ (int)($import->valid_rows ?? 0) }} / Error: {{ (int)($import->error_rows ?? 0) }} / Total: {{ (int)($import->total_rows ?? 0) }}`;
        navigator.clipboard.writeText(summary).then(function () {
            alert('Summary copied to clipboard.');
        });
    });

    document.getElementById('btn-download')?.addEventListener('click', function () {
        if (!table) {
            alert('Table not ready yet.');
            return;
        }
        let csv = 'Row,Quota Code,Product,Status,Message\n';
        table.querySelectorAll('tbody tr').forEach(function (row) {
            const cols = row.querySelectorAll('td');
            const cells = Array.from(cols).map(function (cell) {
                const text = cell.textContent.trim().replace(/"/g, '""');
                return `"${text}"`;
            });
            csv += cells.join(',') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'quota_preview.csv');
        link.click();
        URL.revokeObjectURL(url);
    });
})();
</script>
@endpush

