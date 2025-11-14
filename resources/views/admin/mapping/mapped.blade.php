{{-- resources/views/admin/mapping/mapped.blade.php --}}
@extends('layouts.admin')

@section('title', 'Model > HS (Mapped)')
@section('page-title', 'Model > HS (Mapped)')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item active">Model > HS (Mapped)</li>
@endsection

@section('content')
<div class="container-fluid px-0">
  <div class="mm-card mb-3">
    <div class="mm-card__body">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
          <input type="text" name="search" value="{{ $search ?? '' }}" class="mm-input" placeholder="Search model/code/name/HS...">
        </div>
        <div class="col-md-3">
          <label class="mm-check">
            <input type="checkbox" id="only_active" name="only_active" value="1" {{ !empty($onlyActive) ? 'checked' : '' }}>
            <span>Active Only</span>
          </label>
        </div>
        <div class="col-md-4 d-flex gap-2 justify-content-end">
          <button type="submit" class="mm-btn mm-btn--primary"><i class="fas fa-search me-2"></i>Show</button>
          <a href="{{ route('admin.mapping.mapped.page') }}" class="mm-btn mm-btn--ghost">Reset</a>
          @if(Route::has('admin.master.quick_hs.create') && auth()->user()?->can('product.create'))
            <a href="{{ route('admin.master.quick_hs.create', ['return' => request()->fullUrl()]) }}" class="mm-btn mm-btn--success">
              <i class="fas fa-circle-plus me-1"></i> Add Model > HS
            </a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="mm-card">
    <div class="mm-card__body table-responsive">
      <table class="mm-table align-middle">
        <thead>
          <tr>
            <th style="width:60px">#</th>
            <th>Model/SKU</th>
            <th>Name</th>
            <th>HS Code</th>
            <th class="text-end">PK</th>
            <th>Category</th>
            <th>Related Quotas</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($products as $p)
            <tr>
              <td>{{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}</td>
              <td>{{ $p->sap_model ?: $p->code }}</td>
              <td>{{ $p->name }}</td>
              <td>
                @if($p->hs_code)
                  <span class="mm-pill mm-pill--info">{{ $p->hs_code }}</span>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td class="text-end">
                @php $label = $p->hs_desc ?? null; @endphp
                @if(!empty($label))
                  {{ $label }}
                @elseif(!is_null($p->pk_capacity))
                  {{ number_format((float)$p->pk_capacity, 2) }}
                @else
                  -
                @endif
              </td>
              <td>{{ $p->category ?: '-' }}</td>
              <td>
                @php
                  // De-duplicate related quota badges by quota_id and preserve Primary label
                  $grouped = $p->quotaMappings->groupBy('quota_id');
                @endphp
                @if($grouped->isEmpty())
                  <span class="text-muted">-</span>
                @else
                  @foreach($grouped as $quotaId => $items)
                    @php
                      $first = $items->first();
                      $isPrimaryAny = (bool) $items->contains(fn($it) => (bool) ($it->is_primary ?? false));
                    @endphp
                    <span class="mm-chip me-1 mb-1">
                      {{ $first->quota?->display_number ?? 'Unknown' }}
                      @if($isPrimaryAny)
                        <small class="text-success ms-1">Primary</small>
                      @endif
                    </span>
                  @endforeach
                @endif
              </td>
              <td>
                <span class="mm-badge {{ $p->is_active ? 'mm-badge--success' : 'mm-badge--muted' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted">No models have an HS Code yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
      <div class="d-flex justify-content-end">
        {{ $products->links() }}
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
.mm-card{ border:1px solid #dfe4f3; border-radius:16px; background:#ffffff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.mm-card__body{ padding:14px 16px; }
.mm-input{ width:100%; border:1px solid #cbd5f5; border-radius:12px; padding:10px 14px; font-size:13px; transition:border-color .2s ease, box-shadow .2s ease; }
.mm-input:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); outline:none; }
.mm-check{ display:inline-flex; align-items:center; gap:8px; color:#334155; font-size:13px; }
.mm-btn{ display:inline-flex; align-items:center; gap:8px; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; border:1px solid transparent; }
.mm-btn--primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
.mm-btn--ghost{ background:rgba(59,130,246,.08); color:#1d4ed8; border:1px solid #3b82f6; }
.mm-btn--success{ background:#16a34a; color:#fff; border-color:#16a34a; }
.mm-table{ width:100%; border-collapse:separate; border-spacing:0; }
.mm-table thead th{ background:#f8fbff; padding:12px 14px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.mm-table tbody td{ padding:12px 14px; border-top:1px solid #e5eaf5; font-size:13px; color:#1f2937; }
.mm-pill{ display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.mm-pill--info{ background:#e8f0ff; color:#1d4ed8; border:1px solid #c9dcff; }
.mm-chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#eef2fb; color:#334155; border:1px solid #e2e8f0; }
.mm-badge{ display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.mm-badge--success{ background:#e8faee; color:#15803d; border:1px solid #bbf7d0; }
.mm-badge--muted{ background:#e2e8f0; color:#475569; border:1px solid #d1d5db; }
</style>
@endpush
