{{-- resources/views/admin/mapping/model_hs/preview.blade.php --}}
@extends('layouts.admin')

@section('title','Preview Import Model &rarr; HS')
@section('page-title','Preview Import Model &rarr; HS')

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('admin.mapping.model_hs.index') }}">Import Model &rarr; HS</a></li>
  <li class="breadcrumb-item active">Preview</li>
@endsection

@section('content')
<div class="container-fluid">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <strong>Ringkasan</strong>
        <span class="badge bg-secondary ms-2">Total: {{ (int)($preview['total'] ?? 0) }}</span>
        <span class="badge bg-success ms-1">Valid: {{ (int)($preview['valid'] ?? 0) }}</span>
        <span class="badge bg-danger ms-1">Errors: {{ (int)($preview['error_count'] ?? 0) }}</span>
      </div>
      @php
        $rows = $preview['rows'] ?? [];
        $hasErrors = (int)($preview['error_count'] ?? 0) > 0;
        $hasValid = (int)($preview['valid'] ?? 0) > 0;
        $blockingErrors = false; // errors that cannot be fixed by create_missing
        $missingProductErrors = 0;
        foreach ($rows as $rr) {
          $st = $rr['status'] ?? '';
          $notesLower = strtolower((string)($rr['notes'] ?? ''));
          if ($st === 'error') {
            if (str_contains($notesLower, 'produk tidak ditemukan')) {
              $missingProductErrors++;
            } else {
              $blockingErrors = true; break;
            }
          }
        }
      @endphp
      <form method="POST" action="{{ route('admin.mapping.model_hs.publish') }}" class="d-flex align-items-center gap-3">
        @csrf
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" id="create_missing_ck" name="create_missing" checked>
          <label class="form-check-label" for="create_missing_ck">Buat model jika belum ada</label>
        </div>
        <button id="btnPublish" type="submit" class="btn btn-primary" {{ ($blockingErrors || ($hasErrors && !$hasValid && $missingProductErrors===0)) ? 'disabled' : '' }} title="{{ $hasErrors ? 'Perbaiki error atau centang opsi buat produk' : '' }}">Publish</button>
        <a href="{{ route('admin.mapping.model_hs.index') }}" class="btn btn-outline-secondary">Kembali</a>
      </form>
    </div>
    <div class="card-body p-0">
      @if(($preview['error_count'] ?? 0) > 0)
        <div class="alert alert-warning m-3">Ada error pada data. Perbaiki sebelum publish.</div>
      @endif
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
            @foreach(($preview['rows'] ?? []) as $i=>$r)
              <tr class="{{ ($r['status'] ?? '') === 'error' ? 'table-danger' : ((($r['status'] ?? '') === 'skip') ? 'table-warning' : '') }}">
                <td>{{ $r['row'] ?? ($i+2) }}</td>
                <td>{{ $r['model'] ?? '' }}</td>
                <td>{{ $r['hs_code'] ?? '' }}</td>
                <td>
                  @php $st = $r['status'] ?? 'error'; @endphp
                  <span class="badge {{ $st === 'ok' ? 'bg-success' : ($st === 'skip' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ strtoupper($st) }}</span>
                </td>
                <td>
                  {{ $r['notes'] ?? '' }}
                  @php
                    $notesLower = strtolower((string)($r['notes'] ?? ''));
                    $needsHsPk = str_contains($notesLower, 'hs belum punya pk');
                    $needsProduct = str_contains($notesLower, 'produk tidak ditemukan') || str_contains($notesLower, 'model belum ada') || str_contains($notesLower, 'model tidak ditemukan');
                  @endphp
                  @if(($r['status'] ?? '') !== 'ok')
                    @if($needsProduct && auth()->user()?->can('product.create') && Route::has('admin.master.quick_hs.create'))
                      <div class="mt-2">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.master.quick_hs.create', [
                            'model' => $r['model'] ?? '',
                            'return' => request()->fullUrl(),
                          ]) }}">
                          Tambah Model + HS
                        </a>
                      </div>
                    @endif
                    @if($needsHsPk)
                      <div class="mt-2">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.imports.hs_pk.index') }}">Buka Import HS &rarr; PK</a>
                      </div>
                    @endif
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
(function(){
  const ck = document.getElementById('create_missing_ck');
  const btn = document.getElementById('btnPublish');
  const blockingErrors = {{ $blockingErrors ? 'true' : 'false' }};
  const hasValid = {{ $hasValid ? 'true' : 'false' }};
  const hasOnlyMissingProductErrors = {{ (!$blockingErrors && $missingProductErrors>0) ? 'true' : 'false' }};
  function update(){
    if(!btn) return;
    if(blockingErrors){ btn.disabled = true; btn.title = 'Perbaiki error sebelum publish'; return; }
    const allowByCreateMissing = ck?.checked && hasOnlyMissingProductErrors;
    btn.disabled = !(allowByCreateMissing || hasValid);
    btn.title = btn.disabled ? 'Perbaiki error atau centang opsi buat produk' : '';
  }
  ck?.addEventListener('change', update);
  update();
})();
</script>
@endpush
@endsection
