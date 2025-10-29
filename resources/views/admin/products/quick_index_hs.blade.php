{{-- resources/views/admin/products/quick_index_hs.blade.php --}}
@extends('layouts.admin')

@section('title', 'Tambah Model > HS')
@section('page-title', 'Tambah Model > HS')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Tambah Model > HS</li>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('modelhs-upload');
    const file = document.getElementById('modelhs-file');
    const fileHelp = document.getElementById('modelhs-file-help');

    function validate() {
        if (!file) { return true; }
        file.classList.remove('is-invalid');
        fileHelp.textContent = '';

        if (!file.files || file.files.length === 0) {
            file.classList.add('is-invalid');
            fileHelp.textContent = 'File wajib diunggah (.xlsx/.xls/.csv, maks 10MB).';
            return false;
        }

        const name = file.files[0].name.toLowerCase();
        if (!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv')) {
            file.classList.add('is-invalid');
            fileHelp.textContent = 'Tipe file harus .xlsx, .xls, atau .csv.';
            return false;
        }

        return true;
    }

    form?.addEventListener('submit', function (event) {
        if (!validate()) {
            event.preventDefault();
            event.stopPropagation();
            file?.focus();
        }
    });
})();
</script>
@endpush

@section('content')
@php
    $canCreate = auth()->user()?->can('product.create');
@endphp

<div class="container-fluid px-0">
    <div class="mb-4">
        <h1 class="h4 mb-2">Tambah Model &gt; HS</h1>
        <p class="text-muted mb-0">
            Unggah referensi Model &gt; HS terbaru atau gunakan form manual untuk update cepat.
        </p>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row gy-3">
        <div class="col-xl-8">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Upload Model &gt; HS</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Format minimal: kolom <code>MODEL</code> dan <code>HS_CODE</code>. HS harus sudah punya PK pada master HS &rarr; PK. Data akan divalidasi sebelum publish.
                    </p>

                    @if($errors->has('file'))
                        <div class="alert alert-danger">
                            {{ $errors->first('file') }}
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('admin.mapping.model_hs.upload') }}"
                          enctype="multipart/form-data"
                          id="modelhs-upload"
                          novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="modelhs-file" class="form-label">File Excel/CSV</label>
                            <input type="file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   id="modelhs-file"
                                   name="file"
                                   accept=".xlsx,.xls,.csv"
                                   required>
                            <div class="invalid-feedback" id="modelhs-file-help" aria-live="polite"></div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Upload &amp; Preview
                        </button>
                    </form>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <small class="text-muted">
                        Aturan: tidak menimpa HS yang sudah terisi pada produk, dan model baru dapat dibuat saat publish bila diizinkan.
                    </small>
                    @if($canCreate)
                        <a href="{{ route('admin.master.quick_hs.create', ['return' => route('admin.master.quick_hs.index')]) }}"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-pen-to-square me-1"></i> Input Manual Model &gt; HS
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Aktivitas Terakhir</span>
                    <a href="{{ route('admin.mapping.mapped.page') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-table-list me-1"></i> Lihat Mapping
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Model/SKU</th>
                                    <th>HS Code</th>
                                    <th>PK</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recent as $product)
                                    <tr>
                                        <td>{{ $product->code }}</td>
                                        <td>{{ $product->hs_code ?? '-' }}</td>
                                        <td>
                                            @if($product->pk_capacity === null)
                                                -
                                            @else
                                                {{ rtrim(rtrim(number_format((float) $product->pk_capacity, 2), '0'), '.') }}
                                            @endif
                                        </td>
                                        <td>{{ optional($product->updated_at)->format('d M Y H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada model dengan HS Code.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    Menampilkan {{ $recent->count() }} model terakhir diperbarui.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
