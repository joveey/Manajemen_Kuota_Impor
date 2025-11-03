@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Audit Logs</h1>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Pengguna</label>
                    <select name="user_id" class="form-select">
                        <option value="">All</option>
                        @foreach($users as $id => $name)
                            <option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis Aksi</label>
                    <select name="method" class="form-select">
                        <option value="">Semua</option>
                        <option value="create" @selected(request('method')==='create')>Menambah Data</option>
                        <option value="update" @selected(request('method')==='update')>Mengubah Data</option>
                        <option value="delete" @selected(request('method')==='delete')>Menghapus Data</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control" placeholder="dd/mm/yyyy">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control" placeholder="dd/mm/yyyy">
                </div>
                <div class="col-md-3 align-self-end">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
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
                        <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="text-nowrap">{{ optional($log->user)->name ?? 'Guest' }}</td>
                        <td>
                            @php
                                $label = match($log->method) {
                                    'POST' => 'Menambah',
                                    'PUT', 'PATCH' => 'Mengubah',
                                    'DELETE' => 'Menghapus',
                                    default => $log->method,
                                };
                            @endphp
                            <span class="badge bg-dark">{{ $label }}</span>
                        </td>
                        <td class="text-nowrap">{{ $log->path }}</td>
                        <td class="text-nowrap">{{ $log->route_name }}</td>
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
                                <ul class="mb-0 small" style="padding-left: 18px;">
                                    @foreach($nice as [$label,$val])
                                        <li><strong>{{ $label }}</strong>: {{ $val }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No logs found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
