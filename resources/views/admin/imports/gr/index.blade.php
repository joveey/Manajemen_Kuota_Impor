@extends('layouts.admin')
@section('title','Import GR (Good Receipt)')

@section('content')
<div class="page-shell">
  <div class="page-header">
    <div>
      <h1 class="page-header__title">Import GR (Good Receipt)</h1>
      <p class="page-header__subtitle">Upload file SAP berisi kolom: PO_NO, LINE_NO, RECEIVE_DATE, QTY (opsional INVOICE_NO).</p>
    </div>
  </div>

  <form class="card p-3" method="POST" action="{{ route('admin.imports.gr.upload') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label">File</label>
        <input type="file" name="file" class="form-control" required>
        @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary">Upload & Preview</button>
      </div>
    </div>
  </form>

  <div class="card mt-3">
    <div class="card-header">Riwayat</div>
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead><tr><th>#</th><th>File</th><th>Status</th><th>Dibuat</th><th></th></tr></thead>
        <tbody>
          @forelse($recent as $i)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ $i->source_filename }}</td>
              <td>{{ $i->status }}</td>
              <td>{{ $i->created_at }}</td>
              <td><a href="{{ route('admin.imports.gr.preview', $i) }}" class="btn btn-sm btn-outline-primary">Preview</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">Belum ada import.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

