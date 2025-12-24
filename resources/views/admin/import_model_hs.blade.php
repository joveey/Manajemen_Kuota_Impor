@extends('layouts.admin')

@section('title', 'Import Model → HS Code')

@section('content')
<div class="container-fluid px-0">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Import Model → HS Code</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.imports.model_hs.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">File Excel / CSV</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <small class="form-text text-muted">Kolom wajib: Model, HS. Period opsional.</small>
                    @error('file')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i>Upload &amp; Import
                </button>
            </form>
        </div>
    </div>

    @if(!empty($summary))
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <strong>Ringkasan Hasil</strong>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="fw-bold">Total Dibaca</div>
                        <div class="fs-4">{{ number_format($summary['total_rows'] ?? 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-bold text-success">Inserted</div>
                        <div class="fs-4 text-success">{{ number_format($summary['inserted'] ?? 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-bold text-info">Updated</div>
                        <div class="fs-4 text-info">{{ number_format($summary['updated'] ?? 0) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-bold text-danger">Skipped</div>
                        <div class="fs-4 text-danger">{{ number_format($summary['skipped'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong>Daftar Error (maks 20)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Row</th>
                            <th>Pesan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($importErrors ?? [] as $error)
                            <tr>
                                <td>{{ $error['row'] ?? '-' }}</td>
                                <td>{{ $error['message'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted">Tidak ada error.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
