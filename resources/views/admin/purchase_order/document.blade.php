{{-- resources/views/admin/purchase_order/document.blade.php --}}
@extends('layouts.admin')

@section('title', 'Detail PO '.$poNumber)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Purchase Orders</a></li>
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
            <p class="page-header__subtitle">Summary and line details from Purchase Order.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.purchase-orders.index') }}" class="page-header__button">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>
            @if(isset($internalPO) && $internalPO)
                <button type="button" class="page-header__button" data-bs-toggle="modal" data-bs-target="#reallocateModalDoc">
                    <i class="fas fa-random"></i>
                    Move Quota
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
        
        @php
            $quotaStatusLabel = '-';
            $quotaStatusClass = '';
            if (isset($internalPO) && $internalPO) {
                $allocs = $internalPO->allocatedQuotas()->get();
                $counts = ['current'=>0,'upcoming'=>0,'expired'=>0,'unknown'=>0];
                foreach ($allocs as $q) {
                    $s = $q->timeline_status ?? 'unknown';
                    if (!isset($counts[$s])) { $counts[$s] = 0; }
                    $counts[$s]++;
                }
                if (($counts['current'] ?? 0) > 0) { $quotaStatusLabel = 'Current'; $quotaStatusClass = 'text-success'; }
                elseif (($counts['upcoming'] ?? 0) > 0 && ($counts['expired'] ?? 0) === 0) { $quotaStatusLabel = 'Upcoming'; $quotaStatusClass = 'text-primary'; }
                elseif (($counts['expired'] ?? 0) > 0 && ($counts['upcoming'] ?? 0) === 0) { $quotaStatusLabel = 'Expired'; $quotaStatusClass = 'text-danger'; }
                elseif (($counts['upcoming'] ?? 0) > 0 || ($counts['expired'] ?? 0) > 0) { $quotaStatusLabel = 'Mixed'; $quotaStatusClass = 'text-warning'; }
            }
        @endphp
        <div class="summary-tile">
            <div class="summary-tile__label">Quota Status</div>
            <div class="summary-tile__value {{ $quotaStatusClass }}">{{ $quotaStatusLabel }}</div>
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
            // Use unified display number from model accessor
        @endphp

        <div class="modal fade" id="reallocateModalDoc" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Move Quota (Per PO Line)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.purchase-orders.reallocate_quota', $internalPO) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Source Quota</label>
                                    <select name="source_quota_id" id="doc_source_quota_id" class="form-select" required>
                                        @foreach($currentAllocs as $q)
                                            <option value="{{ $q->id }}" data-allocated="{{ (int) $q->pivot->allocated_qty }}">
                                                {{ $q->quota_number }} - Alloc: {{ number_format((int) $q->pivot->allocated_qty) }} (Period: {{ optional($q->period_start)->format('d-m-Y') }} to {{ optional($q->period_end)->format('d-m-Y') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Select the quota currently holding this PO's allocation.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">New ETA (optional)</label>
                                    <input type="date" name="eta_date" id="doc_eta_date" class="form-control">
                                    <div class="form-text">Used to filter target quotas by period.</div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Target Quota</label>
                                    <select name="target_quota_id" id="doc_target_quota_id" class="form-select" required>
                                        <option value="" disabled selected hidden>Select quota</option>
                                        @foreach($candidateQuotas as $q)
                                            <option value="{{ $q->id }}" data-start="{{ optional($q->period_start)->format('Y-m-d') }}" data-end="{{ optional($q->period_end)->format('Y-m-d') }}" data-avail="{{ (int) $q->forecast_remaining }}">
                                                {{ $q->quota_number }} - Remaining: {{ number_format((int) $q->forecast_remaining) }} ({{ optional($q->period_start)->format('d-m-Y') }} to {{ optional($q->period_end)->format('d-m-Y') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Only quotas with remaining capacity will receive reallocation.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Quantity to Move</label>
                                    <input type="number" name="move_qty" id="doc_move_qty" class="form-control" min="1" step="1" placeholder="auto">
                                    <div class="form-text">Leave empty to move the entire allocation from the source quota.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Move</button>
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

    @if(isset($internalPO) && $internalPO)
        @php
            // Safely fetch current quota allocations for this PO
            $__poAllocs = $internalPO->allocatedQuotas()->get();
        @endphp

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Quota Info</span>
                <span class="badge bg-secondary">Entries: {{ $__poAllocs->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quota</th>
                                <th>PK Range</th>
                                <th>Period</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Forecast Left</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($__poAllocs as $q)
                                @php
                                    $raw = (string) ($q->government_category ?? '');
                                    $friendly = trim($raw);
                                    try {
                                        $parsed = \App\Support\PkCategoryParser::parse($friendly);
                                        $min = $parsed['min_pk'];
                                        $max = $parsed['max_pk'];
                                        // format numbers without trailing zeros
                                        $minStr = !is_null($min) ? rtrim(rtrim(number_format((float)$min, 2, '.', ''), '0'), '.') : '';
                                        $maxStr = !is_null($max) ? rtrim(rtrim(number_format((float)$max, 2, '.', ''), '0'), '.') : '';
                                        if (!is_null($min) && !is_null($max)) {
                                            $friendly = ($min == $max) ? ($minStr.' PK') : ($minStr.'-'.$maxStr.' PK');
                                        } elseif (!is_null($min) && is_null($max)) {
                                            $friendly = ($min >= 8 && $min < 10) ? '8-10 PK' : ('>'.$minStr.' PK');
                                        } elseif (is_null($min) && !is_null($max)) {
                                            $friendly = ($max <= 8) ? '<8 PK' : ('<'.$maxStr.' PK');
                                        } else {
                                            if ($friendly !== '' && stripos($friendly, 'PK') === false && strtoupper($friendly) !== 'ACCESORY') {
                                                $friendly = $friendly.' PK';
                                            }
                                        }
                                    } catch (\Throwable $e) {
                                        if ($friendly !== '' && stripos($friendly, 'PK') === false && strtoupper($friendly) !== 'ACCESORY') {
                                            $friendly = $friendly.' PK';
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $q->quota_number }}</td>
                                    <td>{{ $friendly !== '' ? $friendly : '-' }}</td>
                                    <td>
                                        {{ optional($q->period_start)->format('d-m-Y') ?? '-' }}
                                        @if($q->period_end)
                                            - {{ optional($q->period_end)->format('d-m-Y') }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((int) ($q->pivot->allocated_qty ?? 0), 0) }}</td>
                                    <td class="text-end">{{ number_format((int) ($q->forecast_remaining ?? 0), 0) }}</td>
                                    <td>
                                        <span class="badge {{ $q->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $q->is_active ? 'active' : 'inactive' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No quota allocation is linked to this PO.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

    <div class="row g-3 align-items-start">
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Voyage (Manual)</div>
                <div class="card-body">
                    <p class="text-muted mb-3">Manage per-line voyage information on a dedicated page for faster, cleaner updates.</p>
                    <a href="{{ route('admin.purchase-orders.voyage.index', ['po' => $poNumber]) }}" class="btn btn-outline-primary w-100">
                        Manage Voyage
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
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
                                <th>Purchasing Document</th>
                                <th>Material</th>
                                <th>Header Text</th>
                                <th>Storage Location</th>
                                <th class="text-end">Order Qty</th>
                                <th class="text-end">To Invoice</th>
                                <th class="text-end">To Deliver</th>
                                <th>Delivery Date</th>
                                <th>Document Date</th>
                                <th>Vendor No</th>
                                <th>Vendor Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                <tr>
                                    <td>{{ $line->po_number }}</td>
                                    <td>{{ $line->item_code ?? '-' }}</td>
                                    <td>{{ $line->item_description ?? '-' }}</td>
                                    <td>{{ $line->storage_location ?? '-' }}</td>
                                    <td class="text-end">{{ number_format((float) ($line->quantity ?? 0), 0) }}</td>
                                    <td class="text-end">{{ isset($line->qty_to_invoice) ? number_format((float) $line->qty_to_invoice, 0) : '-' }}</td>
                                    <td class="text-end">{{ isset($line->qty_to_deliver) ? number_format((float) $line->qty_to_deliver, 0) : '-' }}</td>
                                    <td>{{ !empty($line->deliv_date) ? (\Illuminate\Support\Carbon::parse($line->deliv_date)->format('d M Y')) : '-' }}</td>
                                    <td>{{ optional($line->display_order_date)->format('d M Y') ?? '-' }}</td>
                                    <td>{{ $line->vendor_number ?? '-' }}</td>
                                    <td>{{ $line->vendor_name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">No line data for this PO.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    // Sidebar voyage form wiring
    const form = document.getElementById('voyageForm');
    const selectLine = document.getElementById('voyage_line');
    const badge = document.getElementById('voyage_badge');
    const fields = ['voyage_bl','voyage_etd','voyage_eta','voyage_factory','voyage_status','voyage_issue_date','voyage_expired_date','voyage_remark'];
    function fillForm(data){
        if(!data) return;
        fields.forEach(function(n){ var el=form?.querySelector('[name="'+n+'"]'); if(!el) return; el.value = data[n.replace('voyage_','')] || ''; });
        if(data.id && form){ form.action = data.id; }
        var hasVoy = !!(data.etd || data.eta);
        var inTransit = hasVoy && !!data.alloc && (parseInt(data.received||0) < parseInt(data.qty||0));
        if(badge){ if(inTransit){ badge.classList.remove('d-none'); } else { badge.classList.add('d-none'); } }
    }
    selectLine?.addEventListener('change', function(){
        var opt = selectLine.selectedOptions[0]; if(!opt) return;
        var payload = {}; try { payload = JSON.parse(opt.getAttribute('data-json')||'{}'); } catch(e) { payload = {}; }
        payload.id = "{{ route('admin.purchase-orders.lines.voyage.update', ['line' => 'LINE_ID']) }}".replace('LINE_ID', String(opt.value));
        fillForm(payload);
    });
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
