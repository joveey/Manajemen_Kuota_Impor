@extends('layouts.admin')

@include('admin.shipments.partials.styles')

@section('title', 'Shipment Detail')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.shipments.index') }}">Shipments</a></li>
    <li class="breadcrumb-item active">Shipment Detail</li>
@endsection

@section('content')
@php
    $totalReceived = (int) $shipment->receipts->sum('quantity_received');
    $planned = max(0, (int) $shipment->quantity_planned);
    $percentage = $planned > 0 ? min(100, (int) round(($totalReceived / max(1, $planned)) * 100)) : 0;

    $shipmentStatusMap = [
        \App\Models\Shipment::STATUS_DELIVERED => ['label' => 'Delivered', 'badge' => 'shipment-badge--success'],
        \App\Models\Shipment::STATUS_PARTIAL => ['label' => 'Partial', 'badge' => 'shipment-badge--warning'],
        \App\Models\Shipment::STATUS_IN_TRANSIT => ['label' => 'In Transit', 'badge' => 'shipment-badge--warning'],
        \App\Models\Shipment::STATUS_SCHEDULED => ['label' => 'Scheduled', 'badge' => 'shipment-badge--neutral'],
    ];

    $statusMeta = $shipmentStatusMap[$shipment->status] ?? [
        'label' => ucfirst(str_replace('_', ' ', $shipment->status)),
        'badge' => 'shipment-badge--neutral',
    ];

    $quota = optional($shipment->purchaseOrder)->quota;
    $quotaBadge = match ($quota?->status) {
        'available' => 'shipment-badge--success',
        'limited' => 'shipment-badge--warning',
        'depleted' => 'shipment-badge--danger',
        default => 'shipment-badge--neutral',
    };

    $statusTimeline = $shipment->statusLogs
        ->sortByDesc('recorded_at')
        ->map(function ($log) {
            $received = $log->quantity_received_snapshot;
            $planned = $log->quantity_planned_snapshot;
            $progressBadge = null;

            if (!is_null($received) || !is_null($planned)) {
                $progressBadge = sprintf(
                    '%s / %s unit',
                    number_format($received ?? 0),
                    number_format($planned ?? 0)
                );
            }

            $badgeVariant = match ($log->status) {
                \App\Models\Shipment::STATUS_DELIVERED => 'success',
                \App\Models\Shipment::STATUS_PARTIAL => 'info',
                \App\Models\Shipment::STATUS_IN_TRANSIT => 'warning',
                default => 'neutral',
            };

            return [
                'title' => ucfirst(str_replace('_', ' ', $log->status)),
                'subtitle' => $log->description,
                'date' => optional($log->recorded_at)->format('d M Y H:i'),
                'badge' => $progressBadge,
                'variant' => $badgeVariant,
            ];
        })
        ->values()
        ->all();
@endphp

<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $shipment->shipment_number ?? "Shipment #{$shipment->id}" }}</h1>
            <p class="page-header__subtitle">
                Summary of status, schedule, and receipts for this shipment.
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.shipments.index') }}" class="page-header__button page-header__button--outline">
                <i class="fas fa-arrow-left"></i>
                Back to List
            </a>
            <a href="{{ route('admin.shipments.receipts.create', $shipment->id) }}" class="page-header__button page-header__button--primary">
                <i class="fas fa-clipboard-check"></i>
                Record Receipt
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="shipment-alert shipment-alert--info">
            <i class="fas fa-check-circle mt-1"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <div class="shipment-card shipment-card--padded">
        <div class="shipment-summary">
            <div class="shipment-summary__card">
                <span class="shipment-summary__label">Shipment Status</span>
                <span class="shipment-summary__value">{{ $statusMeta['label'] }}</span>
                <span class="shipment-summary__meta">
                    <span class="shipment-badge {{ $statusMeta['badge'] }}">{{ $statusMeta['label'] }}</span>
                </span>
            </div>
            <div class="shipment-summary__card">
                <span class="shipment-summary__label">Progress</span>
                <span class="shipment-summary__value">{{ number_format($totalReceived) }} / {{ number_format($planned) }}</span>
                <div class="shipment-progress">
                    <div class="shipment-progress__bar" style="width: {{ $percentage }}%;"></div>
                </div>
                <span class="shipment-summary__meta">{{ $percentage }}% fulfilled</span>
            </div>
            <div class="shipment-summary__card">
                <span class="shipment-summary__label">Schedule</span>
                <span class="shipment-summary__value">{{ optional($shipment->ship_date)->format('d M Y') ?? '-' }}</span>
                <span class="shipment-summary__meta">
                    ETA: {{ optional($shipment->eta_date)->format('d M Y') ?? 'Not set' }}
                </span>
            </div>
        </div>

        <div class="shipment-meta-grid">
            <div class="shipment-meta">
                <span class="shipment-meta__label">Purchase Order</span>
                <span class="shipment-meta__value">{{ optional($shipment->purchaseOrder)->po_number ?? '-' }}</span>
                <span class="shipment-summary__meta">
                    Qty PO: {{ optional($shipment->purchaseOrder) ? number_format($shipment->purchaseOrder->quantity) : '-' }} unit
                </span>
            </div>

            <div class="shipment-meta">
                <span class="shipment-meta__label">Product</span>
                <span class="shipment-meta__value">
                    {{ optional(optional($shipment->purchaseOrder)->product)->code ?? '-' }}
                </span>
                <span class="shipment-summary__meta">
                    {{ optional(optional($shipment->purchaseOrder)->product)->name ?? 'No product information' }}
                </span>
            </div>

            <div class="shipment-meta">
                <span class="shipment-meta__label">Shipment Number</span>
                <span class="shipment-meta__value">{{ $shipment->shipment_number ?? 'Not assigned' }}</span>
                <span class="shipment-summary__meta">
                    Created: {{ optional($shipment->created_at)->format('d M Y H:i') ?? '-' }}
                </span>
            </div>

            <div class="shipment-meta">
                <span class="shipment-meta__label">Quota</span>
                <span class="shipment-meta__value">{{ $quota?->id ?? '-' }}</span>
                <span class="shipment-summary__meta">
                    <span class="shipment-badge {{ $quotaBadge }}">{{ ucfirst($quota?->status ?? 'Unknown') }}</span>
                </span>
            </div>
        </div>

        @if($shipment->detail)
            <div class="shipment-message mt-4">
                <strong>Shipment Notes:</strong>
                <div class="mt-2">{{ $shipment->detail }}</div>
            </div>
        @endif
    </div>

    <div class="shipment-card">
        <h2 class="shipment-section-title">Status History</h2>
        @if(!empty($statusTimeline))
            <x-timeline :items="$statusTimeline" />
        @else
            <div class="shipment-empty">No status history for this shipment.</div>
        @endif
    </div>

    <div class="shipment-card">
        <div class="shipment-section-title d-flex justify-content-between align-items-center">
            <span>Receipt History</span>
            <span class="shipment-summary__meta">Total {{ $shipment->receipts->count() }} records</span>
        </div>

        @if($shipment->receipts->isEmpty())
            <div class="shipment-empty">No receipts yet.</div>
        @else
            <div class="shipment-table-shell">
                <table class="shipment-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Document No.</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shipment->receipts as $receipt)
                            <tr>
                                <td>{{ optional($receipt->receipt_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ number_format((int) $receipt->quantity_received) }}</td>
                                <td>{{ $receipt->document_number ?? '-' }}</td>
                                <td>{{ $receipt->notes ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
