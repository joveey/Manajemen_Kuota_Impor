{{-- resources/views/admin/mapping/model_hs/preview.blade.php --}}
@extends('layouts.admin')

@section('title','Preview Import Model → HS')
@section('page-title','Preview Import Model → HS')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('admin.mapping.model_hs.index') }}">Import Model → HS</a></li>
  <li class="breadcrumb-item active">Preview</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <strong>Ringkasan</strong>
        <span class="badge bg-secondary ms-2">Total: {{ (int)($total ?? 0) }}</span>
        <span class="badge bg-success ms-1">Valid: {{ (int)($valid ?? 0) }}</span>
        <span class="badge bg-danger ms-1">Error: {{ (int)($errors ?? 0) }}</span>
      </div>
      <form method="POST" action="{{ route('admin.mapping.model_hs.publish') }}">
        @csrf
        <button type="submit" class="btn btn-primary" {{ ($valid ?? 0) === 0 ? 'disabled' : '' }}>Publish</button>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>MODEL</th>
              <th>HS_CODE</th>
              <th>Status</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            @foreach(($rows ?? []) as $i=>$r)
              <tr class="{{ ($r['status'] ?? '') === 'error' ? 'table-danger' : ((($r['status'] ?? '') === 'skip') ? 'table-warning' : '') }}">
                <td>{{ $r['row'] ?? ($i+2) }}</td>
                <td>{{ $r['model'] ?? '' }}</td>
                <td>{{ $r['hs_code'] ?? '' }}</td>
                <td>
                  @php $st = $r['status'] ?? 'error'; @endphp
                  <span class="badge {{ $st === 'ok' ? 'bg-success' : ($st === 'skip' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ strtoupper($st) }}</span>
                </td>
                <td>{{ $r['notes'] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

