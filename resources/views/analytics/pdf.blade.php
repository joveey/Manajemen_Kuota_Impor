<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f2f5ff; text-align: left; }
        .right { text-align: right; }
        .muted { color: #777; }
    </style>
</head>
<body>
    @php
        $mode = $summary['mode'] ?? 'actual';
        $title = $mode === 'forecast' ? 'Analytics Forecast Report' : 'Analytics Actual Report';
        $usageLabel = $summary['usage_label'] ?? ($mode === 'forecast' ? 'Forecast' : 'Actual');
        $secondaryLabel = $summary['secondary_label'] ?? ($mode === 'forecast' ? 'Sisa Forecast' : 'Sisa Kuota');
        $percentageLabel = $summary['percentage_label'] ?? ($mode === 'forecast' ? 'Penggunaan Forecast %' : 'Pemakaian Actual %');
    @endphp

    <h1>{{ $title }}</h1>
    <p class="muted">Periode: {{ $filters['start_date'] ?? '-' }} s/d {{ $filters['end_date'] ?? '-' }}</p>

    <table style="margin-bottom:12px">
        <tbody>
            <tr>
                <td>Total Kuota</td>
                <td class="right">{{ number_format($summary['total_allocation'] ?? 0) }}</td>
                <td>Total {{ $usageLabel }}</td>
                <td class="right">{{ number_format($summary['total_usage'] ?? 0) }}</td>
                <td>{{ $secondaryLabel }}</td>
                <td class="right">{{ number_format($summary['total_remaining'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Nomor Kuota</th>
                <th>Range PK</th>
                <th class="right">Kuota Awal</th>
                <th class="right">{{ $usageLabel }}</th>
                <th class="right">{{ $secondaryLabel }}</th>
                <th class="right">{{ $percentageLabel }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td>{{ $r['quota_number'] }}</td>
                <td>{{ $r['range_pk'] }}</td>
                <td class="right">{{ number_format($r['initial_quota']) }}</td>
                <td class="right">{{ number_format($r['primary_value']) }}</td>
                <td class="right">{{ number_format($r['secondary_value']) }}</td>
                <td class="right">{{ number_format($r['percentage'], 2) }}%</td>
            </tr>
            @empty
            <tr><td colspan="6" class="muted">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
<!-- Generated for PDF export -->
</html>
