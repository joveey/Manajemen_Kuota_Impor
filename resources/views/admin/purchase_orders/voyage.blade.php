@extends('layouts.admin')

@section('title', 'Voyage — PO '.$summary['po_number'])

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Purchase Orders</a></li>
  <li class="breadcrumb-item active">Voyage — PO {{ $summary['po_number'] }}</li>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/voyage.css') }}">
@endpush

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="mb-1">Voyage — PO {{ $summary['po_number'] }}</h5>
      <div class="text-muted small">Vendor: {{ $summary['vendor_number'] ?: '-' }} {{ $summary['vendor_name'] ?: '-' }}</div>
    </div>
    <a href="{{ route('admin.purchase-orders.document', ['poNumber' => $summary['po_number']]) }}" class="btn btn-outline-secondary">
      Back to PO Details
    </a>
  </div>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <form method="GET" action="{{ route('admin.purchase-orders.voyage.index', ['po' => $summary['po_number']]) }}" class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <div class="flex-grow-1">
      <input id="search" name="q" type="text" class="form-control" value="{{ request('q') }}" placeholder="Search material / description / line">
    </div>
    <div>
      <select id="status-filter" name="status" class="form-select">
        <option value="">All statuses</option>
        @php $cur = (string) request('status', ''); @endphp
        <option value="Booked" {{ $cur==='Booked'?'selected':'' }}>Booked</option>
        <option value="On Vessel" {{ $cur==='On Vessel'?'selected':'' }}>On Vessel</option>
        <option value="Arrived" {{ $cur==='Arrived'?'selected':'' }}>Arrived</option>
        <option value="Shipping" {{ $cur==='Shipping'?'selected':'' }}>Shipping</option>
      </select>
    </div>
    <div>
      <input id="eta-month" name="eta_month" type="month" class="form-control" value="{{ request('eta_month') }}" placeholder="ETA (month)">
    </div>
    <div>
      <button type="submit" class="btn btn-outline-secondary">Filter</button>
    </div>
  </form>

  <div class="voyage-desktop">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 d-none">
      <span class="text-muted small me-1">Quick actions:</span>
      <button type="button" class="btn btn-outline-secondary btn-sm qa-fill-bl" title="Isi ke bawah nilai BL dari nilai terakhir">Fill BL ↓</button>
      <button type="button" class="btn btn-outline-secondary btn-sm qa-fill-factory" title="Isi ke bawah nilai Factory dari nilai terakhir">Fill Factory ↓</button>
      <button type="button" class="btn btn-outline-secondary btn-sm qa-copy-etd-eta" title="Copy ETD to ETA when ETA is empty">Copy ETD -> ETA</button>
      <button type="button" class="btn btn-outline-secondary btn-sm qa-parent-to-splits" title="Copy parent row values to all empty splits">Parent -> Splits (empty)</button>
      <button type="button" class="btn btn-outline-secondary btn-sm qa-status-finish" title="Set status Finish untuk baris yang punya BL">Set Finish if BL</button>
    </div>
    <div class="voyage-table-wrapper">
      <table class="table table-sm voyage-table align-middle">
        <thead class="table-light">
          <tr>
            <th class="col-line sticky-col line sticky-shadow">Line</th>
            <th class="col-mat  sticky-col mat sticky-shadow">Material</th>
            <th class="col-desc">Item Desc</th>
            <th class="col-qty">Qty</th>
            <th class="col-date">Delivery</th>
            <th class="col-text">BL</th>
            <th class="col-date">ETD</th>
            <th class="col-date">ETA</th>
            <th class="col-text">Factory</th>
            <th class="col-text">Status</th>
            <th class="col-remark">Remark</th>
          </tr>
        </thead>
        <tbody id="voyage-rows">
          @foreach ($lines as $ln)
          <tr data-line-id="{{ $ln->id }}">
            <td class="sticky-col line sticky-shadow">{{ $ln->line_no ?: '-' }}</td>
            <td class="sticky-col mat  sticky-shadow">{{ $ln->material ?? '-' }}</td>
            <td class="text-truncate" title="{{ $ln->item_desc }}">{{ $ln->item_desc ?? '-' }}</td>
            @php $qtyRemain = isset($ln->qty_remaining) ? (float)$ln->qty_remaining : (float)($ln->qty_ordered ?? 0); @endphp
            <td>{{ number_format($qtyRemain, 0) }}</td>
            <td>{{ $ln->delivery_date ? \Illuminate\Support\Carbon::parse($ln->delivery_date)->format('d-m-Y') : '-' }}</td>
            <td><input type="text"  class="form-control form-control-sm v-bl"       value="{{ $ln->bl ?? '' }}"></td>
            <td><input type="text" class="form-control form-control-sm v-etd datepicker"      value="{{ $ln->etd ?? '' }}" placeholder="dd-mm-yyyy"></td>
            <td><input type="text" class="form-control form-control-sm v-eta datepicker"      value="{{ $ln->eta ?? '' }}" placeholder="dd-mm-yyyy"></td>
            <td><input type="text"  class="form-control form-control-sm v-factory" value="{{ $ln->factory ?? '' }}"></td>
            <td>
              @php $s = strtolower((string)($ln->mstatus ?? '')); @endphp
              <select class="form-select form-select-sm v-status">
                <option value=""></option>
                <option value="Finish" {{ $s==='finish' ? 'selected' : '' }}>Finish</option>
                <option value="Not Ship Yet" {{ $s==='not ship yet' ? 'selected' : '' }}>Not Ship Yet</option>
                <option value="Shipping" {{ $s==='shipping' ? 'selected' : '' }}>Shipping</option>
              </select>
            </td>
            <td><input type="text"  class="form-control form-control-sm v-remark"  value="{{ $ln->remark ?? '' }}"></td>
          </tr>
          @php $subs = ($splitsByLine[$ln->id] ?? []); @endphp
          @if(!empty($subs))
            @foreach ($subs as $sp)
              <tr class="split-row text-muted" data-line-id="{{ $ln->id }}" data-split-id="{{ $sp->id }}">
                <td></td>
                <td colspan="2">Split</td>
                <td>{{ number_format((float)($sp->qty ?? 0),0) }}</td>
                <td>{{ $ln->delivery_date ? \Illuminate\Support\Carbon::parse($ln->delivery_date)->format('d-m-Y') : '-' }}</td>
                <td><input type="text" class="form-control form-control-sm s-bl" value="{{ $sp->voyage_bl ?? '' }}"></td>
                <td><input type="text" class="form-control form-control-sm s-etd datepicker" value="{{ $sp->voyage_etd ?? '' }}" placeholder="dd-mm-yyyy"></td>
                <td><input type="text" class="form-control form-control-sm s-eta datepicker" value="{{ $sp->voyage_eta ?? '' }}" placeholder="dd-mm-yyyy"></td>
                <td><input type="text" class="form-control form-control-sm s-factory" value="{{ $sp->voyage_factory ?? '' }}"></td>
                <td>
                  @php $s = strtolower((string)($sp->voyage_status ?? '')); @endphp
                  <select class="form-select form-select-sm s-status">
                    <option value=""></option>
                    <option value="Finish" {{ $s==='finish' ? 'selected' : '' }}>Finish</option>
                    <option value="Not Ship Yet" {{ $s==='not ship yet' ? 'selected' : '' }}>Not Ship Yet</option>
                    <option value="Shipping" {{ $s==='shipping' ? 'selected' : '' }}>Shipping</option>
                  </select>
                </td>
                <td>
                  <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm s-remark" value="{{ $sp->voyage_remark ?? '' }}">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-split-move" data-line-id="{{ $ln->id }}" data-split-id="{{ $sp->id }}" data-qty="{{ (int)($sp->qty ?? 0) }}">Move</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-split-delete" title="Delete this split">Delete</button>
                  </div>
                </td>
              </tr>
              
            @endforeach
          @endif
          <tr class="split-row split-new" data-line-id="{{ $ln->id }}" data-split-id="">
            <td></td>
            <td colspan="2"><em class="text-muted">Add split</em></td>
            <td><input type="number" min="0" step="1" class="form-control form-control-sm s-qty" value=""></td>
            <td>{{ $ln->delivery_date ? \Illuminate\Support\Carbon::parse($ln->delivery_date)->format('d-m-Y') : '-' }}</td>
            <td><input type="text"  class="form-control form-control-sm s-bl"       value=""></td>
            <td><input type="text" class="form-control form-control-sm s-etd datepicker"      value="" placeholder="dd-mm-yyyy"></td>
            <td><input type="text" class="form-control form-control-sm s-eta datepicker"      value="" placeholder="dd-mm-yyyy"></td>
            <td><input type="text"  class="form-control form-control-sm s-factory" value=""></td>
            <td>
              <select class="form-select form-select-sm s-status">
                <option value=""></option>
                <option value="Finish">Finish</option>
                <option value="Not Ship Yet">Not Ship Yet</option>
                <option value="Shipping">Shipping</option>
              </select>
            </td>
            <td><input type="text"  class="form-control form-control-sm s-remark"  value=""></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-2">{{ $lines->withQueryString()->links() }}</div>
  </div>

  {{-- datalist no longer used (replaced by select) --}}

  <div class="voyage-mobile">
    @foreach ($lines as $ln)
      <div class="voyage-card" data-line-id="{{ $ln->id }}">
        <div class="d-flex justify-content-between mb-2">
          <div class="fw-semibold">Line {{ $ln->line_no ?: '-' }} — {{ $ln->material ?? '-' }}</div>
          <div class="text-muted small">{{ $ln->delivery_date ? \Illuminate\Support\Carbon::parse($ln->delivery_date)->format('d-m-Y') : '-' }}</div>
        </div>
        <div class="text-muted small mb-2 text-truncate" title="{{ $ln->item_desc }}">{{ $ln->item_desc ?? '-' }}</div>

        <div class="row row-gap">
          <div class="col-6">
            <label>BL</label>
            <input type="text" class="form-control form-control-sm v-bl" value="{{ $ln->bl ?? '' }}">
          </div>
          <div class="col-6">
            <label>Factory</label>
            <input type="text" class="form-control form-control-sm v-factory" value="{{ $ln->factory ?? '' }}">
          </div>
          <div class="col-6">
            <label>ETD</label>
            <input type="text" class="form-control form-control-sm v-etd datepicker" value="{{ $ln->etd ?? '' }}" placeholder="dd-mm-yyyy">
          </div>
          <div class="col-6">
            <label>ETA</label>
            <input type="text" class="form-control form-control-sm v-eta datepicker" value="{{ $ln->eta ?? '' }}" placeholder="dd-mm-yyyy">
          </div>
          <div class="col-6">
            <label>Status</label>
            @php $s = strtolower((string)($ln->mstatus ?? '')); @endphp
            <select class="form-select form-select-sm v-status">
              <option value=""></option>
              <option value="Finish" {{ $s==='finish' ? 'selected' : '' }}>Finish</option>
              <option value="Not Ship Yet" {{ $s==='not ship yet' ? 'selected' : '' }}>Not Ship Yet</option>
              <option value="Shipping" {{ $s==='shipping' ? 'selected' : '' }}>Shipping</option>
            </select>
          </div>
          <div class="col-12">
            <label>Remark</label>
            <input type="text" class="form-control form-control-sm v-remark" value="{{ $ln->remark ?? '' }}">
          </div>
        </div>
      </div>
    @endforeach
    <div class="mt-2">{{ $lines->withQueryString()->links() }}</div>
  </div>

  <div class="bottom-bar">
    <form id="voyageBulkForm" method="POST" action="{{ route('admin.purchase-orders.voyage.bulk', ['po' => $poNumber]) }}">
      @csrf
      <input type="hidden" name="splits_json" id="splits_json" value="">
      <input type="hidden" name="rows_json" id="rows_json" value="">
      <button type="button" id="saveChanges" class="btn btn-primary btn-sm">Save changes</button>
    </form>
  </div>

  <!-- Move Split Modal -->
  <div class="modal fade" id="moveSplitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content move-modal">
        <div class="modal-header">
          <h6 class="modal-title">Move Split to Another Quota</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="moveSplitForm" method="POST" action="{{ route('admin.purchase-orders.voyage.move', ['po'=>$poNumber]) }}">
          @csrf
          <input type="hidden" name="line_id" id="mv_line_id" value="">
          <input type="hidden" name="split_id" id="mv_split_id" value="">
          <input type="hidden" name="source_quota_id" id="mv_source_id" value="">
          <input type="hidden" name="move_qty" id="mv_qty_val" value="">
          <div class="modal-body">
            <div class="d-flex align-items-center flex-wrap gap-2">
              <div class="move-label text-muted">Source</div>
              <div class="quota-badge" id="mv_source_label"><i class="fa-solid fa-box-archive"></i><span>—</span></div>
              <div class="move-label text-muted ms-2">Qty</div>
              <div class="mi-qty-pill" id="mv_qty_pill">0</div>
            </div>
            <div class="mt-3">
              <label class="move-label text-muted mb-1">Target quota</label>
              <select class="form-select js-mv-quota-select" id="mv_target_select" name="target_quota_id" required style="width:100%"></select>
              <div class="text-muted small mt-1">Remaining: <span id="mv_target_rem">-</span></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Move</button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  // Provide data for Move modal
  var quotaOptionsByLine = @json($quotaOptionsByLine);
  var sourceQuotaByLine = @json($sourceQuotaByLine);
  function collect(){
    var rows=[]; var splits=[];
    document.querySelectorAll('tr[data-line-id]')?.forEach(function(tr){
      var lineId = tr.getAttribute('data-line-id');
      // parent line inputs
      var bl = tr.querySelector('.v-bl')?.value; var etd = tr.querySelector('.v-etd')?.value; var eta = tr.querySelector('.v-eta')?.value; var fac = tr.querySelector('.v-factory')?.value; var st = tr.querySelector('.v-status')?.value; var rm = tr.querySelector('.v-remark')?.value;
      if (bl!==undefined || etd!==undefined || eta!==undefined || fac!==undefined || st!==undefined || rm!==undefined){
        rows.push({line_id:parseInt(lineId), bl:bl, etd:etd, eta:eta, factory:fac, status:st, remark:rm});
      }
    });
    document.querySelectorAll('tr.split-row')?.forEach(function(tr){
      var lineId = parseInt(tr.getAttribute('data-line-id')||'0');
      var id = parseInt(tr.getAttribute('data-split-id')||'0');
      // if row is marked for deletion, only send id + delete flag
      if (id>0 && (tr.getAttribute('data-delete')==='1' || tr.classList.contains('is-deleting'))){
        splits.push({id:id, line_id: lineId, delete: true});
        return;
      }
      var qty = tr.querySelector('.s-qty')?.value;
      var bl = tr.querySelector('.s-bl')?.value; var etd = tr.querySelector('.s-etd')?.value; var eta = tr.querySelector('.s-eta')?.value; var fac = tr.querySelector('.s-factory')?.value; var st = tr.querySelector('.s-status')?.value; var rm = tr.querySelector('.s-remark')?.value;
      if (isNaN(lineId) || lineId<=0) return;
      // only post rows that have any value filled or existing id
      var hasVal = (qty && qty!=='' && Number(qty)>0) || bl||etd||eta||fac||st||rm||id>0;
      if (!hasVal) return;
      var payload = {id: id>0? id: undefined, line_id: lineId, qty: qty||0, bl: bl, etd: etd, eta: eta, factory: fac, status: st, remark: rm};
      splits.push(payload);
    });
    document.getElementById('rows_json').value = JSON.stringify(rows);
    document.getElementById('splits_json').value = JSON.stringify(splits);
  }
  document.getElementById('saveChanges')?.addEventListener('click', function(){
    collect();
    document.getElementById('voyageBulkForm').submit();
  });
  // Quick actions helpers
  function fillDown(selector){
    var inputs = Array.from(document.querySelectorAll(selector));
    var last = '';
    inputs.forEach(function(el){
      var v = (el.value||'').trim();
      if (v) { last = v; return; }
      if (last) { el.value = last; el.dispatchEvent(new Event('input',{bubbles:true})); }
    });
  }
  function copyEtdToEta(){
    document.querySelectorAll('.v-etd,.s-etd').forEach(function(etd){
      var tr = etd.closest('tr'); if (!tr) return;
      var eta = tr.querySelector('.v-eta, .s-eta'); if (!eta) return;
      if (!eta.value && etd.value){ eta.value = etd.value; eta.dispatchEvent(new Event('input',{bubbles:true})); }
    });
  }
  function parentToSplitsOnlyEmpty(){
    document.querySelectorAll('tr[data-line-id]:not(.split-row)').forEach(function(pr){
      var lineId = pr.getAttribute('data-line-id');
      var vals = {
        bl: pr.querySelector('.v-bl')?.value || '',
        etd: pr.querySelector('.v-etd')?.value || '',
        eta: pr.querySelector('.v-eta')?.value || '',
        fac: pr.querySelector('.v-factory')?.value || '',
        st:  pr.querySelector('.v-status')?.value || '',
        rm:  pr.querySelector('.v-remark')?.value || ''
      };
      if (!lineId) return;
      document.querySelectorAll('tr.split-row[data-line-id="'+lineId+'"]')?.forEach(function(sr){
        function setIfEmpty(sel,val){ var el=sr.querySelector(sel); if (el && !el.value && val){ el.value=val; el.dispatchEvent(new Event('input',{bubbles:true})); } }
        setIfEmpty('.s-bl', vals.bl); setIfEmpty('.s-etd', vals.etd); setIfEmpty('.s-eta', vals.eta); setIfEmpty('.s-factory', vals.fac); setIfEmpty('.s-remark', vals.rm);
        var ss=sr.querySelector('.s-status'); if (ss && !ss.value && vals.st){ ss.value=vals.st; ss.dispatchEvent(new Event('change',{bubbles:true})); }
      });
    });
  }
  function setFinishIfBL(){
    document.querySelectorAll('tr[data-line-id]')?.forEach(function(tr){
      var bl = tr.querySelector('.v-bl, .s-bl'); var st = tr.querySelector('.v-status, .s-status');
      if (bl && bl.value && st && !st.value){ st.value = 'Finish'; st.dispatchEvent(new Event('change',{bubbles:true})); }
    });
  }
  document.querySelector('.qa-fill-bl')?.addEventListener('click', function(){ fillDown('.v-bl, .s-bl'); });
  document.querySelector('.qa-fill-factory')?.addEventListener('click', function(){ fillDown('.v-factory, .s-factory'); });
  document.querySelector('.qa-copy-etd-eta')?.addEventListener('click', copyEtdToEta);
  document.querySelector('.qa-parent-to-splits')?.addEventListener('click', parentToSplitsOnlyEmpty);
  document.querySelector('.qa-status-finish')?.addEventListener('click', setFinishIfBL);

  // Keyboard: Enter/Shift+Enter navigates between editable cells
  var editSelectors = '.v-bl,.v-etd,.v-eta,.v-factory,.v-status,.v-remark,.s-qty,.s-bl,.s-etd,.s-eta,.s-factory,.s-status,.s-remark';
  document.querySelector('.voyage-table')?.addEventListener('keydown', function(e){
    if (e.key !== 'Enter') return;
    var focusables = Array.from(document.querySelectorAll(editSelectors));
    var idx = focusables.indexOf(document.activeElement);
    if (idx === -1) return;
    e.preventDefault();
    var next = e.shiftKey ? focusables[idx-1] : focusables[idx+1];
    if (next) { next.focus(); if (next.select) try{ next.select(); }catch(_){} }
  });

  // Delete/Undo for existing splits
  document.querySelector('.voyage-table')?.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-split-delete');
    if (!btn) return;
    var tr = btn.closest('tr.split-row');
    if (!tr) return;
    var isDeleting = tr.classList.contains('is-deleting');
    if (!isDeleting) {
      // mark as deleting
      tr.classList.add('is-deleting');
      tr.dataset.delete = '1';
      btn.classList.remove('btn-outline-danger');
      btn.classList.add('btn-outline-secondary');
      btn.textContent = 'Undo';
      // collapse move panel if open
      var collapse = tr.nextElementSibling; if (collapse && collapse.classList.contains('collapse')) {
        try { var c = new bootstrap.Collapse(collapse, {toggle:false}); c.hide(); } catch(_) {}
      }
    } else {
      // unmark
      tr.classList.remove('is-deleting');
      delete tr.dataset.delete;
      btn.classList.add('btn-outline-danger');
      btn.classList.remove('btn-outline-secondary');
      btn.textContent = 'Delete';
    }
  });

  // Move modal: open + populate
  function openMoveModal(lineId, splitId, qty){
    var src = sourceQuotaByLine[lineId] || null;
    var opts = quotaOptionsByLine[lineId] || [];
    // Set hidden inputs
    document.getElementById('mv_line_id').value = lineId;
    document.getElementById('mv_split_id').value = splitId;
    document.getElementById('mv_qty_val').value = qty;
    document.getElementById('mv_qty_pill').textContent = (qty||0).toLocaleString();
    var srcId = src ? src.id : '';
    document.getElementById('mv_source_id').value = srcId;
    var srcLabelEl = document.getElementById('mv_source_label');
    srcLabelEl.querySelector('span').textContent = src ? src.label : 'Not found';
    // compute source remaining if available
    var srcRem = '-';
    if (srcId) {
      for (var i=0;i<opts.length;i++){ if (opts[i].id===srcId || opts[i].id===parseInt(srcId)){ srcRem = (opts[i].rem||0).toLocaleString(); break; } }
    }
    var srcRemEl = document.getElementById('mv_source_rem');
    if (srcRemEl) { srcRemEl.textContent = 'Rem: ' + srcRem; }
    // Build target options
    var $sel = jQuery('#mv_target_select');
    // destroy previous select2
    if ($sel.hasClass('select2-hidden-accessible')) { $sel.select2('destroy'); }
    $sel.empty();
    $sel.append(new Option('Select quota','', true, false));
    opts.forEach(function(o){
      var text = o.quota_number + ' ('+o.start+'..'+o.end+')';
      var opt = new Option(text, o.id, false, false);
      jQuery(opt).attr({'data-number':o.quota_number,'data-start':o.start,'data-end':o.end,'data-rem':o.rem});
      $sel.append(opt);
    });
    // init select2
    $sel.select2({
      theme: 'bootstrap-5',
      width: 'resolve',
      dropdownParent: jQuery('#moveSplitModal'),
      dropdownAutoWidth: true,
      placeholder: 'Select quota',
      minimumResultsForSearch: Infinity,
      selectionCssClass: 'quota-selection',
      dropdownCssClass: 'quota-dropdown',
      templateResult: function(data){
        if (!data.id) return data.text;
        var $opt = jQuery(data.element);
        var num = $opt.data('number');
        var start = $opt.data('start');
        var end = $opt.data('end');
        var rem = $opt.data('rem');
        return jQuery('<div class="quota-option"><div class="qo-number">'+(num||data.text)+'</div><div class="qo-range">'+(start? (start+' .. '+end):'')+' • Rem: '+(rem!=null? rem.toLocaleString(): '-')+'</div></div>');
      },
      templateSelection: function(data){
        if (!data.id) return data.text;
        var $opt = jQuery(data.element);
        var num = $opt.data('number');
        var start = $opt.data('start');
        var end = $opt.data('end');
        var rem = $opt.data('rem');
        // update live remaining label under select
        var tgtRemEl = document.getElementById('mv_target_rem');
        if (tgtRemEl) { tgtRemEl.textContent = rem!=null? Number(rem).toLocaleString() : '-'; }
        return jQuery('<span class="qo-pill">'+(num||data.text)+'<span class="qo-pill-range">'+(start? ' • '+start+'..'+end:'')+' • Rem: '+(rem!=null? Number(rem).toLocaleString():'-')+'</span></span>');
      },
      escapeMarkup: function(m){ return m; }
    });
    // Remove duplicated 'Rem' text from option and selection renderers
    function pruneRemFromSelectUI(){
      try {
        // Clean selection pill
        var $ranges = jQuery('#moveSplitModal').find('.select2-selection .qo-pill-range');
        $ranges.each(function(){
          var t = jQuery(this).text();
          var idx = t.indexOf('Rem:');
          if (idx >= 0) { jQuery(this).text(t.substring(0, idx).trim()); }
        });
        // Clean options in dropdown
        jQuery('#moveSplitModal').find('.select2-results__option .quota-option .qo-range').each(function(){
          var t = jQuery(this).text();
          var idx = t.indexOf('Rem:');
          if (idx >= 0) { jQuery(this).text(t.substring(0, idx).trim()); }
        });
      } catch(e) { /* no-op */ }
    }
    pruneRemFromSelectUI();
    $sel.on('select2:open', pruneRemFromSelectUI);
    $sel.on('select2:select', function(e){
      var rem = jQuery(e.params.data.element).data('rem');
      var tgtRemEl = document.getElementById('mv_target_rem');
      if (tgtRemEl) { tgtRemEl.textContent = rem!=null? Number(rem).toLocaleString() : '-'; }
      pruneRemFromSelectUI();
    });
    // show modal
    var modal = new bootstrap.Modal(document.getElementById('moveSplitModal'));
    modal.show();
  }

  document.querySelector('.voyage-table')?.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-split-move');
    if (!btn) return;
    var lineId = parseInt(btn.getAttribute('data-line-id')||'0');
    var splitId = parseInt(btn.getAttribute('data-split-id')||'0');
    var qty = parseInt(btn.getAttribute('data-qty')||'0');
    if (lineId>0 && splitId>0) { openMoveModal(lineId, splitId, qty); }
  });
})();
</script>
@endpush
