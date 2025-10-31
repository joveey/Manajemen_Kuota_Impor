<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Quota Pipeline</strong>
    <a href="{{ route('admin.mapping.unmapped.page') }}" class="btn btn-sm btn-outline-primary">View</a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      @php
        $steps = [
          ['label'=>'Unmapped Model','value'=>$metrics['unmapped'] ?? 0,'hint'=>'No HS/PK yet','route'=>route('admin.mapping.unmapped.page')],
          ['label'=>'Mapped','value'=>$metrics['mapped'] ?? 0,'hint'=>'Model mapped','route'=>route('admin.mapping.mapped.page')],
          ['label'=>'Open PO','value'=>number_format($metrics['open_po_qty'] ?? 0),'hint'=>'Outstanding qty','route'=>route('admin.purchase-orders.index')],
          ['label'=>'In-Transit','value'=>number_format($metrics['in_transit_qty'] ?? 0),'hint'=>'Invoice - GR','route'=>route('admin.purchase-orders.index')],
          ['label'=>'GR (Actual)','value'=>number_format($metrics['gr_qty'] ?? 0),'hint'=>'Received','route'=>\Illuminate\Support\Facades\Route::has('admin.imports.gr.index') ? route('admin.imports.gr.index') : '#'],
        ];
      @endphp
      @foreach($steps as $s)
        <div class="col-6 col-md-4 col-lg">
          <a href="{{ $s['route'] }}" class="text-decoration-none">
            <div class="p-3 border rounded-3 h-100" style="background:#fff">
              <div class="small text-muted">{{ $s['label'] }}</div>
              <div class="h4 mb-0">{{ $s['value'] }}</div>
              <div class="text-muted small">{{ $s['hint'] }}</div>
            </div>
          </a>
        </div>
      @endforeach
    </div>
  </div>
</div>
