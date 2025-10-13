<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics Actual Report</title>
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
    <h1>Analytics Actual Report</h1>
    <p class="muted">Periode: {{ $filters['start_date'] ?? '-' }} s/d {{ $filters['end_date'] ?? '-' }}</p>

    <table style="margin-bottom:12px">
        <tbody>
            <tr>
                <td>Total Kuota</td>
                <td class="right">{{ number_format($summary['total_allocation'] ?? 0) }}</td>
                <td>Total Actual</td>
                <td class="right">{{ number_format($summary['total_actual'] ?? 0) }}</td>
                <td>Sisa</td>
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
                <th class="right">Forecast</th>
                <th class="right">Actual</th>
                <th class="right">Pemakaian Actual %</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td>{{ $r['quota_number'] }}</td>
                <td>{{ $r['range_pk'] }}</td>
                <td class="right">{{ number_format($r['initial_quota']) }}</td>
                <td class="right">{{ number_format($r['forecast']) }}</td>
                <td class="right">{{ number_format($r['actual']) }}</td>
                <td class="right">{{ number_format($r['actual_pct'], 2) }}%</td>
            </tr>
            @empty
            <tr><td colspan="6" class="muted">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
<!-- Generated for PDF export -->
</html>

