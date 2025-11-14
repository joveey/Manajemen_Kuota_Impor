@extends('layouts.admin')
@section('title','Import GR (Good Receipt)')

@section('content')
<div class="page-shell">
  <div class="page-header">
    <div>
      <h1 class="page-header__title">Import GR (Good Receipt)</h1>
      <p class="page-header__subtitle">Upload SAP file with columns: PO_NO, LINE_NO, RECEIVE_DATE, QTY (optional INVOICE_NO).</p>
    </div>
  </div>

  <div class="gr-card mb-3">
    <div class="gr-card__body">
      <form method="POST" action="{{ route('admin.imports.gr.upload') }}" enctype="multipart/form-data" class="row g-3 align-items-center" id="gr-upload-form">
        @csrf
        <div class="col-md-6">
          <label class="gr-label" for="gr-file">File</label>
          <input type="file" id="gr-file" name="file" class="gr-input" accept=".xlsx,.xls,.csv" required>
          @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
          <div class="form-text gr-hint">Max 10MB. Types: .xlsx, .xls, .csv</div>
        </div>
        <div class="col-auto align-self-center">
          <button class="gr-btn gr-btn--primary" type="submit"><i class="fas fa-upload me-2"></i>Upload & Preview</button>
        </div>
      </form>
    </div>
  </div>

  <div class="gr-card">
    <div class="gr-card__header"><div class="gr-card__title">History</div></div>
    <div class="gr-card__body p-0">
      <div class="table-responsive">
        <table class="gr-table mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>File</th>
              <th>Status</th>
              <th>Created</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recent as $i)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $i->source_filename }}</td>
                <td>{{ $i->status }}</td>
                <td>{{ optional($i->created_at)->format('Y-m-d H:i:s') }}</td>
                <td class="text-end">
                  <a href="{{ route('admin.imports.gr.preview', $i) }}" class="gr-btn gr-btn--ghost gr-btn--sm">Preview</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted">No imports yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
.gr-card{ border:1px solid #dfe4f3; border-radius:16px; background:#fff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.gr-card__header{ padding:14px 16px; border-bottom:1px solid #eef2fb; display:flex; align-items:center; justify-content:space-between; }
.gr-card__title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.gr-card__body{ padding:16px; }

.gr-label{ display:block; font-weight:600; margin-bottom:6px; color:#334155; }
.gr-input{ display:block; width:100%; border:1px solid #cbd5f5; border-radius:12px; padding:10px 12px; font-size:13px; transition:border-color .2s, box-shadow .2s; }
.gr-input:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); outline:none; }
.gr-hint{ color:#64748b; }

.gr-btn{ display:inline-flex; align-items:center; gap:8px; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; border:1px solid transparent; }
.gr-btn--primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
.gr-btn--ghost{ background:rgba(59,130,246,.08); color:#1d4ed8; border:1px solid #3b82f6; }
.gr-btn--sm{ padding:6px 12px; font-size:12px; }
.gr-btn:hover{ filter:brightness(0.98); }

.gr-table{ width:100%; border-collapse:separate; border-spacing:0; }
.gr-table thead th{ background:#f8fbff; padding:12px 14px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.gr-table tbody td{ padding:12px 14px; border-top:1px solid #e5eaf5; font-size:13px; color:#1f2937; }
</style>
@endpush
