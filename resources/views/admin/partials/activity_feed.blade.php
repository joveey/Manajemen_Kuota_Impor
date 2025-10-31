@props(['activities' => [], 'alerts' => []])
@php
    $iconMap = [
        'GR' => ['icon' => 'fa-receipt', 'variant' => 'info'],
        'HS_PK' => ['icon' => 'fa-layer-group', 'variant' => 'primary'],
        'QUOTA' => ['icon' => 'fa-chart-pie', 'variant' => 'warning'],
        'PO' => ['icon' => 'fa-file-invoice', 'variant' => 'primary'],
        'SHIPMENT' => ['icon' => 'fa-truck', 'variant' => 'success'],
        'USER' => ['icon' => 'fa-user-check', 'variant' => 'success'],
    ];
    $hasImportRoute = \Illuminate\Support\Facades\Route::has('admin.imports.quotas.index');
@endphp
<div class="card activity-card">
    <div class="card-header">
        <div>
            <strong>Activity (7 days)</strong>
            <span class="activity-card__subtitle">Recent import and update activity.</span>
        </div>
        @if($hasImportRoute)
            <a href="{{ route('admin.imports.quotas.index') }}" class="kpi-card__action">Import History</a>
        @endif
    </div>
    <div class="card-body">
        @if(!empty($activities))
            <ul class="activity-list">
                @foreach($activities as $a)
                    @php
                        $type = strtoupper($a['type'] ?? 'LOG');
                        $meta = $iconMap[$type] ?? ['icon' => 'fa-file-alt', 'variant' => 'muted'];
                        $time = $a['time'] ?? null;
                    @endphp
                    <li class="activity-item">
                        <span class="activity-icon activity-icon--{{ $meta['variant'] }}">
                            <i class="fas {{ $meta['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <div class="activity-content">
                            <div class="activity-title">{{ $a['title'] }}</div>
                            <div class="activity-meta">
                                <span class="activity-tag activity-tag--{{ $meta['variant'] }}">{{ $type }}</span>
                                @if($time)
                                    <span>&middot; {{ $time }}</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="activity-empty">
                <i class="fas fa-inbox mb-2" aria-hidden="true"></i>
                <div>No activity in the last 7 days.</div>
            </div>
        @endif
    </div>
    @if(!empty($alerts))
        <div class="activity-alerts">
            <div class="activity-alerts__title">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                Important Alerts
            </div>
            <ul class="activity-alerts__list">
                @foreach($alerts as $msg)
                    <li>{{ $msg }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

