{{-- resources/views/admin/kuota/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Quota Management')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Quota Management</li>
@endsection

@push('styles')
<style>
    .quota-page {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    .quota-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }

    .quota-header__title {
        font-size: 26px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .quota-header__subtitle {
        margin-top: 6px;
        color: #64748b;
        font-size: 13px;
        max-width: 520px;
    }

    .quota-actions {
        display: flex;
        gap: 12px;
    }

    .action-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .action-pill--outline {
        background: rgba(148, 163, 184, 0.1);
        color: #1f2937;
        border-color: rgba(148, 163, 184, 0.35);
    }

    .action-pill--outline:hover {
        background: rgba(148, 163, 184, 0.16);
    }

    .action-pill--primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 18px 36px -28px rgba(37, 99, 235, 0.75);
    }

    .action-pill--primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .info-banner {
        display: flex;
        gap: 14px;
        background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 18px 22px;
        color: #334155;
    }

    .info-banner__icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(37, 99, 235, 0.16);
        color: #1d4ed8;
        display: grid;
        place-items: center;
        font-size: 16px;
    }

    .info-banner__title {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .table-shell {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid #e6ebf5;
        box-shadow: 0 32px 64px -48px rgba(15, 23, 42, 0.45);
        overflow: hidden;
    }

    .quota-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .quota-table thead th {
        background: #f8faff;
        padding: 16px 20px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        border-bottom: 1px solid #e6ebf5;
    }

    .quota-table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #eef2fb;
        font-size: 13px;
        color: #1f2937;
        vertical-align: top;
    }

    .badge-soft {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(148, 163, 184, 0.12);
        border: 1px solid rgba(148, 163, 184, 0.32);
        color: #334155;
    }

    .btn-toggle {
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 12px;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-top: 16px;
    }

    .stat-card-modern {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 12px 14px;
        background: #ffffff;
    }

    .stat-card-modern__label {
        display: block;
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .stat-card-modern__value {
        display: block;
        font-weight: 700;
        font-size: 20px;
        color: #0f172a;
    }

    .table-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .action-icon { border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; padding: 6px 10px; }
    .action-icon--delete { color: #b91c1c; }
</style>
@endpush

@section('content')
<div class="quota-page">
  <div class="quota-header">
    <div>
      <h1 class="quota-header__title">Quota Management</h1>
      <p class="quota-header__subtitle">Monitor government allocation and track forecast vs actual usage.</p>
    </div>
    <div class="quota-actions">
      <a href="{{ route('admin.imports.quotas.index') }}" class="action-pill action-pill--primary">
        <i class="fas fa-file-import"></i>
        Import Quotas
      </a>
      <a href="{{ route('admin.quotas.export') }}" class="action-pill action-pill--outline">
        <i class="fas fa-download"></i>
        Export CSV
      </a>
    </div>
  </div>

  <div class="info-banner">
    <div class="info-banner__icon"><i class="fas fa-circle-info"></i></div>
    <div>
      <div class="info-banner__title">Forecast vs Actual</div>
      <div class="small text-muted">Switch between forecast and actual metrics to validate planning against realized imports.</div>
    </div>
  </div>

  <div class="table-shell">
    <div class="d-flex justify-content-between align-items-center p-3">
      <div class="d-flex gap-2">
        <button id="mode-forecast" type="button" class="btn btn-sm btn-primary btn-toggle">Forecast</button>
        <button id="mode-actual" type="button" class="btn btn-sm btn-outline-primary btn-toggle">Actual</button>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary filter-pill" data-filter="all">All</button>
        <button class="btn btn-sm btn-outline-secondary filter-pill" data-filter="safe">Safe</button>
        <button class="btn btn-sm btn-outline-secondary filter-pill" data-filter="warn">Warning</button>
        <button class="btn btn-sm btn-outline-secondary filter-pill" data-filter="zero">Depleted</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="quota-table table">
        <thead>
          <tr>
            <th>#</th>
            <th>Quota No</th>
            <th>Name</th>
            <th class="text-end">Allocation</th>
            <th class="text-end col-forecast">Consumed (F)</th>
            <th class="text-end col-forecast">Remaining (F)</th>
            <th class="text-end col-actual d-none">Consumed (A)</th>
            <th class="text-end col-actual d-none">Remaining (A)</th>
            <th>Period</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
            @forelse($quotas as $quota)
                @php
                    $allocation = (int) ($quota->total_allocation ?? 0);
                    $forecastRemaining = (int) ($quota->forecast_remaining ?? 0);
                    $status = ['SAFE', '#d1fae5', '#047857', '#10b981'];
                    $state = 'safe';
                    $consumed = max($allocation - $forecastRemaining, 0);
                    $pct = $allocation > 0 ? round(($consumed / $allocation) * 100) : 0; // 0..100
                    $ratio = $allocation > 0 ? ($forecastRemaining / $allocation) : 0;   // 0..1
                    if ($forecastRemaining <= 0) { $status = ['DEPLETED','#fee2e2','#b91c1c','#ef4444']; $state='zero'; }
                    elseif ($ratio < 0.5 && $ratio >= 0.2) { $status = ['WARNING','#fef3c7','#92400e','#f59e0b']; $state='warn'; }
                    elseif ($ratio < 0.2) { $status = ['WARNING','#fef3c7','#92400e','#f59e0b']; $state='warn'; }
                    else { $status = ['SAFE','#d1fae5','#047857','#10b981']; $state='safe'; }
                @endphp
                <tr data-state="{{ $state }}">
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $quota->quota_number }}</strong></td>
                    <td>{{ $quota->name }}</td>
                    <td class="text-end">{{ number_format($quota->total_allocation ?? 0) }}</td>
                    <td class="text-end col-forecast">{{ number_format(max(($quota->forecast_consumed ?? 0),0)) }}</td>
                    <td class="text-end col-forecast">{{ number_format(max(($quota->forecast_remaining ?? 0),0)) }}</td>
                    <td class="text-end col-actual d-none">{{ number_format(max(($quota->actual_consumed ?? 0),0)) }}</td>
                    <td class="text-end col-actual d-none">{{ number_format(max(($quota->actual_remaining ?? 0),0)) }}</td>
                    <td>{{ optional($quota->period_start)->format('M Y') ?? '-' }} - {{ optional($quota->period_end)->format('M Y') ?? '-' }}</td>
                    <td>
                        {{-- New colored badge based on forecast ratio --}}
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs fw-semibold" style="background: {{ $status[1] }}; color: {{ $status[2] }};">
                            {{ $status[0] }}
                        </span>

                        {{-- Progress bar (consumed vs allocation) --}}
                        <div class="mt-2">
                          <div class="h-2 w-100 bg-slate-200 rounded" style="height:8px;border-radius:6px;background:#e2e8f0;">
                            <div class="h-2 rounded" style="height:8px;width: {{ $pct }}%;border-radius:6px;background: {{ $status[3] }};"></div>
                          </div>
                          <div class="mt-1 text-[11px] text-slate-500">
                            {{ number_format($consumed) }} / {{ number_format($allocation) }} ({{ $pct }}%)
                          </div>
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="table-actions">
                            @can('delete quota')
                                <form action="{{ route('admin.quotas.destroy', $quota) }}" method="POST" onsubmit="return confirm('Delete this quota?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-icon action-icon--delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-muted">No quota data available.</td>
                </tr>
            @endforelse
        </tbody>
      </table>
    </div>

    <div class="stat-grid">
        <div class="stat-card-modern">
            <span class="stat-card-modern__label">Total Quotas</span>
            <span class="stat-card-modern__value">{{ $summary['active_count'] }}</span>
        </div>
        <div class="stat-card-modern">
            <span class="stat-card-modern__label">Total Units Available</span>
            <span class="stat-card-modern__value">{{ number_format($summary['total_quota']) }}</span>
        </div>
        <div class="stat-card-modern">
            <span class="stat-card-modern__label">Units Used</span>
            <span class="stat-card-modern__value">{{ number_format($summary['total_quota'] - $summary['forecast_remaining']) }}</span>
        </div>
        <div class="stat-card-modern">
            @php
                $percent = $summary['total_quota'] > 0
                    ? (($summary['total_quota'] - $summary['forecast_remaining']) / $summary['total_quota']) * 100
                    : 0;
            @endphp
            <span class="stat-card-modern__label">Usage Percentage</span>
            <span class="stat-card-modern__value">{{ number_format($percent, 1) }}%</span>
        </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('.filter-pill').forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.filter;
      document.querySelectorAll('tr[data-state]').forEach(row => {
        row.style.display = (mode==='all' || row.dataset.state===mode) ? '' : 'none';
      });
    });
  });
</script>
@endpush
@push('scripts')
<script>
(function(){
  const btnF = document.getElementById('mode-forecast');
  const btnA = document.getElementById('mode-actual');
  const showForecast = () => {
    document.querySelectorAll('.col-forecast').forEach(el=>el.classList.remove('d-none'));
    document.querySelectorAll('.col-actual').forEach(el=>el.classList.add('d-none'));
    btnF.classList.add('btn-primary'); btnF.classList.remove('btn-outline-primary');
    btnA.classList.add('btn-outline-primary'); btnA.classList.remove('btn-primary');
  };
  const showActual = () => {
    document.querySelectorAll('.col-forecast').forEach(el=>el.classList.add('d-none'));
    document.querySelectorAll('.col-actual').forEach(el=>el.classList.remove('d-none'));
    btnA.classList.add('btn-primary'); btnA.classList.remove('btn-outline-primary');
    btnF.classList.add('btn-outline-primary'); btnF.classList.remove('btn-primary');
  };
  if(btnF && btnA){
    btnF.addEventListener('click', showForecast);
    btnA.addEventListener('click', showActual);
    showForecast();
  }
})();
</script>
@endpush
