{{-- resources/views/admin/imports/hs_pk/index.blade.php (manual only) --}}
@extends('layouts.admin')

@section('title', 'Input HS & PK')
@section('page-title', 'Input HS & PK')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Input HS & PK</li>
@endsection

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Input HS → PK</h1>
            <p class="page-header__subtitle"></p>
        </div>
    </div>

    <div class="container-fluid px-0">
        <div class="row gy-3">
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Form Input HS → PK</div>
                    <div class="card-body">
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

                        <form action="{{ route('admin.hs_pk.manual.store') }}" method="POST" class="row g-3" novalidate>
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label" for="period_key">Periode (YYYY)</label>
                                <input type="text" name="period_key" id="period_key" class="form-control" placeholder="YYYY" inputmode="numeric" maxlength="4" value="{{ old('period_key') }}" title="Masukkan 4 digit tahun, mis. 2025">
                                <small class="text-muted">Kosongkan untuk legacy.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="hs_code">HS_CODE</label>
                                <input type="text" name="hs_code" id="hs_code" class="form-control" placeholder="contoh: 0101.21.00" value="{{ old('hs_code') }}" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="pk_value">DESC</label>
                                <input type="text" name="pk_value" id="pk_value" class="form-control" placeholder="8-10, <8, >10, atau 8" value="{{ old('pk_value') }}" required>
                                <small class="text-muted">Gunakan format: 8-10, &lt;8, &gt;10, atau angka tunggal.</small>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-3 shadow-sm">
                    <div class="card-header fw-semibold">Format yang dibutuhkan</div>
                    <div class="card-body">
                        <ul class="mb-2">
                            <li>Input kolom: <code>Periode (YYYY)</code>, <code>HS_CODE</code>, <code>DESC</code>.</li>
                            <li>Aturan parsing <code>DESC</code> (kategori PK): gunakan <code>8-10</code>, <code>&lt;8</code>, <code>&gt;10</code>, atau angka tunggal.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-5" id="mapping-list">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                        <span>Daftar Mapping</span>
                        <form method="GET" action="{{ route('admin.imports.hs_pk.index') }}" class="d-flex align-items-center" role="search">
                          <input type="text" class="form-control form-control-sm me-2" name="period_key" placeholder="Filter tahun (YYYY)" value="{{ $period ?? '' }}" style="width: 160px;">
                          <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table mb-0">
                            <thead class="table-light">
                              <tr>
                                <th>HS Code</th>
                                <th>Periode</th>
                                <th>DESC</th>
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
                                  <td colspan="4" class="text-center text-muted py-4">Tidak ada data.</td>
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
