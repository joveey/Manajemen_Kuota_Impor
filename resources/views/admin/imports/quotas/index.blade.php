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

<div class="page-shell">
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
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Quota Input</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.imports.quotas.manual.add') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label for="manual-hs" class="form-label">HS Code</label>
                                <select
                                    id="manual-hs"
                                    name="hs_code"
                                    class="form-select @error('hs_code') is-invalid @enderror"
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
                                <input type="text" id="manual-hs-desc" class="form-control" value="{{ $selectedHsCode ? ($selectedHsOption['desc'] ?? '') : '' }}" readonly>
                                <div class="form-text">Description is automatically filled based on the HS code.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="manual-letter" class="form-label">Letter No (optional)</label>
                                <input type="text" id="manual-letter" name="letter_no" value="{{ old('letter_no') }}" class="form-control @error('letter_no') is-invalid @enderror" maxlength="100">
                                @error('letter_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                                        <th>HS Code</th>
                                        <th>Description</th>
                                        <th>Letter No</th>
                                        <th class="text-end">Quantity</th>
                                        <th>Period</th>
                                        <th class="text-center" style="width: 90px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($manualPreview as $item)
                                        <tr>
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
                                            <td>{{ $item['letter_no'] ?? '-' }}</td>
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
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No quotas in the preview yet. Add them using the form on the left.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.imports.quotas.manual.reset') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm" {{ empty($manualPreview) ? 'disabled' : '' }}>
                                <i class="fas fa-rotate-left me-1"></i> Reset Preview
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.imports.quotas.manual.publish') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm" {{ empty($manualPreview) ? 'disabled' : '' }}>
                                <i class="fas fa-cloud-upload-alt me-1"></i> Publish
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quota import history (file) removed as requested --}}

        <div class="card shadow-sm mt-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Quota History</span>
                <span class="badge bg-secondary">Showing {{ $manualQuotas->count() }} latest entries</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quota</th>
                                <th>HS Code</th>
                                <th>Description</th>
                                <th>Letter No</th>
                                <th class="text-end">Quantity</th>
                                <th>Period</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($manualQuotas as $quota)
                                @php
                                    $hsDisplay = '';
                                    if (!empty($quota->notes) && str_starts_with($quota->notes, 'HS')) {
                                        $hsDisplay = trim(substr($quota->notes, 3));
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $quota->display_number }}</td>
                                    <td>{{ $hsDisplay !== '' ? $hsDisplay : '-' }}</td>
                                    <td>
                                        @php
                                            $dd = trim((string)($quota->government_category ?? ''));
                                            $friendly2 = $dd;
                                            try {
                                                $pp = \App\Support\PkCategoryParser::parse($dd);
                                                $min = $pp['min_pk']; $max = $pp['max_pk'];
                                                $fmt = function($v){ return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); };
                                                if (!is_null($min) && !is_null($max)) {
                                                    $friendly2 = ($min === $max) ? ($fmt($min).' PK') : ($fmt($min).'-'.$fmt($max).' PK');
                                                } elseif (!is_null($min) && is_null($max)) {
                                                    $friendly2 = ($min >= 8 && $min < 10) ? '8-10 PK' : ('>'.$fmt($min).' PK');
                                                } elseif (is_null($min) && !is_null($max)) {
                                                    $friendly2 = ($max <= 8) ? '<8 PK' : ('<'.$fmt($max).' PK');
                                                } else {
                                                    if ($dd !== '' && stripos($dd,'PK')===false && strtoupper($dd)!=='ACCESORY') { $friendly2 = $dd.' PK'; }
                                                }
                                            } catch (\Throwable $e) {
                                                if ($dd !== '' && stripos($dd,'PK')===false && strtoupper($dd)!=='ACCESORY') { $friendly2 = $dd.' PK'; }
                                            }
                                        @endphp
                                        {{ $friendly2 !== '' ? $friendly2 : '-' }}
                                    </td>
                                    <td>{{ $quota->source_document ?? '-' }}</td>
                                    <td class="text-end">{{ number_format((float) $quota->total_allocation, 0) }}</td>
                                    <td>
                                        {{ optional($quota->period_start)->format('d-m-Y') ?? '-' }}
                                        @if($quota->period_end)
                                            - {{ optional($quota->period_end)->format('d-m-Y') }}
                                        @endif
                                    </td>
                                    <td>{{ optional($quota->created_at)->format('d M Y H:i') }}</td>
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
