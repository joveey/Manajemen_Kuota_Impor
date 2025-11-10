{{-- resources/views/admin/imports/quotas/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Quota Import')
@section('page-title', 'Quota Import')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Quota Import</li>
@endsection

@section('content')
@php
    $hsSeedOptions = $hsSeedOptions ?? [];
    $selectedHsOption = $selectedHsOption ?? null;
    $manualPreview = $manualPreview ?? [];
    $manualSummary = $manualSummary ?? ['count' => 0, 'total_quantity' => 0];
    $selectedHsCode = old('hs_code', $selectedHsOption['id'] ?? null);
    $manualQuotas = $manualQuotas ?? collect();
@endphp

<div class="iq-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Quota Input</h1>
            <p class="page-header__subtitle">
               Quotas based on HS codes will be published to the master quota list.
            </p>
        </div>
    </div>

    <div class="container-fluid px-0">
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
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row gy-3">
            <div class="col-xl-5 col-lg-6">
                <div class="iq-card">
                    <div class="iq-card__header"><div class="iq-card__title">Quota Input</div></div>
                    <div class="iq-card__body">
                        <form method="POST" action="{{ route('admin.imports.quotas.manual.add') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label for="manual-quota-no" class="form-label">Quota No.</label>
                                <input
                                    type="text"
                                    id="manual-quota-no"
                                    name="quota_no"
                                    value="{{ old('quota_no') }}"
                                    class="iq-input @error('quota_no') is-invalid @enderror"
                                    placeholder="e.g., 04.PI-76.25.0108"
                                    required
                                >
                                <div class="form-text">Quota No. boleh diulang dan periode yang sama akan membuat entri baru.</div>
                                @error('quota_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="manual-hs" class="form-label">HS Code</label>
                                <select
                                    id="manual-hs"
                                    name="hs_code"
                                    class="iq-select @error('hs_code') is-invalid @enderror"
                                    required
                                >
                                    <option value="" disabled {{ $selectedHsCode ? '' : 'selected' }} hidden>Select HS</option>
                                    @foreach($hsSeedOptions as $option)
                                        <option value="{{ $option['id'] }}"
                                            data-desc="{{ $option['desc'] }}"
                                            @selected($selectedHsCode === $option['id'])>{{ $option['text'] }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">HS list follows the HS-to-PK master.</div>
                                @error('hs_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <input type="text" id="manual-hs-desc" class="iq-input" value="{{ $selectedHsCode ? ($selectedHsOption['desc'] ?? '') : '' }}" readonly>
                                <div class="form-text">Description is automatically filled based on the HS code.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="manual-quantity" class="form-label">Quantity</label>
                                <input type="number" step="1" min="0" id="manual-quantity" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>
                                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="period-start" class="form-label">Start Period</label>
                                <input type="text" id="period-start" name="period_start" value="{{ old('period_start') }}" class="form-control manual-date @error('period_start') is-invalid @enderror" placeholder="DD-MM-YYYY" required>
                                @error('period_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="period-end" class="form-label">End Period</label>
                                <input type="text" id="period-end" name="period_end" value="{{ old('period_end') }}" class="form-control manual-date @error('period_end') is-invalid @enderror" placeholder="DD-MM-YYYY" required>
                                @error('period_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Add to Preview
                                </button>
                                <a href="{{ route('admin.imports.quotas.index') }}" class="btn btn-outline-secondary">
                                    Reset Form
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-7 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Quota Preview</span>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge bg-secondary">Item: {{ $manualSummary['count'] }}</span>
                            <span class="badge bg-info text-dark">Total Qty: {{ number_format($manualSummary['total_quantity'], 0) }}</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Quota No.</th>
                                        <th>HS Code</th>
                                        <th>Description</th>
                                        <th class="text-end">Quantity</th>
                                        <th>Period</th>
                                        <th class="text-center" style="width: 90px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($manualPreview as $item)
                                        <tr>
                                            <td>{{ $item['quota_no'] ?? '-' }}</td>
                                            <td>{{ $item['hs_code'] ?? '-' }}</td>
                                            <td>
                                                @php
                                                    $d = trim((string)($item['hs_desc'] ?? ''));
                                                    $friendly = $d;
                                                    try {
                                                        $p = \App\Support\PkCategoryParser::parse($d);
                                                        $min = $p['min_pk']; $max = $p['max_pk'];
                                                        $fmt = function($v){ return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); };
                                                        if (!is_null($min) && !is_null($max)) {
                                                            $friendly = ($min === $max)
                                                                ? ($fmt($min).' PK')
                                                                : ($fmt($min).'-'.$fmt($max).' PK');
                                                        } elseif (!is_null($min) && is_null($max)) {
                                                            $friendly = ($min >= 8 && $min < 10) ? '8-10 PK' : ('>'.$fmt($min).' PK');
                                                        } elseif (is_null($min) && !is_null($max)) {
                                                            $friendly = ($max <= 8) ? '<8 PK' : ('<'.$fmt($max).' PK');
                                                        } else {
                                                            if ($d !== '' && stripos($d,'PK')===false && strtoupper($d)!=='ACCESORY') { $friendly = $d.' PK'; }
                                                        }
                                                    } catch (\Throwable $e) {
                                                        if ($d !== '' && stripos($d,'PK')===false && strtoupper($d)!=='ACCESORY') { $friendly = $d.' PK'; }
                                                    }
                                                @endphp
                                            {{ $friendly !== '' ? $friendly : '-' }}
                                            </td>
                                            <td class="text-end">{{ number_format($item['quantity'] ?? 0, 0) }}</td>
                                            <td>
                                                {{ $item['period_start'] ?? '-' }} -
                                                {{ $item['period_end'] ?? '-' }}
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" action="{{ route('admin.imports.quotas.manual.remove') }}">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $item['id'] }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No quotas in the preview yet. Add them using the form on the left.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="iq-card__footer">
                        <form method="POST" action="{{ route('admin.imports.quotas.manual.reset') }}">
                            @csrf
                            <button type="submit" class="iq-btn iq-btn--ghost" {{ empty($manualPreview) ? 'disabled' : '' }}>
                                Reset Preview
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.imports.quotas.manual.publish') }}">
                            @csrf
                            <button type="submit" class="iq-btn" {{ empty($manualPreview) ? 'disabled' : '' }}>
                                Publish
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quota import history (file) removed as requested --}}

        <div class="iq-card mt-4">
            <div class="iq-card__header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span>Quota History</span>
                    <span class="badge bg-secondary">Showing {{ ($quotaHistorySummary ? count($quotaHistorySummary) : 0) }} quota numbers</span>
                </div>
            </div>
            <div class="iq-card__body p-0">
                <div class="table-responsive">
                    <table class="iq-table">
                        <thead>
                            <tr>
                                <th>Quota No.</th>
                                <th class="qh-qty-head">Total Quantity</th>
                                <th class="text-center">Period</th>
                                <th class="text-center">Created</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="qh-tbody">
                            @forelse(($quotaHistorySummary ?? []) as $row)
                                @php
                                    $ps = $row['period_start'] ? \Illuminate\Support\Carbon::parse($row['period_start'])->format('d-m-Y') : '-';
                                    $pe = $row['period_end'] ? \Illuminate\Support\Carbon::parse($row['period_end'])->format('d-m-Y') : null;
                                    $qid = 'qh-'.md5($row['quota_no']);
                                @endphp
                                <tr data-quota="{{ $row['quota_no'] }}" data-target="{{ $qid }}" class="qh-summary">
                                    <td class="qh-qn">
                                        <i class="fas fa-chevron-right me-1 text-muted qh-caret" aria-hidden="true" style="cursor:pointer"></i>
                                        {{ $row['quota_no'] }}
                                    </td>
                                    <td class="qh-qty">{{ number_format((float) $row['total_quantity'], 0) }}</td>
                                    <td>{{ $ps }}@if($pe) - {{ $pe }}@endif</td>
                                    <td class="text-center text-nowrap">{{ \Illuminate\Support\Carbon::parse($row['created_at'])->setTimezone('Asia/Jakarta')->format('d-m-Y') }}</td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary qh-toggle" data-target="{{ $qid }}">Details</button></td>
                                </tr>
                                <tr id="{{ $qid }}" class="qh-detail" style="display:none; background:#f8fafc">
                                    <td colspan="5" class="p-0">
                                        <div class="p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="fw-semibold">HS items for {{ $row['quota_no'] }}</div>
                                                <button type="button" class="qh-btn qh-close" data-target="{{ $qid }}">Close</button>
                                            </div>
                                            @php $items = ($quotaHistoryDetails[$row['quota_no']] ?? []); @endphp
                                            @if(empty($items))
                                                <div class="text-muted">No HS details yet.</div>
                                            @else
                                                <div class="qh-list">
                                                    <div class="qh-row qh-row--head">
                                                        <div>HS Code</div>
                                                        <div>Description</div>
                                                        <div class="qh-qty">Quantity</div>
                                                        <div class="qh-period">Period</div>
                                                    </div>
                                                    @foreach($items as $it)
                                                        <div class="qh-row">
                                                            <div class="qh-code">{{ $it['hs_code'] ?: '-' }}</div>
                                                            <div>{{ $it['desc'] ?: '-' }}</div>
                                                            <div class="qh-qty">{{ number_format((float) $it['quantity'], 0) }}</div>
                                                            <div class="qh-period">{{ $it['period_start'] ?? '-' }}@if(!empty($it['period_end'])) - {{ $it['period_end'] }}@endif</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No manual quotas have been published yet.</td>
                                </tr>
                            @endforelse
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
(function () {
    const hsSelect = document.getElementById('manual-hs');
    const descInput = document.getElementById('manual-hs-desc');
    // Quota History filter + total aggregator
    // Toggle details per row (via caret or Details button). Close others when opening one.
    function hideAllDetails(){
        document.querySelectorAll('#qh-tbody tr.qh-detail').forEach(function(d){ d.style.display='none'; });
        document.querySelectorAll('#qh-tbody .qh-caret').forEach(function(c){
            c.classList.remove('fa-chevron-down');
            c.classList.add('fa-chevron-right');
        });
    }
    function toggleRow(id, caret){
        var el = document.getElementById(id);
        if(!el) return;
        var shown = (el.style.display !== 'none');
        if(shown){
            el.style.display = 'none';
            if(caret){ caret.classList.remove('fa-chevron-down'); caret.classList.add('fa-chevron-right'); }
        } else {
            hideAllDetails();
            el.style.display = '';
            if(caret){ caret.classList.remove('fa-chevron-right'); caret.classList.add('fa-chevron-down'); }
        }
    }
    document.querySelectorAll('#qh-tbody tr.qh-summary .qh-caret').forEach(function(icon){
        icon.addEventListener('click', function(e){
            e.stopPropagation();
            var tr = icon.closest('tr.qh-summary');
            var id = tr.getAttribute('data-target');
            toggleRow(id, icon);
        });
    });
    document.querySelectorAll('#qh-tbody .qh-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault(); e.stopPropagation();
            var id = btn.getAttribute('data-target');
            var caret = document.querySelector('tr.qh-summary[data-target="'+id+'"]').querySelector('.qh-caret');
            toggleRow(id, caret);
        });
    });
    document.querySelectorAll('#qh-tbody .qh-close').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault(); e.stopPropagation();
            var id = btn.getAttribute('data-target');
            var caret = document.querySelector('tr.qh-summary[data-target="'+id+'"]').querySelector('.qh-caret');
            toggleRow(id, caret);
        });
    });

    if (typeof flatpickr !== 'undefined') {
        flatpickr('.manual-date', {
            dateFormat: 'Y-m-d', // submitted to server
            altInput: true,
            altFormat: 'd-m-Y', // displayed to user
            allowInput: true
        });
    }

    if (hsSelect && descInput) {
        const updateDescription = () => {
            const option = hsSelect.selectedOptions[0];
            descInput.value = option ? (option.dataset.desc || '') : '';
        };
        hsSelect.addEventListener('change', updateDescription);
        updateDescription();
    }
})();
</script>
@endpush

