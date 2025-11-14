@extends('layouts.admin')

@section('title', 'Shipments & Receipts')

@section('content')
<div class="container-fluid px-0">
    <div class="pp-card mb-3">
        <div class="pp-card__body">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" value="{{ $q }}" class="pp-input" placeholder="Search PO No or Supplier...">
                </div>
                <div class="col-md-3">
                    <select name="per_page" class="pp-select" onchange="this.form.submit()">
                        @foreach([10,20,30,50] as $opt)
                            <option value="{{ $opt }}" {{ (int)$perPage === $opt ? 'selected' : '' }}>{{ $opt }} per page</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="pp-btn pp-btn--primary w-100" type="submit"><i class="fas fa-search me-2"></i>Search</button>
                </div>
                <div class="col-md-3 text-end small text-muted pp-hint">
                    Read-only: Ordered | Shipped | Received | In-Transit | Remaining
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
        <div class="pp-block mb-4">
            <div class="pp-block__header">
                <div>
                    <div class="pp-po-title">PO {{ $poNo }}</div>
                    <div class="pp-po-subtitle">{{ $meta['po_date'] ?? '-' }} &middot; {{ $meta['supplier'] ?? '-' }}</div>
                </div>
                <div class="pp-counts">
                    <div class="pp-chip">Ordered <span class="pp-chip__num">{{ fmt_qty($sum['ordered_total']) }}</span></div>
                    <div class="pp-chip pp-chip--info">Shipped <span class="pp-chip__num">{{ fmt_qty($sum['shipped_total']) }}</span></div>
                    <div class="pp-chip pp-chip--success">Received <span class="pp-chip__num">{{ fmt_qty($sum['received_total']) }}</span></div>
                    <div class="pp-chip pp-chip--primary">In-Transit <span class="pp-chip__num">{{ fmt_qty(max($sum['in_transit'],0)) }}</span></div>
                    <div class="pp-chip pp-chip--warning">Remaining <span class="pp-chip__num">{{ fmt_qty(max($sum['remaining'],0)) }}</span></div>
                </div>
            </div>

            <div class="pp-block__body">
                @if(empty($lines))
                    <div class="pp-empty">No lines for this PO.</div>
                @else
                    <div class="accordion" id="acc-{{ \Illuminate\Support\Str::slug($poNo) }}">
                        @foreach($lines as $idx => $ln)
                            @php
                                $cid = 'line-'.$poNo.'-'.$ln['line_no'];
                            @endphp
                            <div class="pp-line">
                                <div class="pp-line__main pp-toggle" data-target="{{ $cid }}">
                                    <div class="pp-line__meta">
                                        <div class="pp-line__title">Line {{ $ln['line_no'] }} &middot; {{ $ln['model_code'] ?? '-' }}</div>
                                        <div class="pp-line__subtitle">{{ $ln['item_desc'] ?? '-' }} &middot; Ordered: <strong>{{ fmt_qty($ln['ordered']) }}</strong> {{ $ln['uom'] ?? '' }}</div>
                                    </div>
                                    <div class="pp-line__counts">
                                        <span class="pp-chip">Shipped: {{ fmt_qty($ln['shipped_total']) }}</span>
                                        <span class="pp-chip pp-chip--success">Received: {{ fmt_qty($ln['received_total']) }}</span>
                                        <span class="pp-chip pp-chip--primary">In-Transit: {{ fmt_qty($ln['in_transit']) }}</span>
                                        <span class="pp-chip pp-chip--warning">Remaining: {{ fmt_qty($ln['remaining']) }}</span>
                                        <button type="button" class="pp-btn-details pp-toggle" data-target="{{ $cid }}">Details</button>
                                    </div>
                                </div>
                                <div id="{{ $cid }}" class="pp-detail" style="display:none; background:#f8fafc">
                                    <div class="pp-detail__body">
                                        <div class="table-responsive" style="overflow-x:auto;">
                                            <table class="pp-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th class="text-end">Qty</th>
                                                        <th class="text-end">Shipped Total</th>
                                                        <th class="text-end">Received Total</th>
                                                        <th class="text-end">In-Transit</th>
                                                        <th class="text-end">Remaining (Actual)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                @forelse($ln['events'] as $ev)
                                                    <tr>
                                                        <td>{{ $ev['date'] ?? '-' }}</td>
                                                        <td>
                                                            @if($ev['type'] === 'shipment')
                                                                <span class="pp-tag pp-tag--info">Shipment</span>
                                                            @else
                                                                <span class="pp-tag pp-tag--success">GR</span>
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
                                                        <td colspan="7" class="text-muted fst-italic">No Shipment/GR events yet.</td>
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
        <div class="alert alert-info">No purchase orders found.</div>
