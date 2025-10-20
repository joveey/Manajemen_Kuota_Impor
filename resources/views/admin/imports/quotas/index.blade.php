{{-- resources/views/admin/imports/quotas/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Import Kuota')
@section('page-title', 'Import Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Import Kuota</li>
@endsection

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Import Kuota</h1>
            <p class="page-header__subtitle">Unggah referensi kuota terbaru dan tinjau riwayat impor periode sebelumnya.</p>
        </div>
    </div>

    <div class="container-fluid px-0">
        <div class="row gy-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Upload Kuota</div>
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        <form action="{{ route('admin.imports.quotas.upload.form') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Periode</label>
                                <input type="text" name="period_key" class="form-control" placeholder="YYYY atau YYYY-MM" value="{{ old('period_key') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Excel (sheet: Quota master)</label>
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-upload me-2"></i>Upload
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Riwayat Import Kuota</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Periode</th>
                                        <th>Status</th>
                                        <th>Ringkasan</th>
                                        <th>Dibuat</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recent as $imp)
                                        <tr>
                                            <td>#{{ $imp->id }}</td>
                                            <td>{{ $imp->period_key }}</td>
                                            <td>{{ ucfirst(str_replace('_',' ', $imp->status)) }}</td>
                                            <td>{{ (int)($imp->valid_rows ?? 0) }} / {{ (int)($imp->total_rows ?? 0) }} (err {{ (int)($imp->error_rows ?? 0) }})</td>
                                            <td>{{ optional($imp->created_at)->format('d M Y H:i') ?? '-' }}</td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.imports.quotas.preview', $imp) }}">
                                                    Preview
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Belum ada data import.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
