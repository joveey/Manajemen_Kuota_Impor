{{-- resources/views/admin/mapping/mapped.blade.php --}}
@extends('layouts.admin')

@section('title', 'Model > HS (Mapped)')
@section('page-title', 'Model > HS (Mapped)')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item active">Model > HS (Mapped)</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-4">
          <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control" placeholder="Search model/code/name/HS...">
        </div>
        <div class="col-md-3 form-check mt-1">
          <input class="form-check-input" type="checkbox" id="only_active" name="only_active" value="1" {{ !empty($onlyActive) ? 'checked' : '' }}>
          <label class="form-check-label" for="only_active">Active Only</label>
        </div>
        <div class="col-md-5 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Show</button>
          <a href="{{ route('admin.mapping.mapped.page') }}" class="btn btn-outline-secondary">Reset</a>
          @if(Route::has('admin.master.quick_hs.create') && auth()->user()?->can('product.create'))
            <a href="{{ route('admin.master.quick_hs.create', ['return' => request()->fullUrl()]) }}" class="btn btn-success">
              <i class="fas fa-circle-plus"></i> Add Model > HS
            </a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-sm align-middle">
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
              <td><span class="badge bg-info text-dark">{{ $p->hs_code }}</span></td>
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
                    <span class="badge rounded-pill text-bg-light border me-1 mb-1">
                      {{ $first->quota?->display_number ?? 'Unknown' }}
                      @if($isPrimaryAny)
                        <small class="text-success ms-1">Primary</small>
                      @endif
                    </span>
                  @endforeach
                @endif
              </td>
              <td>
                <span class="badge {{ $p->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span>
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
