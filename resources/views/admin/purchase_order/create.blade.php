{{-- resources/views/admin/purchase_order/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Buat Purchase Order')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Purchase Order</a></li>
    <li class="breadcrumb-item active">Buat PO</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Form Purchase Order</h3>
            </div>
            <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="poForm">
                @csrf
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

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="sequence_number" class="form-label">Nomor Urut</label>
                            <input type="number" class="form-control" id="sequence_number" name="sequence_number" value="{{ old('sequence_number') }}" min="1" placeholder="Auto">
                        </div>
                        <div class="col-md-4">
                            <label for="period" class="form-label">Periode</label>
                            <input type="text" class="form-control" id="period" name="period" value="{{ old('period') }}" placeholder="YYYY-MM">
                        </div>
                        <div class="col-md-4">
                            <label for="order_date" class="form-label">Tanggal PO <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="order_date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="po_number" class="form-label">PO Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="po_number" name="po_number" value="{{ old('po_number') }}" placeholder="Contoh: PO100001" required>
                        </div>
                        <div class="col-md-6">
                            <label for="sap_reference" class="form-label">SAP Reference</label>
                            <input type="text" class="form-control" id="sap_reference" name="sap_reference" value="{{ old('sap_reference') }}" placeholder="Referensi SAP">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="product_id" class="form-label">Produk <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">-- Pilih Produk --</option>
                                @foreach($products as $product)
                                    @php
                                        $primaryMapping = $product->quotaMappings->sortBy('priority')->first();
                                        $quota = $primaryMapping?->quota;
                                    @endphp
                                    <option value="{{ $product->id }}"
                                        data-code="{{ $product->code }}"
                                        data-name="{{ $product->name }}"
                                        data-quota-number="{{ $quota->quota_number ?? '' }}"
                                        data-forecast="{{ $quota->forecast_remaining ?? 0 }}"
                                        data-actual="{{ $quota->actual_remaining ?? 0 }}"
                                        @selected(old('product_id') == $product->id)>
                                        {{ $product->code }} - {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity PO <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="{{ old('quantity') }}" min="1" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="pgi_branch" class="form-label">PGI Branch</label>
                            <input type="text" class="form-control" id="pgi_branch" name="pgi_branch" value="{{ old('pgi_branch') }}" placeholder="Contoh: PGI GREAT JKT 1">
                        </div>
                        <div class="col-md-4">
                            <label for="customer_name" class="form-label">Customer</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="pic_name" class="form-label">PIC</label>
                            <input type="text" class="form-control" id="pic_name" name="pic_name" value="{{ old('pic_name') }}">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="status_po_display" class="form-label">Status P/O</label>
                            <input type="text" class="form-control" id="status_po_display" name="status_po_display" value="{{ old('status_po_display', 'Released') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="truck" class="form-label">Truck</label>
                            <input type="text" class="form-control" id="truck" name="truck" value="{{ old('truck') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="moq" class="form-label">MOQ / Scheme</label>
                            <input type="text" class="form-control" id="moq" name="moq" value="{{ old('moq') }}">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Kategori</label>
                            <input type="text" class="form-control" id="category" name="category" value="{{ old('category') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="plant_name" class="form-label">Nama Pabrik <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="plant_name" name="plant_name" value="{{ old('plant_name', 'Panasonic Corp. Osaka Plant') }}" required>
                        </div>
                    </div>

                    <div class="mb-3 mt-1">
                        <label for="plant_detail" class="form-label">Detail Pabrik <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="plant_detail" name="plant_detail" rows="3" required>{{ old('plant_detail', '1-1 Matsushita-cho, Moriguchi, Osaka, Japan') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="remarks" class="form-label">Catatan</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Catatan tambahan">{{ old('remarks') }}</textarea>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan PO</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-database me-2"></i>Ringkasan Kuota</h3>
            </div>
            <div class="card-body">
                <div id="quota-empty" class="text-muted">
                    Pilih produk untuk melihat ringkasan quota.
                </div>
                <div id="quota-summary" style="display:none;">
                    <dl class="row mb-0">
                        <dt class="col-5">Produk</dt>
                        <dd class="col-7" id="summary-product">-</dd>
                        <dt class="col-5">Quota Aktif</dt>
                        <dd class="col-7" id="summary-quota">-</dd>
                        <dt class="col-5">Forecast Remaining</dt>
                        <dd class="col-7" id="summary-forecast">-</dd>
                        <dt class="col-5">Actual Remaining</dt>
                        <dd class="col-7" id="summary-actual">-</dd>
                    </dl>
                </div>
                <div class="alert alert-info mt-3" id="switch-alert" style="display:none;">
                    <i class="fas fa-random"></i> Sistem akan melakukan auto switch ke quota berikutnya jika quota utama tidak mencukupi.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-stream me-2"></i>History Proses</h3>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0" id="history-log">
                    <li class="small text-muted">Isi form untuk melihat tahapan flow.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const historyLog = document.getElementById('history-log');

    function logHistory(message) {
        const item = document.createElement('li');
        item.className = 'small text-muted mb-1';
        const timestamp = new Date().toLocaleTimeString();
        item.textContent = `[${timestamp}] ${message}`;
        historyLog.prepend(item);
    }

    function updateQuotaSummary(option) {
        if (!option || !option.value) {
            document.getElementById('quota-summary').style.display = 'none';
            document.getElementById('quota-empty').style.display = 'block';
            document.getElementById('switch-alert').style.display = 'none';
            return;
        }

        const productName = option.dataset.name;
        const productCode = option.dataset.code;
        const quotaNumber = option.dataset.quotaNumber;
        const forecast = option.dataset.forecast;
        const actual = option.dataset.actual;

        document.getElementById('summary-product').textContent = `${productCode} - ${productName}`;
        document.getElementById('summary-quota').textContent = quotaNumber || 'Belum dimapping';
        document.getElementById('summary-forecast').textContent = (forecast ? parseInt(forecast).toLocaleString() : '-') + ' unit';
        document.getElementById('summary-actual').textContent = (actual ? parseInt(actual).toLocaleString() : '-') + ' unit';

        document.getElementById('quota-empty').style.display = 'none';
        document.getElementById('quota-summary').style.display = 'block';
        document.getElementById('switch-alert').style.display = 'block';

        logHistory(`Identifikasi model ${productCode} -> quota ${quotaNumber || 'N/A'}`);
    }

    document.getElementById('product_id').addEventListener('change', function() {
        updateQuotaSummary(this.selectedOptions[0]);
    });

    document.getElementById('quantity').addEventListener('input', function() {
        const val = parseInt(this.value) || 0;
        if (val > 0) {
            logHistory(`Rencana kurangi kuota forecast sebesar ${val.toLocaleString()} unit.`);
        }
    });

    document.getElementById('poForm').addEventListener('submit', function() {
        logHistory('Kurangi Kuota Forecast berdasarkan Tanggal PO');
        logHistory('Simpan Table History');
        logHistory('Update Dashboard: Sisa Kuota Forecast');
    });

    // Initialize summary if old value exists
    const initialOption = document.getElementById('product_id').selectedOptions[0];
    if (initialOption && initialOption.value) {
        updateQuotaSummary(initialOption);
    }
</script>
@endpush
