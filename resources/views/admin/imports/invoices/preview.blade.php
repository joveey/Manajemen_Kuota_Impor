@extends('layouts.admin')
@section('title','Preview Import Invoice')

@section('content')
<div class="page-shell">
  <div class="page-header"><h1 class="page-header__title">Preview Import Invoice</h1></div>

  <div class="card mb-3">
    <div class="card-body">
      <div><strong>File:</strong> {{ $import->source_filename }}</div>
      <div><strong>Status:</strong> {{ $import->status }}</div>
      <div><strong>Rows:</strong> valid {{ (int)$import->valid_rows }} / total {{ (int)$import->total_rows }} / error {{ (int)$import->error_rows }}</div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-2">
    <a class="btn btn-outline-secondary" href="{{ route('admin.imports.invoices.index') }}">Back</a>
    @if($import->status === \App\Models\Import::STATUS_READY)
      <form method="POST" action="{{ route('admin.imports.invoices.publish', $import) }}">@csrf<button class="btn btn-primary">Publish</button></form>
    @endif
  </div>

  <div class="card">
    <div class="card-header">Summary</div>
    <div class="card-body">
      <div id="items">Loading...</div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(async function(){
  const res = await fetch("{{ route('admin.imports.items', $import) }}?status=error&per_page=10");
  const j = await res.json();
  const el = document.getElementById('items');
  if (!j.total) { el.textContent = 'No errors.'; return; }
  const list = document.createElement('ul');
  (j.data||[]).forEach(it=>{
    const li = document.createElement('li');
    li.textContent = 'Row '+it.row_index+': '+(it.errors_json||[]).join(', ');
    list.appendChild(li);
  });
  el.innerHTML=''; el.appendChild(list);
})();
</script>
@endpush
@endsection
