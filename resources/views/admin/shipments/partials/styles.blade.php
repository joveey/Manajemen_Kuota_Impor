@push('styles')
<style>
    .shipment-card {
        background: #ffffff;
        border: 1px solid #e6ebf5;
        border-radius: 22px;
        padding: 26px;
        box-shadow: 0 32px 64px -48px rgba(15, 23, 42, .45);
    }

    .shipment-card--padded {
        padding: 32px;
    }

    .shipment-alert {
        border-radius: 16px;
        padding: 14px 18px;
        font-size: 13px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 18px;
    }

    .shipment-alert ul {
        padding-left: 18px;
        margin: 0;
    }

    .shipment-alert--error {
        background: rgba(248, 113, 113, .12);
        border: 1px solid rgba(248, 113, 113, .35);
        color: #b91c1c;
    }

    .shipment-alert--info {
        background: rgba(37, 99, 235, .08);
        border: 1px solid rgba(37, 99, 235, .35);
        color: #1d4ed8;
    }

    .shipment-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px 20px;
    }

    .shipment-form-group label {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 6px;
        font-size: 13px;
    }

    .shipment-form-group--full {
        grid-column: 1 / -1;
    }

    .shipment-helper {
        color: #64748b;
        font-size: 12px;
    }

    .shipment-form-group small {
        color: #64748b;
    }

    .shipment-form-group .form-control,
    .shipment-form-group .form-select {
        border-radius: 12px;
        border-color: #cbd5f5;
        font-size: 13px;
        padding: 10px 14px;
    }

    .shipment-form-group textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }

    .shipment-form-actions {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 24px;
    }

    .shipment-form-actions .form-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid transparent;
        transition: all .2s ease;
        text-decoration: none;
    }

    .form-action-btn--primary {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 18px 38px -30px rgba(37, 99, 235, .78);
    }

    .form-action-btn--primary:hover {
        background: #1d4ed8;
        color: #ffffff;
        transform: translateY(-1px);
    }

    .form-action-btn--secondary {
        background: rgba(148, 163, 184, .12);
        color: #1f2937;
        border-color: rgba(148, 163, 184, .35);
    }

    .form-action-btn--secondary:hover {
        background: rgba(148, 163, 184, .18);
    }

    .shipment-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 18px;
    }

    .shipment-summary__card {
        border-radius: 18px;
        border: 1px solid #e6ebf5;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .shipment-summary__label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #94a3b8;
    }

    .shipment-summary__value {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }

    .shipment-summary__meta {
        font-size: 12px;
        color: #64748b;
    }

    .shipment-progress {
        margin-top: 10px;
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .shipment-progress__bar {
        height: 100%;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
    }

    .shipment-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 18px;
    }

    .shipment-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
        color: #475569;
    }

    .shipment-meta__label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #94a3b8;
    }

    .shipment-meta__value {
        font-weight: 600;
        color: #0f172a;
    }

    .shipment-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .shipment-badge--success {
        background: rgba(34, 197, 94, .16);
        color: #166534;
    }

    .shipment-badge--warning {
        background: rgba(251, 191, 36, .16);
        color: #92400e;
    }

    .shipment-badge--danger {
        background: rgba(239, 68, 68, .16);
        color: #b91c1c;
    }

    .shipment-badge--neutral {
        background: rgba(148, 163, 184, .18);
        color: #475569;
    }

    .shipment-table-shell {
        border-radius: 22px;
        border: 1px solid #e6ebf5;
        overflow: hidden;
    }

    .shipment-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .shipment-table thead th {
        background: #f8faff;
        padding: 15px 18px;
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .shipment-table tbody td {
        padding: 16px 18px;
        border-top: 1px solid #eef2fb;
        font-size: 13px;
        color: #1f2937;
    }

    .shipment-table tbody tr:hover {
        background: rgba(37, 99, 235, .04);
    }

    .shipment-empty {
        padding: 28px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .shipment-message {
        border-radius: 16px;
        border: 1px dashed rgba(37, 99, 235, .35);
        padding: 16px 18px;
        font-size: 12px;
        color: #2563eb;
        background: rgba(37, 99, 235, .06);
    }

    @media (max-width: 768px) {
        .shipment-form-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .shipment-form-actions .form-action-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush
