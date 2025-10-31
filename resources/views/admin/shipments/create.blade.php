{{-- resources/views/admin/shipments/create.blade.php --}}
@extends('layouts.admin')

@include('admin.shipments.partials.styles')

@section('title', 'Create New Shipment')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.shipments.index') }}">Shipments</a></li>
    <li class="breadcrumb-item active">Create Shipment</li>
@endsection

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Create New Shipment</h1>
            <p class="page-header__subtitle">
                Register upcoming shipment schedules to keep PO status and quotas in sync.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.shipments.index') }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-arrow-left"></i>
                Back to List
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
                        <strong>Please review the entered data:</strong>
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
                        <option value="">-- Select Purchase Order --</option>
                        @foreach($purchaseOrders as $po)
                            <option value="{{ $po->id }}"
                                data-remaining="{{ $po->remaining_quantity }}"
                                data-product="{{ $po->product->code }} - {{ $po->product->name }}"
                                @selected(old('purchase_order_id') == $po->id)>
                                {{ $po->po_number }} | {{ $po->customer_name ?? 'Customer -' }} | Remaining: {{ number_format($po->remaining_quantity) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="shipment-helper d-block mt-2">Only POs with remaining quantity are listed.</small>
                </div>

                <div class="shipment-form-group">
                    <label for="quantity_planned">Quantity to Ship <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="quantity_planned" name="quantity_planned" min="1" value="{{ old('quantity_planned') }}" required>
                    <small class="shipment-helper d-block mt-2" id="quantity-helper">Remaining requirement: -</small>
                </div>

                <div class="shipment-form-group">
                    <label for="ship_date">Ship Date <span class="text-danger">*</span></label>
                    <input type="text" class="form-control datepicker" id="ship_date" name="ship_date" value="{{ old('ship_date', now()->format('Y-m-d')) }}" placeholder="DD-MM-YYYY" required>
                </div>

                <div class="shipment-form-group">
                    <label for="eta_date">Estimated Arrival</label>
                    <input type="text" class="form-control datepicker" id="eta_date" name="eta_date" value="{{ old('eta_date', now()->addDays(14)->format('Y-m-d')) }}" placeholder="DD-MM-YYYY">
                </div>

                <div class="shipment-form-group shipment-form-group--full">
                    <label for="detail">Shipment Details</label>
                    <textarea class="form-control" id="detail" name="detail" rows="3" placeholder="Add additional details such as vessel, route, or special notes.">{{ old('detail') }}</textarea>
                </div>
            </div>

            <div class="shipment-alert shipment-alert--info" id="product-info" style="display:none;"></div>

            <div class="shipment-form-actions">
                <a href="{{ route('admin.shipments.index') }}" class="form-action-btn form-action-btn--secondary">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
                <button type="submit" class="form-action-btn form-action-btn--primary">
                    <i class="fas fa-save"></i>
                    Save Shipment
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
            helper.textContent = 'Remaining requirement: -';
            productInfo.style.display = 'none';
            qtyInput.value = '';
            qtyInput.setAttribute('max', 999999);
            qtyInput.disabled = false;
            submitBtn.disabled = false;
            return;
        }

        const remaining = parseInt(option.dataset.remaining) || 0;
        const product = option.dataset.product;
        helper.textContent = `Remaining requirement: ${remaining.toLocaleString()} units`;
        qtyInput.setAttribute('max', Math.max(remaining, 1));
        qtyInput.value = remaining > 0 ? remaining : '';
        qtyInput.disabled = remaining === 0;
        submitBtn.disabled = remaining === 0;

        productInfo.textContent = remaining > 0
            ? `Product: ${product}. The system will create a new shipment and reduce the forecast on the associated PO.`
            : `Product: ${product}. This PO has been fully fulfilled.`;
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
