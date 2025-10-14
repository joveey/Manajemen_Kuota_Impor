{{-- resources/views/admin/shipment/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Pengiriman (Shipment)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Pengiriman (Shipment)</li>
@endsection

@push('styles')
<style>
    .shipment-page { display:flex; flex-direction:column; gap:28px; }

    .shipment-header {
        display:flex;
        flex-wrap:wrap;
        justify-content:space-between;
        gap:18px;
        align-items:flex-start;
    }

    .shipment-title { font-size:26px; font-weight:700; color:#0f172a; margin:0; }
    .shipment-subtitle { margin-top:6px; color:#64748b; font-size:13px; max-width:520px; }

    .shipment-actions { display:flex; gap:12px; }
    .shipment-action {
        display:inline-flex; align-items:center; gap:8px;
        padding:10px 18px; border-radius:14px; font-size:13px; font-weight:600;
        text-decoration:none; transition:all .2s ease; border:1px solid transparent;
    }
    .shipment-action--outline { background:rgba(148,163,184,.1); color:#1f2937; border-color:rgba(148,163,184,.35); }
    .shipment-action--outline:hover { background:rgba(148,163,184,.16); }
    .shipment-action--primary { background:#2563eb; color:#fff; box-shadow:0 18px 38px -30px rgba(37,99,235,.78); }
    .shipment-action--primary:hover { background:#1d4ed8; transform:translateY(-1px); }

    .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
    .summary-card {
        border-radius:18px; border:1px solid #e6ebf5;
        background:linear-gradient(135deg,#ffffff 0%, #f8fafc 100%);
        padding:20px; box-shadow:0 24px 48px -44px rgba(15,23,42,.45);
        display:flex; flex-direction:column; gap:6px;
    }
    .summary-card__label { font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:.12em; }
    .summary-card__value { font-size:24px; font-weight:700; color:#0f172a; }

    .table-shell {
        background:#ffffff; border:1px solid #e6ebf5; border-radius:22px;
        overflow:hidden; box-shadow:0 32px 64px -48px rgba(15,23,42,.45);
    }
    .shipment-table { width:100%; border-collapse:separate; border-spacing:0; }
    .shipment-table thead th {
        background:#f8faff; padding:15px 18px; font-size:12px; color:#64748b;
        text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid #e6ebf5;
    }
    .shipment-table tbody td {
        padding:16px 18px; border-bottom:1px solid #eef2fb; font-size:13px; color:#1f2937;
        vertical-align:top;
    }
    .shipment-table tbody tr:hover { background:rgba(37,99,235,.04); }
    .shipment-table__subtext { font-size:11.5px; color:#94a3b8; display:block; }

    .status-chip {
        display:inline-flex; align-items:center; gap:6px;
        padding:6px 12px; border-radius:999px; font-size:12px; font-weight:600;
        text-transform:uppercase; letter-spacing:.06em;
    }
    .status-chip--pending { background:rgba(148,163,184,.16); color:#475569; }
    .status-chip--in-transit { background:rgba(251,191,36,.16); color:#92400e; }
    .status-chip--partial { background:rgba(96,165,250,.16); color:#1d4ed8; }
    .status-chip--delivered { background:rgba(34,197,94,.16); color:#166534; }

    .confirm-button {
        display:inline-flex;
        align-items:center;
        gap:10px;
        padding:10px 16px;
        border-radius:12px;
        border:1px solid rgba(34,197,94,.32);
        background:rgba(34,197,94,.12);
        color:#166534;
        font-size:13px;
        font-weight:600;
        transition:all .2s ease;
    }

    .confirm-button__icon {
        width:22px;
        height:22px;
        border-radius:8px;
        background:#22c55e;
        color:#ffffff;
        display:grid;
        place-items:center;
        font-size:11px;
    }

    .confirm-button:hover {
        background:#22c55e;
        color:#ffffff;
        box-shadow:0 18px 38px -32px rgba(34,197,94,.7);
        transform:translateY(-1px);
    }

    .confirm-button:hover .confirm-button__icon {
        background:#15803d;
    }

    .confirm-panel {
        background:#ffffff;
        border:1px solid #dbe3f3;
        border-radius:16px;
        padding:18px 20px;
        margin-top:10px;
        box-shadow:0 20px 44px -40px rgba(15,23,42,.35);
    }

    .confirm-panel__header {
        display:flex;
        flex-wrap:wrap;
        gap:16px;
        justify-content:space-between;
        align-items:flex-start;
        margin-bottom:16px;
    }

    .confirm-panel__title {
        font-weight:700;
        color:#0f172a;
        margin:0;
        font-size:15px;
    }

    .confirm-panel__meta {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        color:#475569;
        font-size:12px;
    }

    .confirm-panel__meta span {
        background:rgba(148, 163, 184, 0.12);
        padding:6px 10px;
        border-radius:10px;
        font-weight:600;
        letter-spacing:0.04em;
        text-transform:uppercase;
        color:#475569;
    }

    .confirm-form label { font-size:12px; color:#475569; font-weight:600; }
    .confirm-form input,
    .confirm-form textarea {
        border-radius:12px;
        border:1px solid #dbe3f3;
        padding:10px 14px;
        font-size:13px;
    }

    .confirm-form small { color:#94a3b8; }

    .confirm-submit {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 18px;
        border-radius:12px;
        background:#2563eb;
        color:#ffffff;
        border:none;
        font-size:13px;
        font-weight:600;
        transition:all .2s ease;
    }

    .confirm-submit:hover {
        background:#1d4ed8;
        transform:translateY(-1px);
    }

    .status-history-card {
        margin-top:12px;
        border:1px solid #dbe3f3;
        border-radius:16px;
        background:linear-gradient(135deg,#f8fbff 0%, #ffffff 100%);
        padding:18px 22px;
        box-shadow:0 22px 48px -42px rgba(15,23,42,.35);
    }
    .status-history-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:14px;
    }
    .status-history-title {
        font-size:13px;
        font-weight:700;
        text-transform:uppercase;
        letter-spacing:.08em;
        color:#0f172a;
    }
    .status-history-badge {
        font-size:12px;
        color:#64748b;
    }
    .status-history-empty {
        font-size:12px;
        color:#94a3b8;
    }

    .pagination-modern { display:flex; justify-content:flex-end; margin-top:20px; }

    @media (max-width: 992px) {
        .shipment-header { flex-direction:column; align-items:stretch; }
        .shipment-actions { justify-content:flex-start; }
    }
</style>
@endpush

@section('content')
<div class="shipment-page">
    <div class="shipment-header">
        <div>
            <h1 class="shipment-title">Pengiriman (Shipment)</h1>
            <p class="shipment-subtitle">Lacak seluruh pengiriman, status penerimaan, dan ETA dalam satu dashboard modern.</p>
        </div>
        <div class="shipment-actions">
            <a href="{{ route('admin.shipments.export') }}" class="shipment-action shipment-action--outline">
                <i class="fas fa-file-export"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.shipments.create') }}" class="shipment-action shipment-action--primary">
                <i class="fas fa-plus"></i>
                Buat Shipment
            </a>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span class="summary-card__label">Total Pengiriman</span>
            <span class="summary-card__value">{{ number_format($summary['total']) }}</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Dalam Perjalanan</span>
            <span class="summary-card__value">{{ number_format($summary['in_transit']) }}</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Sudah Diterima</span>
            <span class="summary-card__value">{{ number_format($summary['delivered']) }}</span>
        </div>
        <div class="summary-card">
            <span class="summary-card__label">Total Unit Dikirim</span>
            <span class="summary-card__value">{{ number_format($summary['quantity_total']) }}</span>
        </div>
    </div>

    <div class="table-shell">
        <table class="shipment-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>No. Pengiriman</th>
                    <th>PO Number</th>
                    <th>Produk</th>
                    <th class="text-end">Qty Dikirim</th>
                    <th class="text-end">Qty Diterima</th>
                    <th>Tgl Kirim</th>
                    <th>ETA</th>
                    <th>Status</th>
                    <th class="text-end">Konfirmasi</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $statusNameMap = [
                        \App\Models\Shipment::STATUS_PENDING => 'Menunggu',
                        \App\Models\Shipment::STATUS_IN_TRANSIT => 'Dalam Perjalanan',
                        \App\Models\Shipment::STATUS_PARTIAL => 'Parsial',
                        \App\Models\Shipment::STATUS_DELIVERED => 'Selesai',
                    ];
                    $statusVariantMap = [
                        \App\Models\Shipment::STATUS_PENDING => 'neutral',
                        \App\Models\Shipment::STATUS_IN_TRANSIT => 'primary',
                        \App\Models\Shipment::STATUS_PARTIAL => 'warning',
                        \App\Models\Shipment::STATUS_DELIVERED => 'success',
                    ];
                @endphp
                @forelse($shipments as $shipment)
                    @php
                        $statusBadge = match($shipment->status) {
                            \App\Models\Shipment::STATUS_IN_TRANSIT => 'status-chip--in-transit',
                            \App\Models\Shipment::STATUS_PARTIAL => 'status-chip--partial',
                            \App\Models\Shipment::STATUS_DELIVERED => 'status-chip--delivered',
                            default => 'status-chip--pending',
                        };
                        $statusLabel = $statusNameMap[$shipment->status] ?? ucfirst($shipment->status);

                        $historyItems = $shipment->statusLogs
                            ->sortBy('recorded_at')
                            ->map(function ($log) use ($statusNameMap, $statusVariantMap) {
                                $planned = $log->quantity_planned_snapshot;
                                $received = $log->quantity_received_snapshot;
                                $badge = null;

                                if (!is_null($planned) || !is_null($received)) {
                                    $badge = sprintf(
                                        '%s / %s unit',
                                        number_format($received ?? 0),
                                        number_format($planned ?? 0)
                                    );
                                }

                                return [
                                    'title' => $statusNameMap[$log->status] ?? ucfirst(str_replace('_', ' ', $log->status)),
                                    'subtitle' => $log->description,
                                    'date' => optional($log->recorded_at)->format('d M Y H:i'),
                                    'badge' => $badge,
                                    'variant' => $statusVariantMap[$log->status] ?? 'neutral',
                                ];
                            })
                            ->values()
                            ->all();
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <strong>{{ $shipment->shipment_number }}</strong>
                            @if($shipment->auto_generated)
                                <span class="status-chip status-chip--pending" style="margin-left:6px;">Auto</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $shipment->purchaseOrder->po_number }}</strong>
                            <span class="shipment-table__subtext">Qty PO: {{ number_format($shipment->purchaseOrder->quantity) }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $shipment->purchaseOrder->product->code }}</div>
                            <span class="shipment-table__subtext">{{ $shipment->purchaseOrder->product->name }}</span>
                        </td>
                        <td class="text-end">{{ number_format($shipment->quantity_planned) }}</td>
                        <td class="text-end">{{ number_format($shipment->quantity_received) }}</td>
                        <td>{{ optional($shipment->ship_date)->format('d M Y') ?? '-' }}</td>
                        <td>{{ optional($shipment->eta_date)->format('d M Y') ?? '-' }}</td>
                        <td>
                            <span class="status-chip {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="text-end">
                            @if($shipment->status === \App\Models\Shipment::STATUS_DELIVERED)
                                <span class="po-table__subtext">Selesai</span>
                            @else
                                <button class="confirm-button" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#receipt-form-{{ $shipment->id }}"
                                    aria-expanded="false"
                                    aria-controls="receipt-form-{{ $shipment->id }}">
                                    <span class="confirm-button__icon"><i class="fas fa-box-open"></i></span>
                                    <span>Konfirmasi</span>
                                </button>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td colspan="10">
                            <div class="status-history-card">
                                <div class="status-history-header">
                                    <span class="status-history-title">Riwayat Status</span>
                                    <span class="status-history-badge">{{ $shipment->statusLogs->count() }} catatan</span>
                                </div>
                                @if(!empty($historyItems))
                                    <x-timeline :items="$historyItems" class="mb-0" />
                                @else
                                    <div class="status-history-empty">Belum ada riwayat status untuk shipment ini.</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if($shipment->status !== \App\Models\Shipment::STATUS_DELIVERED)
                        <tr class="collapse" id="receipt-form-{{ $shipment->id }}">
                            <td colspan="10">
                                @php
                                    $remaining = $shipment->quantity_planned - $shipment->quantity_received;
                                @endphp
                                <div class="confirm-panel">
                                    <div class="confirm-panel__header">
                                        <div>
                                            <p class="confirm-panel__title">Konfirmasi Penerimaan - {{ $shipment->shipment_number }}</p>
                                            <div class="confirm-panel__meta">
                                                <span>PO: {{ $shipment->purchaseOrder->po_number }}</span>
                                                <span>Produk: {{ $shipment->purchaseOrder->product->code }}</span>
                                                <span>Sisa Qty: {{ number_format($remaining) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <form action="{{ route('admin.shipments.receive', $shipment) }}" method="POST" class="confirm-form row g-3">
                                        @csrf
                                        <div class="col-md-3">
                                            <label class="form-label small">Tanggal Receipt</label>
                                            <input type="date" name="receipt_date" class="form-control form-control-sm"
                                                value="{{ now()->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Qty Diterima</label>
                                            <input type="number"
                                                name="quantity_received"
                                                class="form-control form-control-sm"
                                                min="1"
                                                max="{{ $remaining }}"
                                                value="{{ $remaining }}"
                                                required>
                                            <small>Sisa tersisa: {{ number_format($remaining) }}</small>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">No Dokumen</label>
                                            <input type="text" name="document_number" class="form-control form-control-sm" placeholder="Optional">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Catatan</label>
                                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="submit" class="confirm-submit">
                                                <i class="fas fa-check"></i>
                                                Simpan Penerimaan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted">Belum ada pengiriman.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($shipments, 'links'))
        <div class="pagination-modern">
            {{ $shipments->links() }}
        </div>
    @endif
</div>
@endsection
