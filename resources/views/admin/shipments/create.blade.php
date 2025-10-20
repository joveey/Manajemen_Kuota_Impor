{{-- resources/views/admin/shipments/create.blade.php --}}
@extends('layouts.admin')

@include('admin.shipments.partials.styles')

@section('title', 'Buat Shipment Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.shipments.index') }}">Pengiriman</a></li>
    <li class="breadcrumb-item active">Buat Shipment</li>
@endsection

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Buat Shipment Baru</h1>
            <p class="page-header__subtitle">
                Registrasikan jadwal pengiriman terbaru untuk memastikan status PO dan kuota selalu sinkron.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.shipments.index') }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar
            </a>
        </div>
    </div>

    <div class="shipment-card shipment-card--padded">
        <form action="{{ route('admin.shipments.store') }}" method="POST" id="shipmentForm">
            @csrf

            @if($errors->any())
                <div class="shipment-alert shipment-alert--error">
                    <i class="fas fa-circle-exclamation mt-1"></i>
                    <div>
                        <strong>Periksa kembali data yang diisi:</strong>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="shipment-form-grid">
                <div class="shipment-form-group shipment-form-group--full">
                    <label for="purchase_order_id">Purchase Order <span class="text-danger">*</span></label>
                    <select class="form-select" id="purchase_order_id" name="purchase_order_id" required>
                        <option value="">-- Pilih Purchase Order --</option>
                        @foreach($purchaseOrders as $po)
                            <option value="{{ $po->id }}"
                                data-remaining="{{ $po->remaining_quantity }}"
                                data-product="{{ $po->product->code }} - {{ $po->product->name }}"
                                @selected(old('purchase_order_id') == $po->id)>
                                {{ $po->po_number }} | {{ $po->customer_name ?? 'Customer -' }} | Sisa: {{ number_format($po->remaining_quantity) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="shipment-helper d-block mt-2">Hanya PO dengan kebutuhan tersisa yang ditampilkan.</small>
                </div>

                <div class="shipment-form-group">
                    <label for="quantity_planned">Qty Dikirim <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="quantity_planned" name="quantity_planned" min="1" value="{{ old('quantity_planned') }}" required>
                    <small class="shipment-helper d-block mt-2" id="quantity-helper">Sisa kebutuhan: -</small>
                </div>

                <div class="shipment-form-group">
                    <label for="ship_date">Tanggal Kirim <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="ship_date" name="ship_date" value="{{ old('ship_date', now()->format('Y-m-d')) }}" required>
                </div>

                <div class="shipment-form-group">
                    <label for="eta_date">Estimasi Tiba</label>
                    <input type="date" class="form-control" id="eta_date" name="eta_date" value="{{ old('eta_date', now()->addDays(14)->format('Y-m-d')) }}">
                </div>

                <div class="shipment-form-group shipment-form-group--full">
                    <label for="detail">Detail Pengiriman</label>
                    <textarea class="form-control" id="detail" name="detail" rows="3" placeholder="Catat detail tambahan seperti kapal, rute, atau catatan khusus.">{{ old('detail') }}</textarea>
                </div>
            </div>

            <div class="shipment-alert shipment-alert--info" id="product-info" style="display:none;"></div>

            <div class="shipment-form-actions">
                <a href="{{ route('admin.shipments.index') }}" class="form-action-btn form-action-btn--secondary">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                <button type="submit" class="form-action-btn form-action-btn--primary">
                    <i class="fas fa-save"></i>
                    Simpan Shipment
                </button>
            </div>
        </form>
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