@push('styles')
<style>
/* Lightweight, AdminLTE-free detail styling */
.iq-shell { display:flex; flex-direction:column; gap:16px; }
.iq-card { border:1px solid #dfe4f3; border-radius:16px; background:#ffffff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.iq-card__header{ padding:16px 18px; border-bottom:1px solid #eef2fb; display:flex; justify-content:space-between; align-items:center; gap:12px; }
.iq-card__title{ font-size:16px; font-weight:700; color:#0f172a; margin:0; }
.iq-card__body{ padding:18px; }
.iq-card__footer{ padding:12px 18px; border-top:1px solid #eef2fb; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
.iq-input, .iq-select{ border:1px solid #cbd5f5; border-radius:12px; padding:10px 14px; font-size:13px; width:100%; transition:border-color .2s ease, box-shadow .2s ease; }
.iq-input:focus, .iq-select:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.15); outline:none; }
.iq-btn{ background:#2563eb; color:#fff; border:none; border-radius:12px; padding:10px 16px; font-weight:600; font-size:13px; }
.iq-btn--ghost{ background:rgba(37,99,235,.08); color:#2563eb; border:1px solid #cbd5f5; }
.iq-table{ width:100%; border-collapse:separate; border-spacing:0; }
.iq-table thead th{ background:#f8fbff; padding:12px 14px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.iq-table tbody td{ padding:16px 14px; font-size:13px; color:#1f2937; border-top:1px solid #e5eaf5; }
/* Center align summary columns 2-4 */
.iq-table thead th:nth-child(2), .iq-table thead th:nth-child(3), .iq-table thead th:nth-child(4){ text-align:center; }
.iq-table tbody td:nth-child(2), .iq-table tbody td:nth-child(3), .iq-table tbody td:nth-child(4){ text-align:center; }
/* Action column right-aligned */
.iq-table thead th:nth-child(5){ text-align:right; }
.iq-table tbody td:nth-child(5){ text-align:right; }
/* Numeric alignment for Quantity */
.iq-table td.qh-qty, .iq-table th.qh-qty-head{ text-align:center; font-variant-numeric: tabular-nums; }
/* Created cell: center the date, push Details button to far right */
/* legacy created-cell styles no longer used */
.qh-btn { border:1px solid #cbd5f5; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600; }
.qh-list { display:flex; flex-direction:column; gap:0; border:1px solid #eef2fb; border-radius:10px; background:#f9fbff; }
.qh-row { display:grid; grid-template-columns: 1.1fr 1.4fr 1fr 1.1fr; gap:22px; padding:10px 12px; border-top:1px solid #eef2fb; font-size:13px; color:#1f2937; align-items:center; }
.qh-row:first-child { border-top:none; border-top-left-radius:10px; border-top-right-radius:10px; background:#f3f8ff; }
.qh-row--head { font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; font-weight:700; }
.qh-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color:#be123c; }
.qh-qty, .qh-period { text-align:center; font-variant-numeric: tabular-nums; }
.qh-summary { cursor:pointer; }
</style>
@endpush
