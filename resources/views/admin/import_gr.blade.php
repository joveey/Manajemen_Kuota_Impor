@extends('layouts.admin')

@section('title', 'Import GR (Goods Receipt)')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Import Goods Receipt (Excel)</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Unggah file Excel (.xlsx/.xls) dengan sheet GR (kolom minimal: PO No, Line No, Receive Date, Qty, Cat PO).</p>
                    <form method="POST" action="{{ route('admin.imports.gr.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label for="file">File Excel</label>
                            <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload mr-1"></i> Upload &amp; Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php $importResult = session('import_result'); @endphp
    @if(!empty($importResult))
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Ringkasan Hasil</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between"><span>Total row dibaca</span><strong>{{ number_format($importResult['total_rows'] ?? 0) }}</strong></li>
                            <li class="list-group-item d-flex justify-content-between text-success"><span>Inserted</span><strong>{{ number_format($importResult['inserted'] ?? 0) }}</strong></li>
                            <li class="list-group-item d-flex justify-content-between text-info"><span>Updated</span><strong>{{ number_format($importResult['updated'] ?? 0) }}</strong></li>
                            <li class="list-group-item d-flex justify-content-between text-danger"><span>Skipped / Error</span><strong>{{ number_format($importResult['skipped'] ?? 0) }}</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
            @if(!empty($importResult['errors']))
                <div class="col-md-6">
                    <div class="card card-danger card-outline">
                        <div class="card-header"><h3 class="card-title">Daftar Error (maks 20 baris)</h3></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead><tr><th style="width:90px">Row</th><th>Pesan</th></tr></thead>
                                    <tbody>
                                        @foreach($importResult['errors'] as $err)
                                            <tr>
                                                <td>{{ $err['row'] ?? '-' }}</td>
                                                <td>{{ $err['message'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
