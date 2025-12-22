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

<div class="am-shell container-fluid px-0">
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row gy-3">
        <div class="col-12">
            <div class="am-card">
                <div class="am-card__header">
                    <div class="am-card__title">Form Input Manual</div>
                </div>
                <div class="am-card__body">
                    @if (!$canCreate)
                        <div class="alert alert-danger mb-0">
                            Access Denied (403): You do not have permission to add HS mappings.
                        </div>
                    @else
                        @include('admin.products.partials.quick_hs_manual_form', [
                            'model' => $model ?? null,
                            'periodKey' => $periodKey ?? null,
                            'backUrl' => $returnUrl ?? route('admin.master.quick_hs.index'),
                            'showCancel' => false,
                            'hsSeedOptions' => $hsSeedOptions ?? [],
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="am-card h-100">
                <div class="am-card__header d-flex justify-content-between align-items-center">
                    <div class="am-card__title">Recent Activity</div>
                    <a href="{{ route('admin.mapping.mapped.page') }}" class="am-btn am-btn--ghost am-btn--sm">
                        <i class="fas fa-table-list me-1"></i> View Mapping
                    </a>
                </div>
                <div class="am-card__body p-0">
                    <div class="table-responsive">
                        <table class="am-table mb-0">
                            <thead>
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
                                        <td>{{ optional($product->updated_at)->setTimezone('Asia/Jakarta')->format('d-m-Y') ?? '-' }}</td>
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
                <div class="am-card__footer text-muted small">
                    Showing {{ $recent->count() }} most recently updated models.
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="am-card h-100">
                <div class="am-card__header"><div class="am-card__title">Suggested Format</div></div>
                <div class="am-card__body">
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

@push('styles')
<style>
.am-card{ border:1px solid #dfe4f3; border-radius:16px; background:#ffffff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.am-card__header{ padding:14px 16px; border-bottom:1px solid #eef2fb; display:flex; align-items:center; justify-content:space-between; }
.am-card__title{ font-size:16px; font-weight:800; color:#0f172a; margin:0; }
.am-card__body{ padding:16px; }
.am-card__footer{ padding:10px 16px; border-top:1px solid #eef2fb; }

/* Modernize form controls inside this page only */
.am-shell .form-control, .am-shell .form-select{ border:1px solid #cbd5f5; border-radius:12px; padding:10px 12px; font-size:13px; transition:border-color .2s ease, box-shadow .2s ease; }
.am-shell .form-control:focus, .am-shell .form-select:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); outline:none; }
.am-shell .btn.btn-primary{ background:#2563eb; border-color:#2563eb; border-radius:12px; font-weight:700; padding:10px 16px; }
.am-shell .btn.btn-outline-secondary{ border-radius:12px; font-weight:700; padding:10px 16px; }

.am-btn{ display:inline-flex; align-items:center; gap:8px; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; border:1px solid transparent; }
.am-btn--ghost{ background:rgba(59,130,246,.08); color:#1d4ed8; border:1px solid #3b82f6; }
.am-btn--sm{ padding:6px 12px; font-size:12px; }

.am-table{ width:100%; border-collapse:separate; border-spacing:0; }
.am-table thead th{ background:#f8fbff; padding:10px 12px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.am-table tbody td{ padding:10px 12px; border-top:1px solid #e5eaf5; font-size:13px; color:#1f2937; }
</style>
@endpush
