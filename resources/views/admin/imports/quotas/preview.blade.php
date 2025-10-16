@extends('layouts.admin')

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
          <form method="POST" action="{{ route('admin.imports.quotas.publish.form', $import) }}">
            @csrf
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="1" id="run_automap" name="run_automap">
              <label class="form-check-label" for="run_automap">Run automapper for period {{ $import->period_key }}</label>
            </div>
            <button class="btn btn-primary" type="submit" {{ $import->status !== 'ready' ? 'disabled' : '' }}>Publish Quotas</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <ul class="nav nav-tabs card-header-tabs" id="tabs">
            <li class="nav-item">
              <a class="nav-link active" data-status="normalized" href="#">Valid</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-status="error" href="#">Error</a>
            </li>
          </ul>
        </div>
        <div class="card-body p-0">
          <div id="table-wrap" class="p-3">
            <div id="loading" class="text-center text-muted">Loading...</div>
            <table class="table d-none" id="items-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Letter No</th>
                  <th>Category Label</th>
                  <th>Allocation</th>
                  <th>Period</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="items-body"></tbody>
            </table>
            <div class="p-2" id="pager"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const importId = {{ $import->id }};
  let status = 'normalized';
  const $tabs = document.querySelectorAll('#tabs a[data-status]');
  $tabs.forEach(a => a.addEventListener('click', (e) => {
    e.preventDefault();
    $tabs.forEach(x => x.classList.remove('active'));
    a.classList.add('active');
    status = a.getAttribute('data-status');
    fetchPage(1);
  }));

  document.getElementById('btn-refresh').addEventListener('click', async () => {
    try {
      const res = await fetch("{{ route('admin.imports.summary', $import) }}");
      const j = await res.json();
      document.getElementById('sum-total').textContent = j.total_rows ?? 0;
      document.getElementById('sum-valid').textContent = j.valid_rows ?? 0;
      document.getElementById('sum-error').textContent = j.error_rows ?? 0;
    } catch (e) { console.warn(e); }
  });

  async function fetchPage(page) {
    const url = new URL("{{ route('admin.imports.items', $import) }}", window.location.origin);
    url.searchParams.set('status', status);
    url.searchParams.set('per_page', 20);
    url.searchParams.set('page', page);
    document.getElementById('loading').classList.remove('d-none');
    document.getElementById('items-table').classList.add('d-none');
    try {
      const res = await fetch(url);
      const j = await res.json();
      renderItems(j);
    } catch (e) { console.warn(e); }
    document.getElementById('loading').classList.add('d-none');
    document.getElementById('items-table').classList.remove('d-none');
  }

  function renderItems(j) {
    const body = document.getElementById('items-body');
    body.innerHTML = '';
    (j.data || []).forEach((row) => {
      const tr = document.createElement('tr');
      const raw = row.raw_json || {};
      const norm = row.normalized_json || {};
      const period = norm.period_start && norm.period_end ? `${norm.period_start} - ${norm.period_end}` : '-';
      tr.innerHTML = `
        <td>${row.row_index}</td>
        <td>${(norm.letter_no ?? raw.LETTER_NO ?? '')}</td>
        <td>${(norm.category_label ?? raw.CATEGORY_LABEL ?? '')}</td>
        <td>${(norm.allocation ?? raw.ALLOCATION ?? '')}</td>
        <td>${period}</td>
        <td><span class="badge ${row.status==='normalized' ? 'bg-success' : 'bg-danger'}">${row.status}</span></td>
      `;
      body.appendChild(tr);
    });
    const pager = document.getElementById('pager');
    pager.innerHTML = `Page ${j.current_page} / ${j.last_page}`;
  }

  fetchPage(1);
});
</script>
@endsection

