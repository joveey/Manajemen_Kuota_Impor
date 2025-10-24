<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Aktivitas (7 hari)</strong>
    <a href="{{ route('admin.imports.quotas.index') }}" class="btn btn-sm btn-outline-secondary">Riwayat Import</a>
  </div>
  <div class="card-body">
    <ul class="list-unstyled mb-0">
      @forelse($activities as $a)
        <li class="mb-2">
          <span class="badge bg-primary me-2">{{ strtoupper($a['type']) }}</span>
          <strong>{{ $a['title'] }}</strong>
          <span class="text-muted small">— {{ $a['time'] }}</span>
        </li>
      @empty
        <li class="text-muted">Tidak ada aktivitas.</li>
      @endforelse
    </ul>
  </div>
  @if(!empty($alerts))
  <div class="card-footer">
    <strong>Alert</strong>
    <ul class="mb-0">
      @foreach($alerts as $msg)
        <li class="text-danger small">{{ $msg }}</li>
      @endforeach
    </ul>
  </div>
  @endif
</div>

