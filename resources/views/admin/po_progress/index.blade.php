@extends('layouts.admin')

@section('title', 'Pengiriman & Receipt')

@section('content')
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari PO No atau Supplier...">
                </div>
                <div class="col-md-3">
                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                        @foreach([10,20,30,50] as $opt)
                            <option value="{{ $opt }}" {{ (int)$perPage === $opt ? 'selected' : '' }}>{{ $opt }} per halaman</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-2"></i>Cari</button>
                </div>
                <div class="col-md-3 text-end small text-muted">
                    Read-only: Ordered | Shipped | Received | In-Transit | Sisa
                </div>
            </form>
        </div>
    </div>

    @forelse($headers as $header)
        @php
            $poNo = $header->po_number;
            $meta = $poData[$poNo]['meta'] ?? ['po_date'=>null,'supplier'=>null,'currency'=>null];
            $sum = $poData[$poNo]['summary'] ?? ['ordered_total'=>0,'shipped_total'=>0,'received_total'=>0,'in_transit'=>0,'remaining'=>0];
            $lines = $poData[$poNo]['lines'] ?? [];
        @endphp
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0 d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <div class="fw-bold" style="font-size: 1.1rem;">PO {{ $poNo }}</div>
                    <div class="text-muted small">{{ $meta['po_date'] ?? '-' }} &middot; {{ $meta['supplier'] ?? '-' }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="badge rounded-pill text-bg-secondary p-2">Ordered <span class="ms-1 fw-semibold">{{ fmt_qty($sum['ordered_total']) }}</span></div>
                    <div class="badge rounded-pill" style="background:#e8f0ff;color:#1d4ed8;border:1px solid #c9dcff;">Shipped <span class="ms-1 fw-semibold">{{ fmt_qty($sum['shipped_total']) }}</span></div>
                    <div class="badge rounded-pill" style="background:#e8faee;color:#15803d;border:1px solid #bbf7d0;">Received <span class="ms-1 fw-semibold">{{ fmt_qty($sum['received_total']) }}</span></div>
                    <div class="badge rounded-pill text-bg-info p-2">In-Transit <span class="ms-1 fw-semibold">{{ fmt_qty(max($sum['in_transit'],0)) }}</span></div>
                    <div class="badge rounded-pill text-bg-warning p-2">Sisa <span class="ms-1 fw-semibold">{{ fmt_qty(max($sum['remaining'],0)) }}</span></div>
                </div>
            </div>

            <div class="card-body pt-2">
                @if(empty($lines))
                    <div class="text-muted fst-italic">Tidak ada line untuk PO ini.</div>
                @else
                    <div class="accordion" id="acc-{{ \Illuminate\Support\Str::slug($poNo) }}">
                        @foreach($lines as $idx => $ln)
                            @php
                                $cid = 'line-'.$poNo.'-'.$ln['line_no'];
                            @endphp
                            <div class="mb-2 border rounded-3">
                                <div class="d-flex align-items-center justify-content-between p-3">
                                    <div class="d-flex flex-column">
                                        <div class="fw-semibold">Line {{ $ln['line_no'] }} &middot; {{ $ln['model_code'] ?? '-' }}</div>
                                        <div class="text-muted small">{{ $ln['item_desc'] ?? '-' }} &middot; Ordered: <strong>{{ fmt_qty($ln['ordered']) }}</strong> {{ $ln['uom'] ?? '' }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge text-bg-secondary">Shipped: {{ fmt_qty($ln['shipped_total']) }}</span>
                                        <span class="badge text-bg-success">Received: {{ fmt_qty($ln['received_total']) }}</span>
                                        <span class="badge text-bg-info">In-Transit: {{ fmt_qty($ln['in_transit']) }}</span>
                                        <span class="badge text-bg-warning">Sisa: {{ fmt_qty($ln['remaining']) }}</span>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $cid }}" aria-expanded="false" aria-controls="{{ $cid }}">
                                            Detail
                                        </button>
                                    </div>
                                </div>
                                <div id="{{ $cid }}" class="collapse" data-bs-parent="#acc-{{ \Illuminate\Support\Str::slug($poNo) }}">
                                    <div class="px-3 pb-3">
                                        <div class="table-responsive" style="overflow-x:auto;">
                                            <table class="table table-sm align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="min-width:120px">Tanggal</th>
                                                        <th style="min-width:110px">Tipe</th>
                                                        <th class="text-end" style="min-width:100px">Qty</th>
                                                        <th class="text-end" style="min-width:120px">Shipped Σ</th>
                                                        <th class="text-end" style="min-width:120px">Received Σ</th>
                                                        <th class="text-end" style="min-width:120px">In-Transit</th>
                                                        <th class="text-end" style="min-width:120px">Sisa (Actual)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                @forelse($ln['events'] as $ev)
                                                    <tr>
                                                        <td>{{ $ev['date'] ?? '-' }}</td>
                                                        <td>
                                                            @if($ev['type'] === 'shipment')
                                                                <span class="badge" style="background:#e8f0ff;color:#1d4ed8;border:1px solid #c9dcff;">Shipment</span>
                                                            @else
                                                                <span class="badge" style="background:#e8faee;color:#15803d;border:1px solid #bbf7d0;">GR</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">{{ fmt_qty($ev['qty']) }}</td>
                                                        <td class="text-end">{{ fmt_qty($ev['ship_sum']) }}</td>
                                                        <td class="text-end">{{ fmt_qty($ev['gr_sum']) }}</td>
                                                        <td class="text-end">{{ fmt_qty(max($ev['in_transit'],0)) }}</td>
                                                        <td class="text-end">{{ fmt_qty(max($ev['remaining'],0)) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="text-muted fst-italic">Belum ada event Shipment/GR.</td>
                                                    </tr>
                                                @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-info">Tidak ada PO ditemukan.</div>
    @endforelse

    <div class="d-flex justify-content-end">
        {{ $headers->links() }}
    </div>
</div>
@endsection
