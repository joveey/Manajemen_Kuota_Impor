@extends('layouts.admin')

@section('title', 'Import Purchase Orders')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Import Purchase Orders (Excel)</h3>
                </div>
                <div class="card-body">
                    <p class="mb-3 text-muted">
                        Unggah file Excel (.xlsx) dengan struktur sama seperti <strong>Sheet "List PO"</strong>.
                        Data akan diproses di server dan masuk ke tabel <code>purchase_orders</code>.
                    </p>
                    <form method="POST" action="{{ route('admin.purchase-orders.import.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label for="file">File Excel</label>
                            <input type="file"
                                   name="file"
                                   id="file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   accept=".xlsx"
                                   required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Gunakan template "PO import system upd.xlsx" &rarr; sheet <em>List PO</em>. Wajib format .xlsx.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload mr-1"></i> Upload &amp; Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $importResult = $importResult ?? session('import_result');
    @endphp

    @if(!empty($importResult))
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ringkasan Hasil</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total row dibaca</span>
                                <strong>{{ number_format($importResult['total_rows'] ?? 0) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between text-success">
                                <span>Inserted</span>
                                <strong>{{ number_format($importResult['inserted'] ?? 0) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between text-info">
                                <span>Updated</span>
                                <strong>{{ number_format($importResult['updated'] ?? 0) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between text-danger">
                                <span>Skipped / Error</span>
                                <strong>{{ number_format($importResult['skipped'] ?? 0) }}</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            @if(!empty($importResult['errors']))
                <div class="col-md-6">
                    <div class="card card-danger card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Error</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:90px;">Row</th>
                                            <th>Pesan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($importResult['errors'] as $error)
                                            <tr>
                                                <td>{{ $error['row'] ?? '-' }}</td>
                                                <td>{{ $error['message'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-3 border-top">
                                <pre class="small mb-0">{{ json_encode($importResult['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
