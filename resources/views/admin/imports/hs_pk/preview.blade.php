@extends('layouts.admin')

@section('title', "Preview Import HS & PK #{$import->id}")
@section('page-title', 'Preview Import HS & PK')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.imports.hs_pk.index') }}">Import HS & PK</a></li>
    <li class="breadcrumb-item active">Preview #{{ $import->id }}</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-md-12">
      @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
      @endif
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong>HS & PK Import #{{ $import->id }}</strong>
          </div>
          <div>
            <span class="badge bg-secondary me-1">Total: <span id="sum-total">{{ (int)($import->total_rows ?? 0) }}</span></span>
            <span class="badge bg-success me-1">Valid: <span id="sum-valid">{{ (int)($import->valid_rows ?? 0) }}</span></span>
            <span class="badge bg-danger me-1">Error: <span id="sum-error">{{ (int)($import->error_rows ?? 0) }}</span></span>
            <span class="badge bg-info text-dark">Status: <span id="sum-status">{{ $import->status }}</span></span>
          </div>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted small">Updated: {{ optional($import->updated_at)->format('Y-m-d H:i') }}</div>
            <div>
              <button class="btn btn-outline-secondary btn-sm" id="btn-refresh">Refresh Summary</button>
            </div>
          </div>
          @if((int)($import->total_rows ?? 0) === 0)
            <div class="alert alert-warning" role="alert">
              <div class="fw-semibold mb-1">Tidak ada baris terdeteksi.</div>
              <div>Pastikan sheet ‘HS code master’, header HS_CODE dan DESC ada di baris pertama. Jika file dari generator/script, coba buka di Excel lalu Save As (.xlsx).</div>
              <div class="mt-2"><a href="{{ route('admin.imports.hs_pk.index') }}" class="btn btn-sm btn-outline-primary">Kembali ke Upload</a></div>
            </div>
          @endif
          @if(!empty($import->notes))
            <div class="alert alert-info" role="alert">{{ $import->notes }}</div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Publish</div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.imports.hs_pk.publish.form', $import) }}">
            @csrf
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="1" id="run_automap" name="run_automap" checked>
              <label class="form-check-label" for="run_automap">Run automapper (opsional)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="1" id="update_existing" name="update_existing" checked>
              <label class="form-check-label" for="update_existing">Update PK jika HS sudah ada (upsert)</label>
            </div>
            <small class="text-muted d-block mb-2">Automapper akan menyelaraskan mapping Product ↔ Quota berdasarkan HS/PK.</small>
            <button class="btn btn-primary" type="submit" {{ $import->status !== 'ready' ? 'disabled' : '' }}>Publish HS & PK</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Data Hasil Import</span>
          <div>
            <button class="btn btn-sm btn-outline-secondary" id="btn-copy">Copy Summary</button>
            <button class="btn btn-sm btn-outline-secondary" id="btn-download-errors">Download Errors (CSV)</button>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="errors-tab" data-bs-toggle="tab" data-bs-target="#errors-pane" type="button" role="tab" aria-controls="errors-pane" aria-selected="true">Error</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="valid-tab" data-bs-toggle="tab" data-bs-target="#valid-pane" type="button" role="tab" aria-controls="valid-pane" aria-selected="false">Valid</button>
            </li>
          </ul>
          <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="errors-pane" role="tabpanel" aria-labelledby="errors-tab">
              <div id="errors-empty" class="text-muted" aria-live="polite" style="display:none;">Tidak ada error pada import ini.</div>
              <div class="table-responsive">
                <table class="table table-sm" id="errors-table">
                  <thead>
                    <tr>
                      <th>Row</th>
                      <th>Cuplikan Data</th>
                      <th>Alasan Error</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <button class="btn btn-sm btn-outline-secondary" id="errors-prev">Prev</button>
                <div class="small" id="errors-pageinfo"></div>
                <button class="btn btn-sm btn-outline-secondary" id="errors-next">Next</button>
              </div>
            </div>
            <div class="tab-pane fade" id="valid-pane" role="tabpanel" aria-labelledby="valid-tab">
              <div id="valid-empty" class="text-muted" aria-live="polite" style="display:none;">Tidak ada data valid.</div>
              <div class="table-responsive">
                <table class="table table-sm" id="valid-table">
                  <thead>
                    <tr>
                      <th>Row</th>
                      <th>HS_CODE</th>
                      <th>DESC</th>
                      <th>PK Anchor</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <button class="btn btn-sm btn-outline-secondary" id="valid-prev">Prev</button>
                <div class="small" id="valid-pageinfo"></div>
                <button class="btn btn-sm btn-outline-secondary" id="valid-next">Next</button>
              </div>
            </div>
          </div>
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
        let csv = 'Row,HS Code,PK Code,Product Name,Status,Message\n';
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
        link.setAttribute('download', 'hs_pk_preview.csv');
        link.click();
        URL.revokeObjectURL(url);
    });
})();
</script>
@endpush

