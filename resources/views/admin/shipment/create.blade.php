{{-- resources/views/admin/shipment/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Buat Shipment Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.shipments.index') }}">Pengiriman</a></li>
    <li class="breadcrumb-item active">Buat Shipment</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-truck-loading me-2"></i>Form Shipment</h3>
            </div>
            <form action="{{ route('admin.shipments.store') }}" method="POST" id="shipmentForm">
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

                    <div class="mb-3">
                        <label for="purchase_order_id" class="form-label">Purchase Order <span class="text-danger">*</span></label>
                        <select class="form-select" id="purchase_order_id" name="purchase_order_id" required>
                            <option value="">-- Pilih Purchase Order --</option>
                            @foreach($purchaseOrders as $po)
                                <option value="{{ $po->id }}"
                                        data-remaining="{{ $po->remaining_quantity }}"
                                        data-product="{{ $po->product->code }} - {{ $po->product->name }}">
                                    {{ $po->po_number }} | {{ $po->customer_name ?? 'Customer -' }} | Sisa: {{ number_format($po->remaining_quantity) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Hanya PO yang belum terpenuhi akan muncul di daftar.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="quantity_planned" class="form-label">Qty Dikirim <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity_planned" name="quantity_planned" min="1" required>
                            <small class="text-muted" id="quantity-helper">Sisa kebutuhan: -</small>
                        </div>
                        <div class="col-md-4">
                            <label for="ship_date" class="form-label">Tanggal Kirim <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ship_date" name="ship_date" value="{{ old('ship_date', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="eta_date" class="form-label">Estimasi Tiba</label>
                            <input type="date" class="form-control" id="eta_date" name="eta_date" value="{{ old('eta_date', now()->addDays(14)->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="detail" class="form-label">Detail Pengiriman</label>
                        <textarea class="form-control" id="detail" name="detail" rows="3" placeholder="Informasi tambahan (kapal, rute, catatan)">{{ old('detail') }}</textarea>
                    </div>

                    <div class="alert alert-info" id="product-info" style="display:none;"></div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.shipments.index') }}" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Shipment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const poSelect = document.getElementById('purchase_order_id');
    const qtyInput = document.getElementById('quantity_planned');
    const helper = document.getElementById('quantity-helper');
    const productInfo = document.getElementById('product-info');
    const submitBtn = document.querySelector('#shipmentForm button[type="submit"]');

    function updateHelper(option) {
        if (!option || !option.value) {
            helper.textContent = 'Sisa kebutuhan: -';
            productInfo.style.display = 'none';
            qtyInput.value = '';
            qtyInput.setAttribute('max', 999999);
            qtyInput.disabled = false;
            submitBtn.disabled = false;
            return;
        }

        const remaining = parseInt(option.dataset.remaining) || 0;
        const product = option.dataset.product;
        helper.textContent = `Sisa kebutuhan: ${remaining.toLocaleString()} unit`;
        qtyInput.setAttribute('max', Math.max(remaining, 1));
        qtyInput.value = remaining > 0 ? remaining : '';
        qtyInput.disabled = remaining === 0;
        submitBtn.disabled = remaining === 0;

        productInfo.textContent = remaining > 0
            ? `Produk: ${product}. Sistem akan membuat shipment baru dan mengurangi forecast pada PO terkait.`
            : `Produk: ${product}. PO ini sudah terpenuhi seluruhnya.`;
        productInfo.style.display = 'block';
    }

    poSelect.addEventListener('change', function() {
        updateHelper(this.selectedOptions[0]);
    });

    // Initialize when returning with validation errors
    const initialOption = poSelect.selectedOptions[0];
    if (initialOption && initialOption.value) {
        updateHelper(initialOption);
    }
</script>
@endpush
