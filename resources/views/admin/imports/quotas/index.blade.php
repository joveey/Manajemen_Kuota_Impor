{{-- resources/views/admin/imports/quotas/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Import Kuota')
@section('page-title', 'Import Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Import Kuota</li>
@endsection

@push('scripts')
<script>
(function(){
  const form = document.getElementById('quota-upload-form');
  const period = document.getElementById('period_key');
  const periodHelp = document.getElementById('period_key_help');
  const file = document.getElementById('file');
  const fileHelp = document.getElementById('file_help');
  const backendDismiss = document.getElementById('dismiss-backend-error');

  backendDismiss?.addEventListener('click', function(){
    period?.focus();
  });

  function validate() {
    let ok = true;
    period.classList.remove('is-invalid');
    file.classList.remove('is-invalid');
    periodHelp.textContent='';
    fileHelp.textContent='';

    const periodVal = (period.value||'').trim();
    const re = /^\d{4}(-\d{2}(-\d{2})?)?$/;
    if (!re.test(periodVal)) {
      period.classList.add('is-invalid');
      periodHelp.textContent = 'Gunakan format YYYY, YYYY-MM, atau YYYY-MM-DD.';
      ok = false;
    }
    if (!file.files || file.files.length === 0) {
      file.classList.add('is-invalid');
      fileHelp.textContent = 'File wajib diunggah (.xlsx, .xls, atau .csv).';
      ok = false;
    } else {
      const name = file.files[0].name.toLowerCase();
      if (!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv')) {
        file.classList.add('is-invalid');
        fileHelp.textContent = 'Tipe file harus .xlsx, .xls, atau .csv.';
        ok = false;
      }
    }
    return ok;
  }

  form?.addEventListener('submit', function(e){
    if (!validate()) {
      e.preventDefault();
      e.stopPropagation();
      period.focus();
    }
  });

  // Download Contoh (CSV) + modal-less
  document.getElementById('btn-template-quota')?.addEventListener('click', function(){
    const csv = [
      'LETTER_NO,CATEGORY_LABEL,ALLOCATION,PERIOD_START,PERIOD_END',
      'S-001,8-10,1200,2025-01-01,2025-12-31',
      'S-002,<8,500,2025-01-01,2025-06-30',
      'S-003,>10,750,2025-07-01,2025-12-31'
    ].join('\n');
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'contoh_quota.csv'; a.click();
    URL.revokeObjectURL(url);
  });
})();
</script>
@endpush

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
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                        <span>Upload Kuota</span>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-template-quota">Download Contoh</button>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="dismiss-backend-error"></button>
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form action="{{ route('admin.imports.quotas.upload.form') }}" method="POST" enctype="multipart/form-data" id="quota-upload-form" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="period_key">Periode</label>
                                <input type="text" name="period_key" id="period_key" class="form-control" placeholder="YYYY atau YYYY-MM" value="{{ old('period_key') }}" required pattern="^\d{4}(-\d{2}(-\d{2})?)?$">
                                <div class="invalid-feedback" id="period_key_help" aria-live="polite"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="file">File Excel/CSV (untuk Excel: sheet "Quota master")</label>
                                <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                                <div class="invalid-feedback" id="file_help" aria-live="polite"></div>
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-upload me-2"></i>Upload
                            </button>
                        </form>

                        <hr>
                        <div class="accordion" id="quotaFormatAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingFormat">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFormat" aria-expanded="false" aria-controls="collapseFormat">
                                        Format yang dibutuhkan
                                    </button>
                                </h2>
                                <div id="collapseFormat" class="accordion-collapse collapse" aria-labelledby="headingFormat" data-bs-parent="#quotaFormatAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-2">
                                            <li>Sheet harus bernama <code>Quota master</code>.</li>
                                            <li>Header wajib: <code>LETTER_NO</code>, <code>CATEGORY_LABEL</code>, <code>ALLOCATION</code>. Opsional: <code>PERIOD_START</code>, <code>PERIOD_END</code>.</li>
                                            <li><strong>CATEGORY_LABEL</strong>: gunakan salah satu pola <code>8-10</code>, <code>&lt;8</code>, <code>&gt;10</code>, atau angka tunggal. Teks <em>PK</em> diabaikan. Tidak mendukung simbol ≤, ≥, atau "s/d".</li>
                                            <li><strong>ALLOCATION</strong> harus bilangan bulat &gt; 0 (pemisah ribuan boleh, desimal tidak).</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        
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
