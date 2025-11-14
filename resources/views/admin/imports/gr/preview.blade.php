@extends('layouts.admin')
@section('title','Preview Import GR')

@section('content')
<div class="page-shell">
  <div class="page-header"><h1 class="page-header__title">Preview Import GR</h1></div>

  <div class="gr-card mb-2">
    <div class="gr-card__body">
      <div><strong>File:</strong> {{ $import->source_filename }}</div>
      <div><strong>Status:</strong> {{ $import->status }}</div>
      <div><strong>Rows:</strong> valid {{ (int)$import->valid_rows }} / total {{ (int)$import->total_rows }} / error {{ (int)$import->error_rows }}</div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3">
    <a class="gr-btn gr-btn--ghost" href="{{ route('admin.imports.gr.index') }}">Back</a>
    @if($import->status === \App\Models\Import::STATUS_READY)
      <form method="POST" action="{{ route('admin.imports.gr.publish', $import) }}">@csrf<button class="gr-btn gr-btn--primary">Publish</button></form>
    @endif
  </div>

  <div class="gr-card">
    <div class="gr-card__header">
      <div>
        <div class="gr-card__title">Review Data</div>
        <div class="gr-badges">
          <span class="gr-badge gr-badge--success" id="badge-valid">Valid: {{ (int)$import->valid_rows }}</span>
          <span class="gr-badge gr-badge--danger" id="badge-error">Error: {{ (int)$import->error_rows }}</span>
        </div>
      </div>
      <div class="gr-tabs" role="tablist">
        <button type="button" class="gr-tab is-active" id="tab-valid">Valid</button>
        <button type="button" class="gr-tab" id="tab-error">Error</button>
      </div>
    </div>
    <div class="gr-card__body">
      <div id="panel-valid">
        <div class="table-responsive">
          <table class="gr-table mb-2">
            <thead>
              <tr>
                <th>#</th>
                <th>PO_NO</th>
                <th>LINE_NO</th>
                <th>RECEIVE_DATE</th>
                <th class="text-end">QTY</th>
                <th>CAT_PO</th>
                <th>INVOICE_NO</th>
                <th>ITEM_NAME</th>
                <th>VENDOR</th>
              </tr>
            </thead>
            <tbody id="tbody-valid"><tr><td colspan="9">Loading...</td></tr></tbody>
          </table>
        </div>
        <div class="gr-pager">
          <div class="gr-pager__info" id="pager-valid-info"></div>
          <div class="gr-pager__btns">
            <button class="gr-btn gr-btn--ghost gr-btn--sm" id="valid-prev">Prev</button>
            <button class="gr-btn gr-btn--ghost gr-btn--sm" id="valid-next">Next</button>
          </div>
        </div>
      </div>

      <div id="panel-error" class="d-none">
        <ul id="list-error" class="mb-2 small">
          <li>Loading...</li>
        </ul>
        <div class="gr-pager">
          <div class="gr-pager__info" id="pager-error-info"></div>
          <div class="gr-pager__btns">
            <button class="gr-btn gr-btn--ghost gr-btn--sm" id="error-prev">Prev</button>
            <button class="gr-btn gr-btn--ghost gr-btn--sm" id="error-next">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
.gr-card{ border:1px solid #dfe4f3; border-radius:16px; background:#fff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.gr-card__header{ padding:14px 16px; border-bottom:1px solid #eef2fb; display:flex; align-items:center; justify-content:space-between; }
.gr-card__title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.gr-card__body{ padding:16px; }
.gr-btn{ display:inline-flex; align-items:center; gap:8px; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; border:1px solid transparent; }
.gr-btn--primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
.gr-btn--ghost{ background:rgba(59,130,246,.08); color:#1d4ed8; border:1px solid #3b82f6; }
.gr-btn--sm{ padding:6px 12px; font-size:12px; }
.gr-badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.gr-badge--success{ background:#e8faee; color:#15803d; border:1px solid #bbf7d0; }
.gr-badge--danger{ background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
.gr-tabs{ display:inline-flex; gap:8px; background:#f1f5fe; border:1px solid #dbe4ff; padding:4px; border-radius:12px; }
.gr-tab{ background:transparent; border:0; padding:8px 14px; border-radius:10px; font-weight:700; font-size:12px; color:#1d4ed8; cursor:pointer; }
.gr-tab.is-active{ background:#2563eb; color:#fff; }
.gr-table{ width:100%; border-collapse:separate; border-spacing:0; }
.gr-table thead th{ background:#f8fbff; padding:10px 12px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.gr-table tbody td{ padding:10px 12px; border-top:1px solid #e5eaf5; font-size:13px; color:#1f2937; }
.gr-pager{ display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#64748b; }
.gr-pager__btns{ display:flex; gap:6px; }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const urlBase = "{{ route('admin.imports.items', $import) }}";
  const tabValid = document.getElementById('tab-valid');
  const tabError = document.getElementById('tab-error');
  const panelValid = document.getElementById('panel-valid');
  const panelError = document.getElementById('panel-error');
  const tbodyValid = document.getElementById('tbody-valid');
  const infoValid = document.getElementById('pager-valid-info');
  const infoError = document.getElementById('pager-error-info');
  const listError = document.getElementById('list-error');
  const btnVP = document.getElementById('valid-prev');
  const btnVN = document.getElementById('valid-next');
  const btnEP = document.getElementById('error-prev');
  const btnEN = document.getElementById('error-next');

  let vPage=1, ePage=1, perPage=20;

  function switchTo(mode){
    const isValid = mode==='valid';
    tabValid.classList.toggle('is-active', isValid);
    tabError.classList.toggle('is-active', !isValid);
    panelValid.classList.toggle('d-none', !isValid);
    panelError.classList.toggle('d-none', isValid);
  }

  async function loadValid(){
    tbodyValid.innerHTML = '<tr><td colspan="9">Loading...</td></tr>';
    const res = await fetch(urlBase+`?status=normalized&per_page=${perPage}&page=${vPage}`);
    const j = await res.json();
    const rows = j.data || [];
    if (!rows.length){ tbodyValid.innerHTML = '<tr><td colspan="9" class="text-muted">No data.</td></tr>'; }
    else {
      tbodyValid.innerHTML = rows.map((it,idx)=>{
        const d = it.normalized_json || {};
        const vendor = [d.VENDOR_CODE||'', d.VENDOR_NAME||''].filter(Boolean).join(' - ');
        return `<tr>
          <td>${(j.current_page-1)*j.per_page + idx + 1}</td>
          <td>${d.po||''}</td>
          <td>${d.ln||''}</td>
          <td>${d.date||''}</td>
          <td class="text-end">${Number(d.qty||0).toLocaleString()}</td>
          <td>${d.cat||''}</td>
          <td>${d.inv||''}</td>
          <td>${d.ITEM_NAME||''}</td>
          <td>${vendor}</td>
        </tr>`;
      }).join('');
    }
    infoValid.textContent = `Page ${j.current_page} / ${j.last_page} | Total ${j.total}`;
    btnVP.disabled = j.current_page<=1; btnVN.disabled = j.current_page>=j.last_page;
  }

  async function loadError(){
    listError.innerHTML = '<li>Loading...</li>';
    const res = await fetch(urlBase+`?status=error&per_page=${perPage}&page=${ePage}`);
    const j = await res.json();
    const rows = j.data || [];
    if (!rows.length){ listError.innerHTML = '<li class="text-muted">No errors.</li>'; }
    else {
      listError.innerHTML = rows.map(it=>{
        const errs = it.errors_json || [];
        const raw = it.raw_json || {};
        return `<li><strong>Row ${it.row_index}</strong> — ${errs.join(', ')} (PO:${raw.po||''} Line:${raw.ln||''} Qty:${raw.qty||''})</li>`;
      }).join('');
    }
    infoError.textContent = `Page ${j.current_page} / ${j.last_page} | Total ${j.total}`;
    btnEP.disabled = j.current_page<=1; btnEN.disabled = j.current_page>=j.last_page;
  }

  // Events
  tabValid.addEventListener('click', ()=>{ switchTo('valid'); });
  tabError.addEventListener('click', ()=>{ switchTo('error'); });
  btnVP.addEventListener('click', ()=>{ if(vPage>1){ vPage--; loadValid(); } });
  btnVN.addEventListener('click', ()=>{ vPage++; loadValid(); });
  btnEP.addEventListener('click', ()=>{ if(ePage>1){ ePage--; loadError(); } });
  btnEN.addEventListener('click', ()=>{ ePage++; loadError(); });

  // Init
  switchTo('valid');
  loadValid();
  loadError();
})();
</script>
@endpush
@endsection
