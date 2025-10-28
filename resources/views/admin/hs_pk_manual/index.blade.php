@extends('layouts.admin')

@section('title', 'HS → PK Manual')
@section('page-title', 'HS → PK Manual')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.imports.hs_pk.index') }}">Import HS & PK</a></li>
    <li class="breadcrumb-item active">Manual</li>
@endsection

@section('content')
<div class="page-shell">
  <div class="page-header">
    <div>
      <h1 class="page-header__title">Input Manual HS → PK</h1>
      <p class="page-header__subtitle">Tambah mapping HS ke PK per tahun. Kosongkan periode untuk mapping legacy.</p>
    </div>
  </div>

  <div class="container-fluid px-0">
    <div class="row gy-3">
      <div class="col-md-5">
        <div class="card shadow-sm">
          <div class="card-header fw-semibold">Tambah Mapping</div>
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

            <form method="POST" action="{{ route('admin.hs_pk.manual.store') }}" class="row g-3">
              @csrf
              <div class="col-12">
                <label for="hs_code" class="form-label">HS Code</label>
                <input type="text" class="form-control" id="hs_code" name="hs_code" placeholder="(contoh : 123.1242.0)" value="{{ old('hs_code') }}">
                <small class="text-muted">Isikan ACC untuk Accsesories</small>
              </div>
              <div class="col-6">
                <label for="pk_capacity" class="form-label">PK</label>
                <input type="number" step="0.01" min="0" class="form-control" id="pk_capacity" name="pk_capacity" placeholder="mis. 8.5" required value="{{ old('pk_capacity') }}">
              </div>
              <div class="col-6">
                <label for="period_key" class="form-label">Periode</label>
                <input type="text" class="form-control" id="period_key" name="period_key" pattern="^\\d{4}$" placeholder="YYYY" value="{{ old('period_key') }}">
                <small class="text-muted">Kosongkan untuk legacy</small>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.imports.hs_pk.index') }}" class="btn btn-outline-secondary ms-2">Kembali</a>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="card shadow-sm">
          <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Daftar Mapping</span>
            <form method="GET" action="{{ route('admin.hs_pk.manual.index') }}" class="d-flex align-items-center" role="search">
              <input type="text" class="form-control form-control-sm me-2" name="period_key" placeholder="Filter tahun (YYYY)" value="{{ $period }}" style="width: 160px;">
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
                    <th>PK</th>
                    <th>Updated</th>
                  </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                  <tr>
                    <td>{{ $r->hs_code }}</td>
                    <td>{{ $r->period_key === '' ? 'Legacy' : $r->period_key }}</td>
                    <td>{{ number_format((float)$r->pk_capacity, 2) }}</td>
                    <td>{{ optional($r->updated_at)->format('Y-m-d H:i') }}</td>
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
              {{ $rows->links() }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

