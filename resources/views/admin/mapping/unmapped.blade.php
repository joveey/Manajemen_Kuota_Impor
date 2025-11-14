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

  <div class="um-card mb-3">
    <div class="um-card__header"><div class="um-card__title">Unmapped Products</div></div>
    <div class="um-card__body">
      <form class="row g-3 align-items-end" id="filter-form">
        <div class="col-md-3">
          <label class="um-label" for="period">Period</label>
          <input type="text" name="period" id="period" class="um-input" value="{{ $period }}" placeholder="YYYY or YYYY-MM">
        </div>
        <div class="col-md-3">
          <label class="um-label" for="reason">Reason</label>
          <select name="reason" id="reason" class="um-select">
            <option value="">(All)</option>
            <option value="missing_hs" {{ $reason==='missing_hs' ? 'selected' : '' }}>Missing HS</option>
            <option value="no_matching_quota" {{ $reason==='no_matching_quota' ? 'selected' : '' }}>No Matching Quota</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="um-label" for="per_page">Per Page</label>
          <select name="per_page" id="per_page" class="um-select">
            @foreach([10,20,50,100,200] as $n)
              <option value="{{ $n }}" {{ (int)$perPage===$n ? 'selected' : '' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="um-btn um-btn--primary w-100"><i class="fas fa-search me-2"></i>Search</button>
        </div>
        <div class="col-md-2 text-end">
          <a class="um-btn um-btn--ghost" href="{{ route('admin.imports.hs_pk.index') }}">Go to HS -> PK Import</a>
        </div>
      </form>
    </div>
  </div>

  <div class="um-card">
    <div class="um-card__header d-flex justify-content-between align-items-center">
      <div class="um-card__title">Results</div>
      <div id="pager-top" class="small text-muted"></div>
    </div>
    <div class="um-card__body p-0">
      <div class="p-3" id="loading">Loading...</div>
      <div class="table-responsive d-none" id="table-wrap">
        <table class="um-table mb-0">
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
      <div class="p-3 d-none text-muted" id="empty">All products have been mapped in this period.</div>
    </div>
    <div class="um-card__footer d-flex justify-content-between align-items-center">
      <div id="pager-bottom" class="small text-muted"></div>
      <div>
        <button id="prev-btn" class="um-btn um-btn--ghost um-btn--sm">Prev</button>
        <button id="next-btn" class="um-btn um-btn--ghost um-btn--sm">Next</button>
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
        <td><span class=\"um-badge um-badge--muted\">${row.reason}</span></td>
        <td>
          <a href=\"{{ route('admin.imports.hs_pk.index') }}\" class=\"um-btn um-btn--ghost um-btn--sm\">HS -> PK Import</a>
        </td>
      `;
      rows.appendChild(tr);
    });

    pagerTop.textContent = `Page ${j.current_page} / ${j.last_page} | Total ${j.total}`;
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

@push('styles')
<style>
.um-card{ border:1px solid #dfe4f3; border-radius:16px; background:#fff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.um-card__header{ padding:14px 16px; border-bottom:1px solid #eef2fb; }
.um-card__title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.um-card__body{ padding:16px; }
.um-card__footer{ padding:10px 16px; border-top:1px solid #eef2fb; }
.um-label{ display:block; font-weight:600; margin-bottom:6px; color:#334155; }
.um-input, .um-select{ width:100%; border:1px solid #cbd5f5; border-radius:12px; padding:10px 12px; font-size:13px; transition:border-color .2s ease, box-shadow .2s ease; }
.um-input:focus, .um-select:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); outline:none; }
.um-btn{ display:inline-flex; align-items:center; gap:8px; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; border:1px solid transparent; }
.um-btn--primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
.um-btn--ghost{ background:rgba(59,130,246,.08); color:#1d4ed8; border:1px solid #3b82f6; }
.um-btn--sm{ padding:6px 12px; font-size:12px; }
.um-table{ width:100%; border-collapse:separate; border-spacing:0; }
.um-table thead th{ background:#f8fbff; padding:12px 14px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.um-table tbody td{ padding:12px 14px; border-top:1px solid #e5eaf5; font-size:13px; color:#1f2937; }
.um-badge{ display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.um-badge--muted{ background:#e2e8f0; color:#475569; border:1px solid #d1d5db; }
</style>
@endpush

