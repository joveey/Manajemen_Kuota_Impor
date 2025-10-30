{{-- resources/views/admin/products/quick_index_hs.blade.php --}}
@extends('layouts.admin')

@section('title', 'Add Model > HS')
@section('page-title', 'Add Model > HS')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Add Model > HS</li>
@endsection

@section('content')
@php
    $canCreate = auth()->user()?->can('product.create');
@endphp

<div class="container-fluid px-0">
    <div class="mb-4">
        <h1 class="h4 mb-2">Manual Model &gt; HS Entry</h1>
        <p class="text-muted mb-0">
            Add or update model/SKU to HS Code mappings manually. Changes are saved directly to the product master.
        </p>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row gy-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    Form Input Manual
                </div>
                <div class="card-body">
                    @if (!$canCreate)
                        <div class="alert alert-danger mb-0">
                            Access Denied (403): You do not have permission to add HS mappings.
                        </div>
                    @else
                        @include('admin.products.partials.quick_hs_manual_form', [
                            'model' => null,
                            'periodKey' => null,
                            'backUrl' => route('admin.master.quick_hs.index'),
                            'showCancel' => false,
                            'hsSeedOptions' => $hsSeedOptions ?? [],
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Recent Activity</span>
                    <a href="{{ route('admin.mapping.mapped.page') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-table-list me-1"></i> View Mapping
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
                                            @php $label = $product->hs_desc ?? null; @endphp
                                            @if(!empty($label))
                                                {{ $label }}
                                            @elseif(!is_null($product->pk_capacity))
                                                {{ rtrim(rtrim(number_format((float) $product->pk_capacity, 2), '0'), '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ optional($product->updated_at)->format('d M Y H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No models have an HS Code yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    Showing {{ $recent->count() }} most recently updated models.
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Suggested Format</div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Enter <code>Model/SKU</code> exactly as it appears in the product master.</li>
                        <li>The HS Code must already have a PK in the HS &rarr; PK master.</li>
                        <li>Use <code>PK Capacity</code> to store a numeric capacity (optional).</li>
                        <li>The <code>Category</code> field helps classify new models.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