@endforelse

    <div class="d-flex justify-content-end">
        {{ $headers->links() }}
    </div>
</div>
@endsection

@push('styles')
<style>
/* Modern styling matching other pages (no AdminLTE components) */
.pp-card { border:1px solid #dfe4f3; border-radius:16px; background:#ffffff; box-shadow:0 20px 45px -36px rgba(15,23,42,.35); }
.pp-card__body { padding:14px 16px; }
.pp-input, .pp-select { border:1px solid #cbd5f5; border-radius:12px; padding:10px 14px; font-size:13px; width:100%; transition:border-color .2s ease, box-shadow .2s ease; }
.pp-input:focus, .pp-select:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.15); outline:none; }
.pp-btn { border:none; border-radius:12px; padding:10px 16px; font-weight:700; font-size:13px; }
.pp-btn--primary { background:#2563eb; color:#fff; }
.pp-hint { color:#64748b !important; }

.pp-block { border:1px solid #e6ebf5; border-radius:18px; background:#ffffff; box-shadow:0 26px 48px -40px rgba(15,23,42,.45); overflow:hidden; }
.pp-block__header { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 18px; border-bottom:1px solid #eef2fb; }
.pp-po-title { font-size:16px; font-weight:800; color:#0f172a; }
.pp-po-subtitle { font-size:12px; color:#64748b; }
.pp-counts { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.pp-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#eef2fb; color:#334155; }
.pp-chip__num { margin-left:6px; font-weight:800; }
.pp-chip--info { background:#e8f0ff; color:#1d4ed8; border:1px solid #c9dcff; }
.pp-chip--success { background:#e8faee; color:#15803d; border:1px solid #bbf7d0; }
.pp-chip--primary { background:rgba(37,99,235,.12); color:#1d4ed8; border:1px solid rgba(37,99,235,.25); }
.pp-chip--warning { background:rgba(245,158,11,.16); color:#92400e; border:1px solid rgba(245,158,11,.35); }

.pp-block__body { padding:14px 16px 18px; }
.pp-empty { color:#94a3b8; font-style:italic; padding:8px 2px; }

.pp-line { border:1px solid #eef2fb; border-radius:14px; margin-bottom:10px; overflow:hidden; background:linear-gradient(135deg,#f8fbff 0%, #ffffff 100%); }
.pp-line__main { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 16px; }
.pp-line__main{ cursor:pointer; }
.pp-line__meta { display:flex; flex-direction:column; }
.pp-line__title { font-weight:700; color:#0f172a; }
.pp-line__subtitle { font-size:12px; color:#64748b; }
.pp-line__counts { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.pp-btn-details{ border:1px solid #3b82f6; color:#1d4ed8; background:rgba(59,130,246,0.08); border-radius:999px; padding:6px 12px; font-size:12px; font-weight:700; }
.pp-btn-details:hover{ background:#2563eb; color:#fff; }

.pp-detail{ border-top:1px solid #eef2fb; background:#f8fafc; }
.pp-detail__body { padding:8px 12px 12px; }
.pp-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
.pp-table thead th{ background:#f8fbff; padding:10px 12px; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
.pp-table tbody td{ padding:12px; border-top:1px solid #e5eaf5; color:#1f2937; }
.pp-tag { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
.pp-tag--info { background:#e8f0ff; color:#1d4ed8; border:1px solid #c9dcff; }
.pp-tag--success { background:#e8faee; color:#15803d; border:1px solid #bbf7d0; }
</style>
@endpush

@push('scripts')
<script>
(function(){
  function hideAll(){ document.querySelectorAll('.pp-detail').forEach(function(el){ el.style.display='none'; }); }
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.pp-toggle');
    if(!btn) return;
    var id = btn.getAttribute('data-target');
    if(!id) return;
    var el = document.getElementById(id);
    if(!el) return;
    var open = el.style.display !== 'none';
    hideAll();
    el.style.display = open ? 'none' : '';
  });
})();
</script>
@endpush
