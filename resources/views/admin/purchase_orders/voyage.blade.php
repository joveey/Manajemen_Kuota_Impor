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
                <td><input type="text" class="form-control form-control-sm s-remark" value="{{ $sp->voyage_remark ?? '' }}"></td>
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

</div>
@endsection

@push('scripts')
<script>
(function(){
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
})();
</script>
@endpush
