@extends('layouts.admin')

@section('title', 'Unmapped Products')
@section('page-title', 'Unmapped Products')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Unmapped Products</li>
@endsection

@section('content')
<div class="page-shell">
  <div class="page-header">
    <div>
      <h1 class="page-header__title">Unmapped Products</h1>
      <p class="page-header__subtitle">List of products that do not have a quota relationship or reference data.</p>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Unmapped Products</div>
    <div class="card-body">
      <form class="row g-3" id="filter-form">
        <div class="col-md-3">
          <label class="form-label">Period</label>
          <input type="text" name="period" id="period" class="form-control" value="{{ $period }}" placeholder="YYYY or YYYY-MM">
        </div>
        <div class="col-md-3">
          <label class="form-label">Reason</label>
          <select name="reason" id="reason" class="form-select">
            <option value="">(All)</option>
            <option value="missing_hs" {{ $reason==='missing_hs' ? 'selected' : '' }}>Missing HS</option>
            <option value="no_matching_quota" {{ $reason==='no_matching_quota' ? 'selected' : '' }}>No Matching Quota</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Per Page</label>
          <select name="per_page" id="per_page" class="form-select">
            @foreach([10,20,50,100,200] as $n)
              <option value="{{ $n }}" {{ (int)$perPage===$n ? 'selected' : '' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 align-self-end">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>
        <div class="col-md-2 align-self-end text-end">
          <a class="btn btn-outline-secondary" href="{{ route('admin.imports.hs_pk.index') }}">Go to HS -> PK Import</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>Results</div>
      <div id="pager-top" class="small text-muted"></div>
    </div>
    <div class="card-body p-0">
      <div class="p-3" id="loading">Loading...</div>
      <div class="table-responsive d-none" id="table-wrap">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Product ID</th>
              <th>Model/SKU</th>
              <th>HS Code</th>
              <th>Resolved PK</th>
              <th>Reason</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="p-3 d-none" id="empty">All products have been mapped in this period.</div>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <div id="pager-bottom" class="small text-muted"></div>
      <div>
        <button id="prev-btn" class="btn btn-sm btn-outline-secondary">Prev</button>
        <button id="next-btn" class="btn btn-sm btn-outline-secondary">Next</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const periodInput = document.getElementById('period');
  const reasonInput = document.getElementById('reason');
  const perPageInput = document.getElementById('per_page');
  const form = document.getElementById('filter-form');
  const rows = document.getElementById('rows');
  const loading = document.getElementById('loading');
  const tableWrap = document.getElementById('table-wrap');
  const empty = document.getElementById('empty');
  const prevBtn = document.getElementById('prev-btn');
  const nextBtn = document.getElementById('next-btn');
  const pagerTop = document.getElementById('pager-top');
  const pagerBottom = document.getElementById('pager-bottom');

  let currentPage = Number(new URLSearchParams(window.location.search).get('page') || 1);

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    currentPage = 1;
    const params = new URLSearchParams({
      period: periodInput.value || '',
      reason: reasonInput.value || '',
      per_page: perPageInput.value || '20',
      page: String(currentPage),
    });
    const newUrl = `${window.location.pathname}?${params}`;
    history.replaceState(null, '', newUrl);
    fetchData();
  });

  prevBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (currentPage > 1) { currentPage--; fetchData(); }
  });
  nextBtn.addEventListener('click', (e) => {
    e.preventDefault();
    currentPage++; fetchData();
  });

  async function fetchData() {
    rows.innerHTML = '';
    loading.classList.remove('d-none');
    tableWrap.classList.add('d-none');
    empty.classList.add('d-none');

    const params = new URLSearchParams({
      period: periodInput.value || '',
      reason: reasonInput.value || '',
      per_page: perPageInput.value || '20',
      page: String(currentPage),
    });
    const url = `{{ route('admin.mapping.unmapped') }}?${params.toString()}`;

    try {
      const res = await fetch(url);
      const j = await res.json();
      render(j);
    } catch (e) {
      console.warn(e);
      rows.innerHTML = '<tr><td colspan="6" class="text-danger">Failed to load data</td></tr>';
    }
    loading.classList.add('d-none');
  }

  function render(j) {
    const data = j.data || [];
    if (j.total === 0) {
      empty.classList.remove('d-none');
      pagerTop.textContent = '';
      pagerBottom.textContent = '';
      tableWrap.classList.add('d-none');
      return;
    }

    tableWrap.classList.remove('d-none');
    empty.classList.add('d-none');

    data.forEach(row => {
      const tr = document.createElement('tr');
      const model = row.model ?? '';
      tr.innerHTML = `
        <td>${row.product_id}</td>
        <td>${model}</td>
        <td>${row.hs_code ?? ''}</td>
        <td>${row.resolved_pk ?? ''}</td>
        <td><span class=\"badge bg-secondary\">${row.reason}</span></td>
        <td>
          <a href=\"{{ route('admin.imports.hs_pk.index') }}\" class=\"btn btn-sm btn-outline-primary\">HS -> PK Import</a>
        </td>
      `;
      rows.appendChild(tr);
    });

    pagerTop.textContent = `Page ${j.current_page} / ${j.last_page} â€¢ Total ${j.total}`;
    pagerBottom.textContent = pagerTop.textContent;

    prevBtn.disabled = j.current_page <= 1;
    nextBtn.disabled = j.current_page >= j.last_page;
  }

  // Initialize from URL
  const qs = new URLSearchParams(window.location.search);
  if (qs.get('period')) periodInput.value = qs.get('period');
  if (qs.get('reason')) reasonInput.value = qs.get('reason');
  if (qs.get('per_page')) perPageInput.value = qs.get('per_page');
  fetchData();
});
</script>
@endsection


