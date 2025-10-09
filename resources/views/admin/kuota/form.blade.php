{{-- resources/views/admin/kuota/form.blade.php --}}
@extends('layouts.admin')

@section('title', 'Form Kuota')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.quotas.index') }}">Manajemen Kuota</a></li>
    <li class="breadcrumb-item active">Form</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit me-2"></i>Form Kuota Impor
                </h3>
            </div>
            <form action="{{ $quota->exists ? route('admin.quotas.update', $quota) : route('admin.quotas.store') }}" method="POST">
                @csrf
                @if($quota->exists)
                    @method('PUT')
                @endif
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="quota_number" class="form-label">Nomor Kuota <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="quota_number" 
                               name="quota_number" 
                               value="{{ old('quota_number', $quota->quota_number) }}"
                               placeholder="Contoh: KTA-2025-001"
                               required>
                        <small class="form-text text-muted">Format: KTA-YYYY-XXX</small>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Kuota <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="name"
                               name="name"
                               value="{{ old('name', $quota->name) }}"
                               placeholder="Contoh: Kuota Pemerintah 0.5 PK - 2 PK"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="government_category" class="form-label">Kategori Pemerintah <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="government_category"
                               name="government_category"
                               value="{{ old('government_category', $quota->government_category) }}"
                               placeholder="Contoh: AC 0.5 PK - 2 PK"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="total_allocation" class="form-label">Quantity Pemerintah <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="total_allocation" 
                               name="total_allocation" 
                               value="{{ old('total_allocation', $quota->total_allocation) }}"
                               placeholder="Contoh: 100000"
                               min="1"
                               required>
                        <small class="form-text text-muted">Jumlah unit yang disetujui pemerintah</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_start" class="form-label">Periode Mulai</label>
                                <input type="text" 
                                       class="form-control datepicker" 
                                       id="period_start" 
                                       name="period_start" 
                                       value="{{ old('period_start', optional($quota->period_start)->format('Y-m-d')) }}"
                                       placeholder="YYYY-MM-DD">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_end" class="form-label">Periode Selesai</label>
                                <input type="text" 
                                       class="form-control datepicker" 
                                       id="period_end" 
                                       name="period_end" 
                                       value="{{ old('period_end', optional($quota->period_end)->format('Y-m-d')) }}"
                                       placeholder="YYYY-MM-DD">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="forecast_remaining" class="form-label">Forecast Remaining</label>
                                <input type="number" class="form-control" id="forecast_remaining" name="forecast_remaining" value="{{ old('forecast_remaining', $quota->forecast_remaining) }}" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="actual_remaining" class="form-label">Actual Remaining</label>
                                <input type="number" class="form-control" id="actual_remaining" name="actual_remaining" value="{{ old('actual_remaining', $quota->actual_remaining) }}" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            @foreach([
                                \App\Models\Quota::STATUS_AVAILABLE => 'Tersedia',
                                \App\Models\Quota::STATUS_LIMITED => 'Hampir Habis',
                                \App\Models\Quota::STATUS_DEPLETED => 'Habis'
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $quota->status ?? \App\Models\Quota::STATUS_AVAILABLE) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="source_document" class="form-label">Dokumen Sumber</label>
                        <input type="text" class="form-control" id="source_document" name="source_document" value="{{ old('source_document', $quota->source_document) }}" placeholder="Contoh: SK Menteri ...">
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Keterangan</label>
                        <textarea class="form-control" 
                                  id="notes" 
                                  name="notes" 
                                  rows="3"
                                  placeholder="Catatan tambahan (opsional)">{{ old('notes', $quota->notes) }}</textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $quota->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Kuota Aktif</label>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                    <a href="{{ route('admin.quotas.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle me-2"></i>Panduan Pengisian
                </h3>
            </div>
            <div class="card-body">
                <h6><strong>Nomor Kuota</strong></h6>
                <p class="text-muted small">Nomor unik kuota. Format: KTA-YYYY-XXX (XXX adalah nomor urut 3 digit)</p>

                <h6 class="mt-3"><strong>Produk</strong></h6>
                <p class="text-muted small">Pilih produk yang akan diberikan kuota impor.</p>

                <h6 class="mt-3"><strong>Quantity Pemerintah</strong></h6>
                <p class="text-muted small">Jumlah maksimal unit yang diizinkan pemerintah untuk diimpor dalam periode tertentu.</p>

                <h6 class="mt-3"><strong>Periode</strong></h6>
                <p class="text-muted small">Rentang waktu berlakunya kuota. Pastikan periode selesai lebih besar dari periode mulai.</p>

                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <small><strong>Perhatian:</strong> Setelah kuota dibuat, quantity pemerintah tidak dapat diubah. Pastikan data sudah benar.</small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calculator me-2"></i>Kalkulator Kuota
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small">Qty Pemerintah:</label>
                    <input type="number" class="form-control form-control-sm" id="calc_qty" value="500">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Forecast (%):</label>
                    <input type="number" class="form-control form-control-sm" id="calc_forecast" value="90">
                </div>
                <button type="button" class="btn btn-sm btn-primary w-100" onclick="calculateForecast()">
                    <i class="fas fa-calculator me-1"></i>Hitung
                </button>
                <div class="mt-3 p-2 bg-light rounded" id="calc_result" style="display: none;">
                    <small class="text-muted">Forecast Quantity:</small>
                    <h5 class="mb-0 text-primary" id="forecast_result">0</h5>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            allowInput: true
        });

        $('#total_allocation, #forecast_remaining').on('input', function() {
            const total = parseInt($('#total_allocation').val()) || 0;
            const forecast = parseInt($('#forecast_remaining').val()) || 0;
            const used = Math.max(0, total - forecast);
            $('#calc_result').slideDown();
            $('#forecast_result').text(`${used.toLocaleString()} unit terpakai (forecast)`);
        });
    });
</script>
@endpush
