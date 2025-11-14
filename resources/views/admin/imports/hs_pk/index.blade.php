{{-- resources/views/admin/imports/hs_pk/index.blade.php (manual only) --}}
@extends('layouts.admin')

@section('title', 'HS & PK Input')
@section('page-title', 'HS & PK Input')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Input HS & PK</li>
@endsection

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Manual HS &amp; PK Input</h1>
            <p class="page-header__subtitle"></p>
        </div>
    </div>

    <div class="container-fluid px-0">
        <div class="row gy-3">
            <div class="col-md-7">
                <div class="hp-card">
                    <div class="hp-card__header"><div class="hp-card__title">HS &amp; PK Input Form</div></div>
                    <div class="hp-card__body">
                        @if (session('status'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
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

                        <form action="{{ route('admin.hs_pk.manual.store') }}" method="POST" class="row g-3" novalidate autocomplete="off">
                            @csrf
                            <div class="col-md-3">
                                <label class="hp-label" for="period_key">Period (YYYY)</label>
                                <input type="text" name="period_key" id="period_key" class="hp-input" placeholder="YYYY" inputmode="numeric" maxlength="4" value="{{ old('period_key') }}" title="Enter a 4-digit year, e.g. 2025" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                <div class="form-text hp-hint">Leave empty for legacy data.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="hp-label" for="hs_code">HS_CODE</label>
                                <input type="text" name="hs_code" id="hs_code" class="hp-input" placeholder="e.g., 8415.10.20" value="{{ old('hs_code') }}" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                            </div>
                            <div class="col-md-5">
                                <label class="hp-label" for="pk_value">Description</label>
                                <input type="text" name="pk_value" id="pk_value" class="hp-input" value="{{ old('pk_value') }}" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                                <div class="form-text hp-hint">Use the format: 8-10, &lt;8, &gt;10, or a single number.</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="hp-btn hp-btn--primary"><i class="fas fa-save me-2"></i>Save</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="hp-card mt-3">
                    <div class="hp-card__header"><div class="hp-card__title">Required Format</div></div>
                    <div class="hp-card__body">
                        <ul class="mb-2 hp-list">
                            <li>Input columns: <code>Period (YYYY)</code>, <code>HS_CODE</code>, <code>Description</code>.</li>
                            <li>Parsing rules for <code>Description</code> (PK category): use <code>8-10</code>, <code>&lt;8</code>, <code>&gt;10</code>, or a single number.</li>
                            <li>For ACC models, enter <code>ACC</code> in <code>HS_CODE</code> and <code>Accessory</code> in <code>Description</code>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-5" id="mapping-list">
                <div class="hp-card">
                    <div class="hp-card__header d-flex justify-content-between align-items-center">
                        <div class="hp-card__title">Mapping List</div>
                        <form method="GET" action="{{ route('admin.imports.hs_pk.index') }}" class="d-flex align-items-center" role="search" autocomplete="off">
                          <input type="text" class="hp-input hp-input--sm me-2" name="period_key" placeholder="Filter year (YYYY)" value="{{ $period ?? '' }}" style="width: 160px;" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                          <button class="hp-btn hp-btn--ghost hp-btn--sm" type="submit">Filter</button>
                        </form>
                    </div>
                    <div class="hp-card__body p-0">
                        <div class="table-responsive">
                          <table class="hp-table mb-0">
                            <thead>
                              <tr>
                                <th>HS Code</th>
                                <th>Period</th>
                                <th>Description</th>
                              </tr>
                            </thead>
                            <tbody>
                              @forelse(($rows ?? []) as $r)
                                <tr>
                                  <td>{{ $r->hs_code }}</td>
                                  <td>{{ isset($r->period_key) && $r->period_key === '' ? 'Legacy' : ($r->period_key ?? '') }}</td>
                                  <td>{{ isset($r->desc) && $r->desc !== '' ? $r->desc : number_format((float)($r->pk_capacity ?? 0), 2) }}</td>
                                </tr>
                              @empty
                                <tr>
                                  <td colspan="4" class="text-center text-muted py-4">No data.</td>
                                </tr>
                              @endforelse
                            </tbody>
                          </table>
                        </div>
                        <div class="p-2">
                          @if(isset($rows))
                            {{ $rows->links() }}
                          @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.hp-card{ border:1px solid #dfe4f3; border-radius:16px; background:#ffffff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.hp-card__header{ padding:14px 16px; border-bottom:1px solid #eef2fb; display:flex; align-items:center; justify-content:space-between; }
.hp-card__title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.hp-card__body{ padding:16px; }
.hp-label{ display:block; font-weight:600; margin-bottom:6px; color:#334155; }
.hp-input{ display:block; width:100%; border:1px solid #cbd5f5; border-radius:12px; padding:10px 12px; font-size:13px; transition:border-color .2s ease, box-shadow .2s ease; }
.hp-input:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); outline:none; }
.hp-input--sm{ padding:8px 10px; font-size:12px; border-radius:10px; }
.hp-btn{ display:inline-flex; align-items:center; gap:8px; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; border:1px solid transparent; }
.hp-btn--primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
.hp-btn--ghost{ background:rgba(59,130,246,.08); color:#1d4ed8; border:1px solid #3b82f6; }
.hp-btn--sm{ padding:6px 12px; font-size:12px; }
.hp-hint{ color:#64748b; }
.hp-list{ color:#334155; }
.hp-table{ width:100%; border-collapse:separate; border-spacing:0; }
.hp-table thead th{ background:#f8fbff; padding:10px 12px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.hp-table tbody td{ padding:10px 12px; border-top:1px solid #e5eaf5; font-size:13px; color:#1f2937; }
</style>
@endpush
