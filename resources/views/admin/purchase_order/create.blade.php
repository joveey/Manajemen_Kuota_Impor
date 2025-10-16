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
                        <div class="col-md-6">
                            <label for="po_number" class="form-label">PO Doc <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="po_number" name="po_number" value="{{ old('po_number') }}" placeholder="Contoh: 7,971E+09" required>
                        </div>
                        <div class="col-md-6">
                            <label for="created_date" class="form-label">Created Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="created_date" name="created_date" value="{{ old('created_date', now()->format('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="vendor_number" class="form-label">Vendor No.</label>
                            <input type="text" class="form-control" id="vendor_number" name="vendor_number" value="{{ old('vendor_number') }}" placeholder="Contoh: 21932">
                        </div>
                        <div class="col-md-6">
                            <label for="vendor_name" class="form-label">Vendor Name</label>
                            <input type="text" class="form-control" id="vendor_name" name="vendor_name" value="{{ old('vendor_name') }}" placeholder="Nama vendor SAP">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="product_id" class="form-label">Item Code <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">-- Pilih Item --</option>
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
                        <div class="col-md-3">
                            <label for="line_number" class="form-label">Line No.</label>
                            <input type="text" class="form-control" id="line_number" name="line_number" value="{{ old('line_number') }}" placeholder="Contoh: 10">
                        </div>
                        <div class="col-md-3">
                            <label for="quantity" class="form-label">Qty <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="{{ old('quantity') }}" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3 mt-1">
                        <label for="item_description" class="form-label">Item Description</label>
                        <textarea class="form-control" id="item_description" name="item_description" rows="2" placeholder="Deskripsi item SAP">{{ old('item_description') }}</textarea>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" min="0" step="0.01" placeholder="Nilai PO">
                        </div>
                        <div class="col-md-4">
                            <label for="sap_order_status" class="form-label">SAP Order Status</label>
                            <input type="text" class="form-control" id="sap_order_status" name="sap_order_status" value="{{ old('sap_order_status') }}" placeholder="Contoh: Trade Overseas">
                        </div>
                        <div class="col-md-4">
                            <label for="category_code" class="form-label">Cat PO</label>
                            <input type="text" class="form-control" id="category_code" name="category_code" value="{{ old('category_code') }}" placeholder="Kode kategori">
                        </div>
                        <div class="col-md-4">
                            <label for="category" class="form-label">Cat Desc</label>
                            <input type="text" class="form-control" id="category" name="category" value="{{ old('category') }}" placeholder="Deskripsi kategori">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="material_group" class="form-label">Material Group</label>
                            <input type="text" class="form-control" id="material_group" name="material_group" value="{{ old('material_group') }}" placeholder="Contoh: COMAC">
                        </div>
                        <div class="col-md-4">
                            <label for="warehouse_code" class="form-label">WH Code</label>
                            <input type="text" class="form-control" id="warehouse_code" name="warehouse_code" value="{{ old('warehouse_code') }}" placeholder="Contoh: 7971">
                        </div>
                        <div class="col-md-4">
                            <label for="warehouse_name" class="form-label">WH Name</label>
                            <input type="text" class="form-control" id="warehouse_name" name="warehouse_name" value="{{ old('warehouse_name') }}" placeholder="Nama gudang">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="warehouse_source" class="form-label">WH Source</label>
                            <input type="text" class="form-control" id="warehouse_source" name="warehouse_source" value="{{ old('warehouse_source') }}" placeholder="Sumber WH">
                        </div>
                        <div class="col-md-4">
                            <label for="subinventory_code" class="form-label">Subinv Code</label>
                            <input type="text" class="form-control" id="subinventory_code" name="subinventory_code" value="{{ old('subinventory_code') }}" placeholder="Contoh: M001">
                        </div>
                        <div class="col-md-4">
                            <label for="subinventory_name" class="form-label">Subinv Name</label>
                            <input type="text" class="form-control" id="subinventory_name" name="subinventory_name" value="{{ old('subinventory_name') }}" placeholder="Nama Subinventory">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="subinventory_source" class="form-label">Subinv Source</label>
                            <input type="text" class="form-control" id="subinventory_source" name="subinventory_source" value="{{ old('subinventory_source') }}" placeholder="Sumber Subinventory">
                        </div>
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
    const itemCodeInput = document.getElementById('item_code');
    const itemDescriptionInput = document.getElementById('item_description');

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

        if (itemCodeInput && option.dataset.code) {
            itemCodeInput.value = option.dataset.code;
        }

        if (itemDescriptionInput && option.dataset.name && !itemDescriptionInput.value) {
            itemDescriptionInput.value = option.dataset.name;
        }
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
