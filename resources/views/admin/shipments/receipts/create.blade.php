@extends('layouts.admin')

@include('admin.shipments.partials.styles')

@section('title', 'Catat Penerimaan Shipment')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.shipments.index') }}">Pengiriman</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.shipments.show', $shipment->id) }}">Detail Shipment</a></li>
    <li class="breadcrumb-item active">Catat Penerimaan</li>
@endsection

@section('content')
@php
    $totalReceived = (int) $shipment->receipts()->sum('quantity_received');
    $planned = (int) $shipment->quantity_planned;
    $remaining = max(0, $planned - $totalReceived);
@endphp

<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Catat Penerimaan</h1>
            <p class="page-header__subtitle">
                Tambahkan catatan penerimaan untuk shipment {{ $shipment->shipment_number ?? "#{$shipment->id}" }}.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.shipments.show', $shipment->id) }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Detail
            </a>
        </div>
    </div>

    <div class="shipment-card shipment-card--padded">
        <div class="shipment-summary mb-4">
            <div class="shipment-summary__card">
                <span class="shipment-summary__label">Qty Direncanakan</span>
                <span class="shipment-summary__value">{{ number_format($planned) }} unit</span>
            </div>
            <div class="shipment-summary__card">
                <span class="shipment-summary__label">Qty Diterima</span>
                <span class="shipment-summary__value">{{ number_format($totalReceived) }} unit</span>
            </div>
            <div class="shipment-summary__card">
                <span class="shipment-summary__label">Sisa Qty</span>
                <span class="shipment-summary__value">{{ number_format($remaining) }} unit</span>
                <span class="shipment-summary__meta">
                    @if($remaining > 0)
                        Sisa penerimaan masih dapat dicatat.
                    @else
                        Seluruh quantity telah terpenuhi.
                    @endif
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.shipments.receipts.store', $shipment->id) }}">
            @csrf

            @if($errors->any())
                <div class="shipment-alert shipment-alert--error">
                    <i class="fas fa-circle-exclamation mt-1"></i>
                    <div>
                        <strong>Data belum lengkap:</strong>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="shipment-form-grid">
                <div class="shipment-form-group">
                    <label for="receipt_date">Tanggal Penerimaan</label>
                    <input
                        type="date"
                        id="receipt_date"
                        name="receipt_date"
                        class="form-control"
                        value="{{ old('receipt_date', now()->toDateString()) }}">
                    @error('receipt_date')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
                </div>

                <div class="shipment-form-group">
                    <label for="quantity_received">Qty Diterima</label>
                    <span class="shipment-helper d-block mb-2">
                        @if($remaining > 0)
                            Maksimal {{ number_format($remaining) }} unit.
                        @else
                            Kuantitas sudah terpenuhi untuk shipment ini.
                        @endif
                    </span>
                    <input
                        type="number"
                        id="quantity_received"
                        name="quantity_received"
                        min="1"
                        @if($remaining > 0) max="{{ $remaining }}" @endif
                        step="1"
                        class="form-control"
                        value="{{ old('quantity_received', $remaining > 0 ? $remaining : null) }}"
                        @if($remaining === 0) disabled @endif>
                    @error('quantity_received')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
                    @if($remaining === 0)
                        <small class="text-warning d-block mt-1">Seluruh quantity sudah diterima untuk shipment ini.</small>
                    @endif
                </div>

                <div class="shipment-form-group">
                    <label for="document_number">No. Dokumen (Customs/Container)</label>
                    <input
                        type="text"
                        id="document_number"
                        name="document_number"
                        class="form-control"
                        value="{{ old('document_number') }}">
                    @error('document_number')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
                </div>

                <div class="shipment-form-group shipment-form-group--full">
                    <label for="notes">Catatan</label>
                    <textarea
                        id="notes"
                        name="notes"
                        class="form-control"
                        rows="3"
                        placeholder="Catat kondisi pengiriman, nomor kontainer, atau informasi pelengkap lainnya.">{{ old('notes') }}</textarea>
                    @error('notes')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="shipment-form-actions">
                <a href="{{ route('admin.shipments.show', $shipment->id) }}" class="form-action-btn form-action-btn--secondary">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                <button type="submit" class="form-action-btn form-action-btn--primary" @if($remaining === 0) disabled @endif>
                    <i class="fas fa-save"></i>
                    Simpan Penerimaan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
