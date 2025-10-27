@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('styles')
<style>
    .dashboard-shell {
        width: 100%;
    }

    .dashboard-shell > *:not(:last-child) {
        margin-bottom: 24px;
    }

    @media (max-width: 640px) {
        .dashboard-shell { padding-bottom: 60px; }
        .dashboard-shell > *:not(:last-child) { margin-bottom: 16px; }
        .stat-card { padding: 16px; }
        .stat-icon { width: 40px; height: 40px; }
        .stat-number { font-size: 24px; }
        .chart-container { min-height: 220px; height: 220px; }
        .data-row, .data-row--shipment { 
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .data-cell--qty {
            align-items: flex-start;
            text-align: left;
        }
        .quota-item {
            flex-direction: column;
            gap: 12px;
        }
        .btn-ghost {
            align-self: flex-start;
        }
    }

    /* Stat Cards */
    .stat-card {
        background: white;
        border: 1px solid #e5eaef;
        border-radius: 16px;
        padding: 24px;
        height: 100%;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
    }

    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        box-shadow: 0 8px 20px -10px currentColor;
    }

    .stat-icon i {
        font-size: 22px;
    }

    .stat-icon.primary {
        color: #2563eb;
        background: rgba(37, 99, 235, 0.12);
    }

    .stat-icon.success {
        color: #10b981;
        background: rgba(16, 185, 129, 0.12);
    }

    .stat-icon.info {
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.12);
    }

    .stat-icon.danger {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.12);
    }

    .stat-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 12px;
        line-height: 1;
    }

    .stat-meta {
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.6;
    }

    /* Welcome Card */
    .welcome-card {
        border: 1px solid #e6ebf5;
        border-radius: 20px;
        background: linear-gradient(135deg, #ffffff 0%, #f7faff 100%);
        box-shadow: 0 28px 42px -38px rgba(15, 23, 42, 0.45);
        color: #0f172a;
    }

    .welcome-card .card-body {
        padding: 32px;
    }

    .welcome-card__header {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        align-items: flex-start;
    }

    .welcome-card__title {
        font-size: 26px;
        font-weight: 700;
        margin: 0;
        color: #0f172a;
    }

    .welcome-card__subtitle {
        margin: 8px 0 0;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
    }

    .welcome-card__last-login {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        min-width: 160px;
        padding: 12px 16px;
        background: rgba(37, 99, 235, 0.06);
        border-radius: 12px;
        border: 1px solid rgba(37, 99, 235, 0.12);
    }

    .welcome-card__last-login-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
    }

    .welcome-card__last-login-value {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
    }

    .welcome-card__last-login-meta {
        font-size: 12px;
        color: #64748b;
    }

    .welcome-card__roles {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 24px 0 0;
    }

    .role-chip {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.12);
        color: #2563eb;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    /* KPI Buckets */
    .kpi-section {
        border: 1px solid #e6ebf5;
        border-radius: 22px;
        background: linear-gradient(135deg, #ffffff 0%, #f6f9ff 100%);
        box-shadow: 0 32px 64px -52px rgba(15, 23, 42, 0.55);
        overflow: hidden;
    }

    .kpi-section .card-header {
        border-bottom: 1px solid #eef2fb;
        background: transparent;
        padding: 26px 30px 18px;
    }

    .kpi-section .card-body {
        padding: 26px 30px 32px;
    }

    .kpi-grid {
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e5eaef;
        border-radius: 18px;
        padding: 22px 22px 24px;
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        gap: 18px;
        transition: transform 0.22s ease, box-shadow 0.22s ease;
    }

    .kpi-card::after {
        content: '';
        position: absolute;
        inset: auto 18px 18px;
        height: 140px;
        border-radius: 999px;
        background: radial-gradient(circle at top, rgba(59, 130, 246, 0.18), transparent 70%);
        opacity: 0;
        transition: opacity 0.22s ease;
        pointer-events: none;
    }

    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 24px 42px -36px rgba(15, 23, 42, 0.48);
    }

    .kpi-card:hover::after {
        opacity: 1;
    }

    .kpi-card__header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
    }

    .kpi-card__title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .kpi-card__period {
        font-size: 12px;
        color: #64748b;
    }

    .kpi-card__action {
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid rgba(37, 99, 235, 0.4);
        background: rgba(37, 99, 235, 0.08);
        color: #1d4ed8;
        font-weight: 600;
        font-size: 12px;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .kpi-card__action:hover {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 12px 28px -20px rgba(37, 99, 235, 0.65);
    }

    .kpi-card__progress {
        height: 8px;
        border-radius: 999px;
        background: #eef2fb;
        overflow: hidden;
        position: relative;
    }

    .kpi-card__progress-fill {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
    }

    .kpi-card__progress-fill--warning {
        background: linear-gradient(90deg, #f59e0b 0%, #f97316 100%);
    }

    .kpi-card__progress-fill--critical {
        background: linear-gradient(90deg, #ef4444 0%, #f43f5e 100%);
    }

    .kpi-card__progress-meta {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #64748b;
    }

    .kpi-card__metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 14px;
    }

    .kpi-card__metric {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .kpi-card__metric dt {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #94a3b8;
        margin: 0;
    }

    .kpi-card__metric dd {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .kpi-card__metric--accent dd {
        color: #2563eb;
    }

    .kpi-card__metric--safe dd {
        color: #16a34a;
    }

    .kpi-empty {
        padding: 40px 20px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px dashed #d0d8e5;
        text-align: center;
        color: #64748b;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .kpi-section .card-header,
        .kpi-section .card-body {
            padding: 22px 18px;
        }
    }

    /* Activity feed */
    .activity-card {
        border: 1px solid #e6ebf5;
        border-radius: 20px;
        box-shadow: 0 26px 46px -40px rgba(15, 23, 42, 0.45);
        overflow: hidden;
    }

    .activity-card .card-header {
        border-bottom: 1px solid #eef2fb;
        background: transparent;
        padding: 24px 28px 16px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .activity-card__subtitle {
        display: block;
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .activity-card .card-body {
        padding: 20px 28px 28px;
    }

    .activity-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin: 0;
        padding: 0;
    }

    .activity-item {
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    .activity-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
        box-shadow: 0 12px 24px -18px rgba(15, 23, 42, 0.38);
    }

    .activity-icon--primary { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
    .activity-icon--info { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
    .activity-icon--success { background: rgba(16, 185, 129, 0.12); color: #059669; }
    .activity-icon--warning { background: rgba(245, 158, 11, 0.12); color: #b45309; }
    .activity-icon--muted { background: rgba(148, 163, 184, 0.18); color: #475569; }

    .activity-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .activity-title {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
    }

    .activity-meta {
        font-size: 12px;
        color: #94a3b8;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .activity-tag {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .activity-tag--primary { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; }
    .activity-tag--info { background: rgba(59, 130, 246, 0.14); color: #1e3a8a; }
    .activity-tag--success { background: rgba(16, 185, 129, 0.12); color: #047857; }
    .activity-tag--warning { background: rgba(245, 158, 11, 0.12); color: #9a3412; }
    .activity-tag--muted { background: rgba(148, 163, 184, 0.18); color: #475569; }

    .activity-empty {
        padding: 32px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px dashed #d0d8e5;
        color: #64748b;
        text-align: center;
    }

    .activity-alerts {
        border-top: 1px solid #eef2fb;
        background: rgba(254, 249, 195, 0.35);
        padding: 18px 28px;
    }

    .activity-alerts__title {
        font-size: 13px;
        font-weight: 700;
        color: #b45309;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .activity-alerts__list {
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 6px;
        color: #92400e;
        font-size: 12px;
    }

    @media (max-width: 768px) {
        .activity-card .card-header,
        .activity-card .card-body {
            padding: 22px 18px;
        }

        .activity-card .card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .activity-icon {
            width: 38px;
            height: 38px;
            font-size: 16px;
        }
    }

    /* Operational panels */
    .panel-modern {
        background: #ffffff;
        border: 1px solid #e6ebf5;
        border-radius: 18px;
        box-shadow: 0 20px 42px -32px rgba(15, 23, 42, 0.45);
        display: flex;
        flex-direction: column;
        height: 100%;
        position: relative;
        z-index: 0;
    }

    .panel-modern__header {
        padding: 22px 26px 18px;
        border-bottom: 1px solid #eef2fb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .panel-modern__title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .panel-modern__body {
        padding: 22px 26px 26px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .panel-modern__body--empty {
        justify-content: center;
        align-items: center;
    }

    .empty-state {
        width: 100%;
        padding: 40px 20px;
        border: 2px dashed #e2e8f0;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: center;
        text-align: center;
        color: #64748b;
        background: #f8fafc;
    }

    .empty-state__icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        background: rgba(37, 99, 235, 0.08);
        color: #2563eb;
    }

    .empty-state__text {
        margin: 0;
        font-size: 14px;
        font-weight: 500;
        color: #64748b;
    }

    .panel-modern__link {
        font-size: 13px;
        font-weight: 600;
        color: #2563eb;
        text-decoration: none;
    }

    .panel-modern__link:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .quota-alerts {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .quota-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 18px 20px;
        border-radius: 14px;
        border: 1px solid #eef2fb;
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .quota-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 28px -28px rgba(15, 23, 42, 0.45);
    }

    .quota-item--warning {
        border-color: #fde68a;
        background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
    }

    .quota-item--critical {
        border-color: #fecaca;
        background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
    }

    .quota-item__title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }

    .quota-meta {
        font-size: 12px;
        color: #64748b;
        margin-top: 6px;
        line-height: 1.5;
    }

    .quota-pill {
        margin-top: 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: #e2e8f0;
        color: #475569;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .quota-item--warning .quota-pill {
        background: #fef3c7;
        color: #92400e;
    }

    .quota-item--critical .quota-pill {
        background: #fee2e2;
        color: #b91c1c;
    }

    .quota-item--normal .quota-pill {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 16px;
        border-radius: 999px;
        border: 1px solid rgba(37, 99, 235, 0.35);
        background: rgba(37, 99, 235, 0.08);
        color: #2563eb;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-ghost:hover {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 10px 20px -18px rgba(37, 99, 235, 0.75);
        transform: translateY(-1px);
    }

    .data-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .data-row {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.7fr) minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 18px 20px;
        border-radius: 14px;
        border: 1px solid #eef2fb;
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .data-row--shipment {
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.6fr) minmax(0, 1fr) auto;
    }

    .data-row:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px -28px rgba(15, 23, 42, 0.45);
    }

    .data-cell {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .data-cell--status {
        align-items: flex-start;
        gap: 8px;
    }

    .data-cell--qty {
        align-items: flex-end;
        text-align: right;
        min-width: 80px;
    }

    .data-title {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .data-sub {
        font-size: 12px;
        color: #64748b;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .data-qty {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }

    @media (max-width: 1100px) {
        .data-row,
        .data-row--shipment {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .data-cell--qty {
            align-items: flex-start;
            text-align: left;
        }
    }

    .badge-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .badge-chip--warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-chip--info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-chip--success {
        background: #dcfce7;
        color: #166534;
    }

    .badge-chip--muted {
        background: #e2e8f0;
        color: #475569;
    }

    @media (max-width: 640px) {
        .welcome-card .card-body {
            padding: 24px;
        }
        
        .welcome-card__header {
            flex-direction: column;
            gap: 16px;
        }
        
        .welcome-card__last-login {
            width: 100%;
            align-items: flex-start;
        }
        
        .welcome-card__title {
            font-size: 22px;
        }
        
        .welcome-card__subtitle {
            font-size: 13px;
        }
    }
</style>
@endpush

@section('content')
<div class="dashboard-shell">

    <!-- Pipeline Kuota -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong style="font-size: 16px; font-weight: 700;">Pipeline Kuota</strong>
            <a href="{{ route('admin.mapping.unmapped.page') }}" class="panel-modern__link">Lihat</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="stat-label">Unmapped Model</div>
                        <div class="stat-number">{{ number_format($metrics['unmapped'] ?? 0) }}</div>
                        <p class="stat-meta">Belum punya HS/PK</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                            <i class="fas fa-link"></i>
                        </div>
                        <div class="stat-label">Mapped</div>
                        <div class="stat-number">{{ number_format($metrics['mapped'] ?? 0) }}</div>
                        <p class="stat-meta">Model sudah map</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="stat-label">Open PO</div>
                        <div class="stat-number">{{ number_format($metrics['open_po_qty'] ?? 0) }}</div>
                        <p class="stat-meta">Outstanding qty</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-label">In-Transit</div>
                        <div class="stat-number">{{ number_format($metrics['in_transit_qty'] ?? 0) }}</div>
                        <p class="stat-meta">Invoice - GR</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="stat-label">GR (Actual)</div>
                        <div class="stat-number">{{ number_format($metrics['gr_qty'] ?? 0) }}</div>
                        <p class="stat-meta">Diterima</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Supply Chain Metrics -->
    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="stat-label">Total Kuota</div>
                <div class="stat-number">{{ number_format($quotaStats['total']) }}</div>
                <p class="stat-meta">Available: {{ number_format($quotaStats['available']) }} | Limited: {{ number_format($quotaStats['limited']) }} | Depleted: {{ number_format($quotaStats['depleted']) }}</p>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-label">Forecast Remaining</div>
                <div class="stat-number">{{ number_format($quotaStats['forecast_remaining']) }}</div>
                <p class="stat-meta">Actual Remaining: {{ number_format($quotaStats['actual_remaining']) }}</p>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-label">PO Bulan Ini</div>
                <div class="stat-number">{{ number_format($poStats['this_month']) }}</div>
                <p class="stat-meta">Need Shipment: {{ number_format($poStats['need_shipment']) }} | In Transit: {{ number_format($poStats['in_transit']) }}</p>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-truck-loading"></i>
                </div>
                <div class="stat-label">Pengiriman</div>
                <div class="stat-number">{{ number_format($shipmentStats['in_transit']) }}</div>
                <p class="stat-meta">Delivered: {{ number_format($shipmentStats['delivered']) }} | Pending Receipt: {{ number_format($shipmentStats['pending_receipt']) }}</p>
            </div>
        </div>
    </div>

    <!-- KPI per PK Bucket -->
    <div class="card kpi-section">
        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <strong style="font-size: 16px; font-weight: 700;">KPI per PK Bucket</strong>
                <span class="activity-card__subtitle">Pantau alokasi dan konsumsi terbaru untuk setiap bucket PK.</span>
            </div>
            @if(\Illuminate\Support\Facades\Route::has('admin.quotas.index'))
                <a href="{{ route('admin.quotas.index') }}" class="kpi-card__action">Kelola Kuota</a>
            @endif
        </div>
        <div class="card-body">
            @if($quotaCards->isNotEmpty())
                <div class="kpi-grid">
                    @foreach($quotaCards as $qc)
                        @include('admin.partials.kpi_bucket_card', ['quota' => $qc])
                    @endforeach
                </div>
            @else
                <div class="kpi-empty">
                    <i class="fas fa-chart-pie mb-2" aria-hidden="true"></i>
                    <div>Belum ada data kuota untuk ditampilkan.</div>
                </div>
            @endif
        </div>
    </div>

    <!-- Activity Feed & Alerts -->
    @include('admin.partials.activity_feed', ['activities' => $activities, 'alerts' => $alerts])

    <!-- Operational Overview -->
    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="panel-modern">
                <div class="panel-modern__header">
                    <h3 class="panel-modern__title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h8m-9 8h10a2 2 0 002-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Recent Purchase Orders
                    </h3>
                    @if(\Illuminate\Support\Facades\Route::has('admin.purchase-orders.index'))
                        <a href="{{ route('admin.purchase-orders.index') }}" class="panel-modern__link">Lihat semua</a>
                    @endif
                </div>
                @php $recentPurchaseOrdersEmpty = $recentPurchaseOrders->isEmpty(); @endphp
                <div class="panel-modern__body {{ $recentPurchaseOrdersEmpty ? 'panel-modern__body--empty' : '' }}">
                    @if($recentPurchaseOrdersEmpty)
                        <div class="empty-state">
                            <span class="empty-state__icon"><i class="fas fa-file-invoice"></i></span>
                            <p class="empty-state__text">Belum ada purchase order terbaru.</p>
                        </div>
                    @else
                        <div class="data-list">
                            @foreach($recentPurchaseOrders as $po)
                                @php
                                    $poStatusStyles = [
                                        'ordered' => ['label' => 'Ordered', 'class' => 'badge-chip--info'],
                                        'partial' => ['label' => 'Partial', 'class' => 'badge-chip--warning'],
                                        'completed' => ['label' => 'Completed', 'class' => 'badge-chip--success'],
                                        'draft' => ['label' => 'Draft', 'class' => 'badge-chip--muted'],
                                    ];
                                    $poStatus = $poStatusStyles[$po->status_key] ?? ['label' => ucfirst($po->status_key ?? 'status'), 'class' => 'badge-chip--muted'];
                                    $sapStatuses = $po->sap_statuses ?? [];
                                @endphp
                                <div class="data-row data-row--po">
                                    <div class="data-cell">
                                        <span class="data-title">{{ $po->po_number }}</span>
                                        <span class="data-sub">{{ optional($po->po_date)->format('d M Y') ?? 'Tanpa tanggal' }}</span>
                                        @if(!empty($po->supplier))
                                            <span class="data-sub">{{ $po->supplier }}</span>
                                        @endif
                                    </div>
                                    <div class="data-cell">
                                        <span class="data-title">{{ $po->line_count }} line</span>
                                        @if(!empty($sapStatuses))
                                            <span class="data-sub">{{ implode(', ', $sapStatuses) }}</span>
                                        @else
                                            <span class="data-sub text-muted">SAP status belum tersedia</span>
                                        @endif
                                    </div>
                                    <div class="data-cell data-cell--status">
                                        <span class="badge-chip {{ $poStatus['class'] }}">{{ $poStatus['label'] }}</span>
                                        <span class="data-sub">Received {{ fmt_qty($po->received_qty) }} / {{ fmt_qty($po->total_qty) }}</span>
                                    </div>
                                    <div class="data-cell data-cell--qty">
                                        <span class="data-qty">{{ fmt_qty($po->total_qty) }}</span>
                                        <span class="data-sub">Total unit</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-4">
            <div class="panel-modern">
                <div class="panel-modern__header">
                    <h3 class="panel-modern__title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v7h7l-3-5h-4V7H3zM5 18a2 2 0 104 0 2 2 0 10-4 0zm10 0a2 2 0 104 0 2 2 0 10-4 0z" />
                        </svg>
                        GR Receipt Terbaru
                    </h3>
                    @if(\Illuminate\Support\Facades\Route::has('admin.imports.gr.index'))
                        <a href="{{ route('admin.imports.gr.index') }}" class="panel-modern__link">Lihat semua</a>
                    @endif
                </div>
                @php $recentShipmentsEmpty = $recentShipments->isEmpty(); @endphp
                <div class="panel-modern__body {{ $recentShipmentsEmpty ? 'panel-modern__body--empty' : '' }}">
                    @if($recentShipmentsEmpty)
                        <div class="empty-state">
                            <span class="empty-state__icon"><i class="fas fa-receipt"></i></span>
                            <p class="empty-state__text">Belum ada GR receipt terbaru.</p>
                        </div>
                    @else
                        <div class="data-list">
                            @foreach($recentShipments as $shipment)
                                <div class="data-row data-row--shipment">
                                    <div class="data-cell">
                                        <span class="data-title">{{ $shipment->po_number }}@if($shipment->line_no) <span class="text-muted"> / {{ $shipment->line_no }}</span>@endif</span>
                                        <span class="data-sub">{{ optional($shipment->receive_date)->format('d M Y') ?? 'Tanpa tanggal' }}</span>
                                    </div>
                                    <div class="data-cell">
                                        <span class="data-title">{{ $shipment->item_name ?? 'Item tidak diketahui' }}</span>
                                        @if($shipment->vendor_name)
                                            <span class="data-sub">{{ $shipment->vendor_name }}</span>
                                        @endif
                                        @if($shipment->warehouse_name)
                                            <span class="data-sub text-muted">{{ $shipment->warehouse_name }}</span>
                                        @endif
                                    </div>
                                    <div class="data-cell data-cell--status">
                                        @if(!empty($shipment->sap_status))
                                            <span class="badge-chip badge-chip--info">{{ $shipment->sap_status }}</span>
                                        @else
                                            <span class="badge-chip badge-chip--muted">GR</span>
                                        @endif
                                    </div>
                                    <div class="data-cell data-cell--qty">
                                        <span class="data-qty">{{ fmt_qty($shipment->quantity) }}</span>
                                        <span class="data-sub">Unit diterima</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
