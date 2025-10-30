{{-- resources/views/admin/products/quick_create_hs.blade.php --}}
@extends('layouts.admin')

@section('title', 'Add Model > HS - Manual Entry')
@section('page-title', 'Manual Model > HS Input')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.master.quick_hs.index') }}">Add Model > HS</a></li>
    <li class="breadcrumb-item active">Manual Entry</li>
@endsection

@section('content')
@php
    $canCreate = auth()->user()?->can('product.create');
    $backUrl = old('return', $returnUrl ?? route('admin.master.quick_hs.index'));
@endphp

<div class="container-fluid px-0">
    @if(!$canCreate)
        <div class="alert alert-danger">
            Access Denied (403): You do not have permission to add HS mappings.
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Manual Model &gt; HS Input</h5>
                    <div class="text-muted small">
                        Add or update model/SKU to HS Code mappings manually. Changes are saved directly to the product master.
                    </div>
                </div>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @include('admin.products.partials.quick_hs_manual_form', [
                    'model' => $model ?? null,
                    'periodKey' => $periodKey ?? null,
                    'backUrl' => $backUrl,
                    'showCancel' => true,
                ])
            </div>
        </div>
    @endif
</div>
@endsection