@push('scripts')
<script>
(function(){
  const summaryUrl = "{{ route('admin.imports.summary', $import) }}";
  const itemsUrl = "{{ route('admin.imports.items', $import) }}";

  const sumTotal = document.getElementById('sum-total');
  const sumValid = document.getElementById('sum-valid');
  const sumError = document.getElementById('sum-error');
  const sumStatus = document.getElementById('sum-status');

  document.getElementById('btn-refresh')?.addEventListener('click', function(){
    fetch(summaryUrl).then(r=>r.json()).then(d=>{
      sumTotal.textContent = d.total_rows ?? 0;
      sumValid.textContent = d.valid_rows ?? 0;
      sumError.textContent = d.error_rows ?? 0;
      if (d.status) sumStatus.textContent = d.status;
    });
  });

  document.getElementById('btn-copy')?.addEventListener('click', function(){
    const summary = `Valid: ${sumValid.textContent} / Error: ${sumError.textContent} / Total: ${sumTotal.textContent}`;
    navigator.clipboard.writeText(summary).then(()=> alert('Summary disalin.'));
  });

  let errPage=1, errLast=1, valPage=1, valLast=1;

  function renderErrors(page){
    const url = itemsUrl + `?status=error&per_page=20&page=${page}`;
    fetch(url).then(r=>r.json()).then(d=>{
      const tbody = document.querySelector('#errors-table tbody');
      tbody.innerHTML = '';
      const empty = document.getElementById('errors-empty');
      if (!d.data || d.data.length===0){
        empty.style.display='block';
      } else {
        empty.style.display='none';
        d.data.forEach(item=>{
          const raw = item.raw_json || {};
          const err = item.errors_json || [];
          const tr = document.createElement('tr');
          const snap = `HS_CODE=${raw.HS_CODE||''}; DESC=${raw.DESC||''}`;
          tr.innerHTML = `<td>${item.row_index}</td><td>${snap}</td><td>${(err||[]).join('; ')}</td>`;
          tbody.appendChild(tr);
        });
      }
      errPage = d.current_page || 1;
      errLast = d.last_page || 1;
      document.getElementById('errors-pageinfo').textContent = `Page ${errPage} / ${errLast}`;
    });
  }

  function renderValid(page){
    const url = itemsUrl + `?status=normalized&per_page=20&page=${page}`;
    fetch(url).then(r=>r.json()).then(d=>{
      const tbody = document.querySelector('#valid-table tbody');
      tbody.innerHTML = '';
      const empty = document.getElementById('valid-empty');
      if (!d.data || d.data.length===0){
        empty.style.display='block';
      } else {
        empty.style.display='none';
        d.data.forEach(item=>{
          const raw = item.raw_json || {};
          const norm = item.normalized_json || {};
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${item.row_index}</td><td>${raw.HS_CODE||''}</td><td>${raw.DESC||''}</td><td>${(norm.pk_anchor??'')}</td>`;
          tbody.appendChild(tr);
        });
      }
      valPage = d.current_page || 1;
      valLast = d.last_page || 1;
      document.getElementById('valid-pageinfo').textContent = `Page ${valPage} / ${valLast}`;
    });
  }

  document.getElementById('errors-prev')?.addEventListener('click', ()=>{ if (errPage>1) renderErrors(errPage-1); });
  document.getElementById('errors-next')?.addEventListener('click', ()=>{ if (errPage<errLast) renderErrors(errPage+1); });
  document.getElementById('valid-prev')?.addEventListener('click', ()=>{ if (valPage>1) renderValid(valPage-1); });
  document.getElementById('valid-next')?.addEventListener('click', ()=>{ if (valPage<valLast) renderValid(valPage+1); });

  renderErrors(1);
  document.getElementById('valid-tab')?.addEventListener('shown.bs.tab', function(){
    if (document.querySelector('#valid-table tbody').children.length===0){
      renderValid(1);
    }
  });

  document.getElementById('btn-download-errors')?.addEventListener('click', function(){
    const rows = Array.from(document.querySelectorAll('#errors-table tbody tr'));
    let csv = 'Row,HS_CODE,DESC,Errors\n';
    rows.forEach(tr=>{
      const tds = tr.querySelectorAll('td');
      const snap = (tds[1]?.textContent||'');
      const parts = Object.fromEntries(snap.split(';').map(s=>s.trim()).filter(Boolean).map(s=>{
        const i = s.indexOf('=');
        return i>0 ? [s.slice(0,i), s.slice(i+1)] : [s, ''];
      }));
      const line = [tds[0]?.textContent||'', parts.HS_CODE||'', parts.DESC||'', tds[2]?.textContent||''];
      csv += line.map(v=>`"${(v||'').replace(/"/g,'""')}"`).join(',')+'\n';
    });
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download='hs_pk_errors.csv'; a.click(); URL.revokeObjectURL(url);
  });
})();
</script>
@endpush
