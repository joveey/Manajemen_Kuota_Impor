{{-- resources/views/admin/purchase_order/document.blade.php --}}
@extends('layouts.admin')

@section('title', 'Detail PO '.$poNumber)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Daftar Purchase Order</a></li>
    <li class="breadcrumb-item active">PO {{ $poNumber }}</li>
@endsection

@push('styles')
<style>
    .page-shell {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }

    .page-header__title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .page-header__subtitle {
        color: #64748b;
        margin: 0;
    }

    .page-header__actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .page-header__button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        border-radius: 12px;
        background: #f8fafc;
        color: #1d4ed8;
        border: 1px solid rgba(37,99,235,.18);
        font-weight: 600;
        text-decoration: none;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .summary-tile {
        border-radius: 18px;
        border: 1px solid #e6ebf5;
        background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
        padding: 20px;
        box-shadow: 0 22px 46px -38px rgba(15,23,42,.35);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .summary-tile__label {
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .12em;
    }

    .summary-tile__value {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        word-break: break-word;
    }

    .po-header-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px;
    }

    .po-header-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px 16px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .po-header-card__date {
        font-weight: 600;
        color: #1d4ed8;
        font-size: 13px;
    }

    .po-header-card__vendor {
        font-weight: 600;
        color: #0f172a;
        font-size: 13px;
    }

    .po-header-card__meta {
        font-size: 12px;
        color: #64748b;
    }

    .table-wrapper {
        width: 100%;
        overflow: visible !important;
        position: relative;
    }

    .table-shell {
        background: #fff;
        border-radius: 22px;
        border: 1px solid #e6ebf5;
        box-shadow: 0 24px 48px -40px rgba(15,23,42,.32);
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .table-shell__head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .table-shell__title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .table-shell__meta {
        font-size: 12px;
        color: #94a3b8;
    }

    .table-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        position: relative;
    }

    .po-table {
        width: 100%;
        border-collapse: separate !important;
        border-spacing: 0;
        min-width: 1600px;
        margin: 0;
    }

    .po-table thead th {
        background: #f1f5f9;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #64748b;
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .po-table tbody td {
        font-size: 13px;
        padding: 10px 12px;
        border-bottom: 1px solid #eef2f6;
        color: #1f2937;
        white-space: nowrap;
    }

    .po-table tbody tr:hover td {
        background: rgba(59,130,246,.08);
    }

    .scroll-indicator {
        position: absolute;
        right: 16px;
        bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(15,23,42,.78);
        color: #e2e8f0;
        font-size: 12px;
        opacity: 0;
        pointer-events: none;
        transform: translateY(8px);
        transition: opacity .3s ease, transform .3s ease;
    }

    .scroll-indicator.show {
        opacity: 1;
        transform: translateY(0);
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .page-header__title {
            font-size: 24px;
        }

        .page-header__actions {
            width: 100%;
            justify-content: flex-start;
        }
    }
</style>
@endpush

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Detail PO {{ $poNumber }}</h1>
            <p class="page-header__subtitle">Ringkasan dan detail line dari Purchase Order SAP.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.purchase-orders.index') }}" class="page-header__button">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            @if(isset($internalPO) && $internalPO)
                <button type="button" class="page-header__button" data-bs-toggle="modal" data-bs-target="#reallocateModalDoc">
                    <i class="fas fa-random"></i>
                    Pindahkan Kuota
                </button>
            @endif
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-tile">
            <div class="summary-tile__label">PO Doc</div>
            <div class="summary-tile__value">{{ $poNumber }}</div>
        </div>
        <div class="summary-tile">
            <div class="summary-tile__label">Created Date</div>
            <div class="summary-tile__value">{{ $dateRange ?? '-' }}</div>
        </div>
        <div class="summary-tile">
            <div class="summary-tile__label">Vendor No</div>
            <div class="summary-tile__value">{{ $primaryVendorNumber !== '' ? $primaryVendorNumber : '-' }}</div>
        </div>
        <div class="summary-tile">
            <div class="summary-tile__label">Vendor Name</div>
            <div class="summary-tile__value">{{ $primaryVendorName !== '' ? $primaryVendorName : '-' }}</div>
        </div>
        <div class="summary-tile">
            <div class="summary-tile__label">Total Line</div>
            <div class="summary-tile__value">{{ number_format($totals['count']) }}</div>
        </div>
        <div class="summary-tile">
            <div class="summary-tile__label">Total Qty</div>
            <div class="summary-tile__value">{{ number_format($totals['quantity'], 0) }}</div>
        </div>
        <div class="summary-tile">
            <div class="summary-tile__label">Total Amount</div>
            <div class="summary-tile__value">
                {{ !is_null($totals['amount']) ? number_format($totals['amount'], 2) : '-' }}
            </div>
        </div>
    </div>

    @if(isset($internalPO) && $internalPO)
        @php
            $candidateQuotas = \App\Models\Quota::query()
                ->active()
                ->orderBy('period_start')
                ->get()
                ->filter(fn ($q) => $q->matchesProduct($internalPO->product));
            $currentAllocs = $internalPO->allocatedQuotas()->get();
            $formatQuotaNo = function($q) {
                $num = (string) ($q->quota_number ?? '');
                if (preg_match('/^MAN-/', $num)) {
                    return sprintf('QUOTA-%06d', (int) $q->id);
                }
                return $num !== '' ? $num : sprintf('QUOTA-%06d', (int) $q->id);
            };
        @endphp

        <div class="modal fade" id="reallocateModalDoc" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Pindahkan Kuota (Per PO Line)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.purchase-orders.reallocate_quota', $internalPO) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kuota Asal</label>
                                    <select name="source_quota_id" id="doc_source_quota_id" class="form-select" required>
                                        @foreach($currentAllocs as $q)
                                            <option value="{{ $q->id }}" data-allocated="{{ (int) $q->pivot->allocated_qty }}">
                                                {{ $formatQuotaNo($q) }} — Alloc: {{ number_format((int) $q->pivot->allocated_qty) }} (Periode: {{ optional($q->period_start)->format('Y-m-d') }} s/d {{ optional($q->period_end)->format('Y-m-d') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Pilih kuota yang saat ini menampung alokasi PO ini.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ETA Baru (opsional)</label>
                                    <input type="date" name="eta_date" id="doc_eta_date" class="form-control">
                                    <div class="form-text">Dipakai untuk memfilter kuota tujuan sesuai periode.</div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Kuota Tujuan</label>
                                    <select name="target_quota_id" id="doc_target_quota_id" class="form-select" required>
                                        <option value="" disabled selected hidden>Pilih kuota</option>
                                        @foreach($candidateQuotas as $q)
                                            <option value="{{ $q->id }}" data-start="{{ optional($q->period_start)->format('Y-m-d') }}" data-end="{{ optional($q->period_end)->format('Y-m-d') }}" data-avail="{{ (int) $q->forecast_remaining }}">
                                                {{ $formatQuotaNo($q) }} — Sisa: {{ number_format((int) $q->forecast_remaining) }} ({{ optional($q->period_start)->format('Y-m-d') }} s/d {{ optional($q->period_end)->format('Y-m-d') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Hanya kuota dengan sisa kapasitas yang akan menerima realokasi.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Qty Dipindahkan</label>
                                    <input type="number" name="move_qty" id="doc_move_qty" class="form-control" min="1" step="1" placeholder="otomatis">
                                    <div class="form-text">Kosongkan untuk memindahkan seluruh alokasi dari kuota asal.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Pindahkan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
            (function(){
                const eta = document.getElementById('doc_eta_date');
                const tgt = document.getElementById('doc_target_quota_id');
                const src = document.getElementById('doc_source_quota_id');
                const qty = document.getElementById('doc_move_qty');

                function parseDate(val){
                    if (!val) return '';
                    const s = String(val).trim();
                    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s; // YYYY-MM-DD
                    const m = s.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/); // DD/MM/YYYY or DD-MM-YYYY
                    if (m) { return `${m[3]}-${m[2]}-${m[1]}`; }
                    try { const d = new Date(s); if (!isNaN(d)) return d.toISOString().slice(0,10); } catch(e) {}
                    return '';
                }

                function within(date, start, end) {
                    const d = parseDate(date);
                    if (!d) return true; // don't filter if invalid input
                    if (!start || !end) return true;
                    return start <= d && d <= end;
                }

                function filterTarget() {
                    const d = eta.value || '';
                    Array.from(tgt.options).forEach(function(opt){
                        if (!opt.value) return;
                        const s = opt.getAttribute('data-start') || '';
                        const e = opt.getAttribute('data-end') || '';
                        opt.hidden = !within(d, s, e);
                    });
                    const sel = tgt.selectedOptions[0];
                    if (sel && sel.hidden) { tgt.selectedIndex = 0; }
                }

                function syncQtyDefault() {
                    const selected = src.selectedOptions[0];
                    if (!selected) return;
                    const alloc = parseInt(selected.getAttribute('data-allocated') || '0', 10);
                    if (!qty.value) { qty.placeholder = alloc > 0 ? String(alloc) : 'otomatis'; }
                }

                eta && eta.addEventListener('change', filterTarget);
                src && src.addEventListener('change', syncQtyDefault);
                document.getElementById('reallocateModalDoc')?.addEventListener('shown.bs.modal', function(){
                    filterTarget();
                    syncQtyDefault();
                });
            })();
        </script>
        @endpush
    @endif

    @if($headers->count() > 1)
        <div class="table-shell">
            <div class="table-shell__head">
                <h5 class="table-shell__title"><i class="fas fa-layer-group me-2"></i>Header SAP</h5>
                <span class="table-shell__meta">{{ $headers->count() }} entri</span>
            </div>
            <div>
                <div class="po-header-list">
                    @foreach($headers as $header)
                        <div class="po-header-card">
                            <div class="po-header-card__date">{{ optional($header->display_date)->format('d M Y') ?? '-' }}</div>
                            <div class="po-header-card__vendor">{{ $header->supplier ?? '-' }}</div>
                            <div class="po-header-card__meta">Vendor No: {{ $header->display_vendor_number ?? '-' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="table-wrapper">
        <div class="table-shell">
            <div class="table-shell__head">
                <h5 class="table-shell__title"><i class="fas fa-table me-2"></i>Detail Line</h5>
                <span class="table-shell__meta">{{ number_format($totals['count']) }} baris</span>
            </div>
            <div class="table-shell__body">
                <div class="scroll-indicator" id="scrollIndicator">
                    <i class="fas fa-arrows-alt-h"></i>
                    Geser untuk melihat kolom lain
                </div>
                <div class="table-scroll" id="tableScroll">
                    <table class="po-table">
                        <thead>
                            <tr>
                                <th>PO Doc</th>
                                <th>Created Date</th>
                                <th>Deliv Date</th>
                                <th>Vendor No</th>
                                <th>Vendor Name</th>
                                <th>Line No</th>
                                <th>Item Code</th>
                                <th>Item Desc</th>
                                <th>WH Code</th>
                                <th>WH Name</th>
                                <th>WH Source</th>
                                <th>Subinv Code</th>
                                <th>Subinv Name</th>
                                <th>Subinv Source</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Amount</th>
                                <th>Cat PO</th>
                                <th>Cat Desc</th>
                                <th>Mat Grp</th>
                                <th>SAP Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                <tr>
                                    <td>{{ $line->po_number }}</td>
                                    <td>{{ optional($line->display_order_date)->format('d M Y') ?? '-' }}</td>
                                    <td>{{ !empty($line->deliv_date) ? (\Illuminate\Support\Carbon::hasTestNow() ? \Illuminate\Support\Carbon::parse($line->deliv_date)->format('d M Y') : (\Illuminate\Support\Carbon::parse($line->deliv_date)->format('d M Y'))) : '-' }}</td>
                                    <td>{{ $line->vendor_number ?? '-' }}</td>
                                    <td>{{ $line->vendor_name ?? '-' }}</td>
                                    <td>{{ $line->line_number !== '' ? $line->line_number : '-' }}</td>
                                    <td>{{ $line->item_code ?? '-' }}</td>
                                    <td>{{ $line->item_description ?? '-' }}</td>
                                    <td>{{ $line->warehouse_code ?? '-' }}</td>
                                    <td>{{ $line->warehouse_name ?? '-' }}</td>
                                    <td>{{ $line->warehouse_source ?? '-' }}</td>
                                    <td>{{ $line->subinventory_code ?? '-' }}</td>
                                    <td>{{ $line->subinventory_name ?? '-' }}</td>
                                    <td>{{ $line->subinventory_source ?? '-' }}</td>
                                    <td class="text-end">{{ number_format((float) ($line->quantity ?? 0), 0) }}</td>
                                    <td class="text-end">
                                        @if(!is_null($line->amount))
                                            {{ number_format((float) $line->amount, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $line->category_code ?? '-' }}</td>
                                    <td>{{ $line->category ?? '-' }}</td>
                                    <td>{{ $line->material_group ?? '-' }}</td>
                                    <td>{{ $line->sap_order_status ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="19" class="text-center text-muted py-4">Tidak ada data line untuk PO ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const tableScroll = document.getElementById('tableScroll');
    const indicator = document.getElementById('scrollIndicator');

    if (!tableScroll) {
        return;
    }

    const showIndicator = () => {
        if (!indicator) return;
        const isScrollable = tableScroll.scrollWidth > tableScroll.clientWidth;
        if (isScrollable) {
            indicator.classList.add('show');
            setTimeout(() => indicator.classList.remove('show'), 3500);
        }
    };

    window.addEventListener('load', () => setTimeout(showIndicator, 300));
    setTimeout(showIndicator, 500);

    let hasScrolled = false;
    tableScroll.addEventListener('scroll', () => {
        if (!indicator) return;
        if (!hasScrolled) {
            indicator.classList.remove('show');
            hasScrolled = true;
        }
    });

    tableScroll.addEventListener('wheel', (event) => {
        if (event.shiftKey) {
            event.preventDefault();
            tableScroll.scrollLeft += event.deltaY;
        }
    }, { passive: false });

    let isTouching = false;
    let startX = 0;
    let scrollLeft = 0;

    tableScroll.addEventListener('touchstart', (event) => {
        isTouching = true;
        startX = event.touches[0].pageX - tableScroll.offsetLeft;
        scrollLeft = tableScroll.scrollLeft;
    }, { passive: true });

    tableScroll.addEventListener('touchend', () => {
        isTouching = false;
    }, { passive: true });

    tableScroll.addEventListener('touchmove', (event) => {
        if (!isTouching) return;
        event.preventDefault();
        const x = event.touches[0].pageX - tableScroll.offsetLeft;
        const walk = (x - startX) * 2;
        tableScroll.scrollLeft = scrollLeft - walk;
    }, { passive: false });
})();
</script>
@endpush
