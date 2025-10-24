@extends('layouts.admin')
@section('title','Preview Import GR')

@section('content')
<div class="page-shell">
  <div class="page-header"><h1 class="page-header__title">Preview Import GR</h1></div>

  <div class="card mb-3">
    <div class="card-body">
      <div><strong>File:</strong> {{ $import->source_filename }}</div>
      <div><strong>Status:</strong> {{ $import->status }}</div>
      <div><strong>Rows:</strong> valid {{ (int)$import->valid_rows }} / total {{ (int)$import->total_rows }} / error {{ (int)$import->error_rows }}</div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="{{ route('admin.imports.gr.index') }}">Kembali</a>
    @if($import->status === \App\Models\Import::STATUS_READY)
      <form method="POST" action="{{ route('admin.imports.gr.publish', $import) }}">@csrf<button class="btn btn-primary">Publish</button></form>
    @endif
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <strong>Review Data</strong>
        <span class="badge bg-success ms-2" id="badge-valid">Valid: {{ (int)$import->valid_rows }}</span>
        <span class="badge bg-danger ms-2" id="badge-error">Error: {{ (int)$import->error_rows }}</span>
      </div>
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-primary" id="tab-valid">Valid</button>
        <button type="button" class="btn btn-outline-primary" id="tab-error">Error</button>
      </div>
    </div>
    <div class="card-body">
      <div id="panel-valid">
        <div class="table-responsive">
          <table class="table table-sm mb-2">
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
        <div class="d-flex justify-content-between align-items-center small">
          <div id="pager-valid-info"></div>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="valid-prev">Prev</button>
            <button class="btn btn-outline-secondary" id="valid-next">Next</button>
          </div>
        </div>
      </div>

      <div id="panel-error" class="d-none">
        <ul id="list-error" class="mb-2 small">
          <li>Loading...</li>
        </ul>
        <div class="d-flex justify-content-between align-items-center small">
          <div id="pager-error-info"></div>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="error-prev">Prev</button>
            <button class="btn btn-outline-secondary" id="error-next">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

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
    tabValid.classList.toggle('btn-primary', isValid);
    tabValid.classList.toggle('btn-outline-primary', !isValid);
    tabError.classList.toggle('btn-primary', !isValid);
    tabError.classList.toggle('btn-outline-primary', isValid);
    panelValid.classList.toggle('d-none', !isValid);
    panelError.classList.toggle('d-none', isValid);
  }

  async function loadValid(){
    tbodyValid.innerHTML = '<tr><td colspan="9">Loading...</td></tr>';
    const res = await fetch(urlBase+`?status=normalized&per_page=${perPage}&page=${vPage}`);
    const j = await res.json();
    const rows = j.data || [];
    if (!rows.length){ tbodyValid.innerHTML = '<tr><td colspan="9" class="text-muted">Tidak ada data.</td></tr>'; }
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
    if (!rows.length){ listError.innerHTML = '<li class="text-muted">Tidak ada error.</li>'; }
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
