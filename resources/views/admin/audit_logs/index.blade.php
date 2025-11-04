@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
@php use Illuminate\Support\Carbon; use Illuminate\Support\Str; @endphp
<style>
    .audit-page .page-header h1 { font-weight: 600; }
    .audit-page .quick-range .btn { border-radius: 999px; padding: 6px 12px; }
    .audit-page .card { border: 1px solid var(--stroke); box-shadow: 0 8px 24px rgba(15,23,42,.06); }
    .audit-page .filter-card .form-label { font-size: 12px; color: var(--muted); }
    .audit-page .table-modern thead th { background: var(--surface); font-size: 12px; text-transform: uppercase; color: var(--muted); letter-spacing: .02em; padding: 10px 14px; }
    .audit-page .table-modern tbody tr:hover { background: rgba(37,99,235,.04); }
    .audit-page .table-modern tbody td { padding: 12px 16px; vertical-align: middle; }
    .audit-page .table-modern .cell-time { white-space: nowrap; }
    .audit-page code { background: rgba(15,23,42,.04); border-radius: 6px; padding: 2px 6px; }
    .badge-action { border-radius: 999px; font-weight: 600; padding: .35rem .6rem; }
    .badge-action.create { background: rgba(16,185,129,.15); color: #047857; }
    .badge-action.update { background: rgba(37,99,235,.15); color: #1e3a8a; }
    .badge-action.delete { background: rgba(239,68,68,.15); color: #991b1b; }
        .badge-action.login { background: rgba(59,130,246,.15); color: #1e40af; }
        .badge-action.logout { background: rgba(107,114,128,.20); color: #374151; }
    .audit-empty { padding: 36px; text-align: center; color: var(--muted); }
    .audit-empty i { color: #9aa5b1; }
</style>
<div class="container-fluid audit-page">
    <div class="d-flex align-items-center justify-content-between mb-3 page-header">
        <div>
            <h1 class="h3 mb-1">Audit Logs</h1>
            <div class="text-muted" style="font-size: 12px;">Catatan perubahan data oleh pengguna</div>
        </div>
    </div>

    <div class="card mb-4 filter-card">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-4">
                    <label class="form-label">Pengguna</label>
                    <select name="user_id" class="form-select select2" data-placeholder="Semua pengguna">
                        <option value="">Semua</option>
                        @foreach($users as $id => $name)
                            <option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label">Jenis Aksi</label>
                    <select name="method" class="form-select">
                        <option value="">Semua</option>
                        <option value="login" @selected(request('method')==='login')>Login</option>
                        <option value="logout" @selected(request('method')==='logout')>Logout</option>
                        <option value="create" @selected(request('method')==='create')>Menambah Data</option>
                        <option value="update" @selected(request('method')==='update')>Mengubah Data</option>
                        <option value="delete" @selected(request('method')==='delete')>Menghapus Data</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label">Periode</label>
                    <select name="range" class="form-select" id="rangeSelect">
                        <option value="" @selected(request('range')==='')>Semua</option>
                        <option value="today" @selected(request('range')==='today')>Hari Ini</option>
                        <option value="2d" @selected(request('range')==='2d')>2 Hari</option>
                        <option value="7d" @selected(request('range')==='7d')>7 Hari</option>
                        <option value="3d" @selected(request('range')==='3d')>3 Hari</option>
                        <option value="30d" @selected(request('range')==='30d')>30 Hari</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control" placeholder="dd/mm/yyyy" id="fromInput">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control" placeholder="dd/mm/yyyy" id="toInput">
                </div>
                <div class="col-xl-5 col-md-6">
                    <label class="form-label">Cari Kata Kunci</label>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari pengguna, fitur, halaman, IP...">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label">Per Halaman</label>
                    <select name="per_page" class="form-select">
                        @foreach([10,25,50,100] as $pp)
                            <option value="{{ $pp }}" @selected((int)request('per_page', 25) === $pp)>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex align-items-end">
                    <div class="d-flex gap-2 me-auto">
                        <button class="btn btn-primary" type="submit">Terapkan</button>
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                    <div class="dropdown ms-auto">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                            <li><a class="dropdown-item" href="{{ route('admin.audit-logs.export', request()->query()) }}"><i class="fas fa-file-csv me-2"></i> CSV</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.audit-logs.export.xlsx', request()->query()) }}"><i class="fas fa-file-excel me-2"></i> XLSX</a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Halaman</th>
                        <th>Fitur</th>
                        <th>IP</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="cell-time text-nowrap">
                            <div>{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
                            <div class="text-muted" style="font-size: 12px;">{{ $log->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="text-nowrap">{{ optional($log->user)->name ?? 'Guest' }}</td>
                        <td>
                            @php
                                $pathLower = strtolower((string) $log->path);
                                $isLogin  = str_starts_with($pathLower, '/login');
                                $isLogout = str_starts_with($pathLower, '/logout');

                                if ($isLogin) {
                                    $label = 'Login';
                                    $cls = 'login';
                                } elseif ($isLogout) {
                                    $label = 'Logout';
                                    $cls = 'logout';
                                } else {
                                    $label = match($log->method) {
                                        'POST' => 'Menambah',
                                        'PUT', 'PATCH' => 'Mengubah',
                                        'DELETE' => 'Menghapus',
                                        default => $log->method,
                                    };
                                    $cls = match($label) {
                                        'Menambah' => 'create',
                                        'Mengubah' => 'update',
                                        'Menghapus' => 'delete',
                                        default => 'update',
                                    };
                                }
                            @endphp
                            <span class="badge-action {{ $cls }}">{{ $label }}</span>
                        </td>
                        <td class="text-nowrap" title="{{ $log->path }}">{{ audit_page_label($log->route_name, $log->path) }}</td>
                        <td class="text-nowrap" title="{{ $log->route_name }}">{{ audit_activity_label($log->route_name, $log->method, $log->description) }}</td>
                        <td class="text-nowrap">{{ $log->ip_address }}</td>
                        <td style="max-width: 480px;">
                            @php
                                $data = is_array($log->description) ? $log->description : (array) $log->description;
                                $hideKeys = ['_token','password','password_confirmation','current_password'];
                                $nice = [];
                                foreach ($data as $k => $v) {
                                    if (in_array($k, $hideKeys, true)) continue;

                                    $label = match ($k) {
                                        'file', 'files' => 'Berkas',
                                        'name' => 'Nama',
                                        'email' => 'Email',
                                        'note', 'notes' => 'Catatan',
                                        default => ucfirst(str_replace(['_', '-'], ' ', (string) $k)),
                                    };

                                    $value = $v;
                                    if (is_string($v) && str_starts_with($v, 'uploaded_file:')) {
                                        $value = 'Berkas diunggah: ' . substr($v, strlen('uploaded_file:'));
                                    } elseif (is_array($v)) {
                                        // ringkas array sederhana menjadi daftar koma
                                        $flat = [];
                                        foreach ($v as $vv) {
                                            if (is_scalar($vv)) { $flat[] = (string) $vv; }
                                        }
                                        $value = count($flat) ? implode(', ', $flat) : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                                    } elseif (is_bool($v)) {
                                        $value = $v ? 'Ya' : 'Tidak';
                                    }

                                    if ($value === '' || $value === null) continue;
                                    $nice[] = [$label, $value];
                                }
                            @endphp
                            @if(empty($nice))
                                <span class="text-muted">Tidak ada detail</span>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#logModal-{{ $log->id }}">Lihat</button>
                                <div class="modal fade" id="logModal-{{ $log->id }}" tabindex="-1" aria-hidden="true">
                                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title">Detail Aksi</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="text-muted small">Pengguna</div>
                                                <div>{{ optional($log->user)->name ?? 'Guest' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small">Waktu</div>
                                                <div>{{ $log->created_at->format('Y-m-d H:i:s') }} ({{ $log->created_at->diffForHumans() }})</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small">Halaman</div>
                                                <div><code>{{ $log->path }}</code></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small">IP & Perangkat</div>
                                                <div>{{ $log->ip_address }} — <span class="text-muted">{{ Str::limit($log->user_agent, 80) }}</span></div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="mb-2 fw-semibold">Data Dikirim</div>
                                        <ul class="mb-0 small" style="padding-left: 18px;">
                                            @foreach($nice as [$label,$val])
                                                <li><strong>{{ $label }}</strong>: {{ $val }}</li>
                                            @endforeach
                                        </ul>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="audit-empty">
                                <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                                <div class="mb-1">Belum ada aktivitas yang tercatat.</div>
                                <div class="small">Lakukan aksi menambah/mengubah/menghapus data kemudian kembali ke halaman ini.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Menampilkan {{ $logs->firstItem() }}–{{ $logs->lastItem() }} dari {{ $logs->total() }} data
            </div>
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection


<script>
(function(){
  const range = document.getElementById('rangeSelect');
  const fromI = document.getElementById('fromInput');
  const toI = document.getElementById('toInput');
  if(!range || !fromI || !toI) return;
  function fmt(d){ const p=n=>String(n).padStart(2,'0'); return d.getFullYear()+"-"+p(d.getMonth()+1)+"-"+p(d.getDate()); }
  function applyRange(){
    const v = range.value; const t = new Date(); let from=null, to=null;
    if(v==='today'){ from=t; to=t; }
    else if(v==='2d'){ const f=new Date(t); f.setDate(t.getDate()-1); from=f; to=t; }
    else if(v==='3d'){ const f=new Date(t); f.setDate(t.getDate()-2); from=f; to=t; }
    else if(v==='7d'){ const f=new Date(t); f.setDate(t.getDate()-6); from=f; to=t; }
    else if(v==='30d'){ const f=new Date(t); f.setDate(t.getDate()-29); from=f; to=t; }
    if(from && to){ fromI.value = fmt(from); toI.value = fmt(to); }
    // keep inputs always editable
    fromI.disabled=false; toI.disabled=false;
  }
  range.addEventListener('change', applyRange);
})();
</script>


