{{-- resources/views/admin/shipments/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Pengiriman (Shipment)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Pengiriman (Shipment)</li>
@endsection

@push('styles')
<style>
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
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Pengiriman (Shipment)</h1>
            <p class="page-header__subtitle">Lacak seluruh pengiriman, status penerimaan, dan ETA dalam satu dashboard modern.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.shipments.export') }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-file-export"></i>
                Export CSV
            </a>
            @can('create purchase_orders')
                <a href="{{ route('admin.shipments.create') }}" class="page-header__button page-header__button--primary">
                    <i class="fas fa-plus"></i>
                    Buat Shipment
                </a>
            @endcan
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
                    <th class="text-end">Status Konfirmasi SAP</th>
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

                        $confirmationMessage = match($shipment->status) {
                            \App\Models\Shipment::STATUS_DELIVERED => 'Selesai oleh SAP',
                            \App\Models\Shipment::STATUS_PARTIAL => 'Parsial, menunggu SAP',
                            \App\Models\Shipment::STATUS_IN_TRANSIT => 'Dalam proses konfirmasi SAP',
                            default => 'Menunggu konfirmasi SAP',
                        };
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
                            <span class="shipment-table__subtext">{{ $confirmationMessage }}</span>
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
