<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotaController extends Controller
{
    public function __construct()
    {
        // Read-only access
        $this->middleware('permission:read quota')->only(['index', 'export']);
        // Create
        $this->middleware('permission:create quota')->only(['create', 'store']);
        // Update
        $this->middleware('permission:update quota')->only(['edit', 'update', 'attachProduct', 'detachProduct']);
        // Delete
        $this->middleware('permission:delete quota')->only(['destroy']);
    }
    public function index(): View
    {
        $quotas = Quota::query()
            ->orderByDesc('period_start')
            ->get();

        // Compute derived metrics per quota using PO allocations (forecast) and histories (actual)
        $ids = $quotas->pluck('id')->all();
        $forecastByQuota = empty($ids) ? collect() : \Illuminate\Support\Facades\DB::table('purchase_order_quota')
            ->select('quota_id', \Illuminate\Support\Facades\DB::raw('SUM(allocated_qty) as qty'))
            ->whereIn('quota_id', $ids)
            ->groupBy('quota_id')
            ->pluck('qty', 'quota_id');
        $actualByQuota = empty($ids) ? collect() : \Illuminate\Support\Facades\DB::table('quota_histories')
            ->select('quota_id', \Illuminate\Support\Facades\DB::raw('SUM(ABS(quantity_change)) as qty'))
            ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
            ->whereIn('quota_id', $ids)
            ->groupBy('quota_id')
            ->pluck('qty', 'quota_id');

        foreach ($quotas as $q) {
            $allocation = (float) ($q->total_allocation ?? 0);
            $fc = min($allocation, (float) ($forecastByQuota[$q->id] ?? 0));
            $ac = min($allocation, (float) ($actualByQuota[$q->id] ?? 0));
            $q->setAttribute('forecast_consumed', $fc);
            $q->setAttribute('actual_consumed', $ac);
            $q->setAttribute('forecast_remaining', max($allocation - $fc, 0));
            $q->setAttribute('actual_remaining', max($allocation - $ac, 0));
        }

        $summary = [
            'total_quota' => (float) $quotas->sum('total_allocation'),
            'forecast_remaining' => (float) $quotas->sum(function ($q) { return (float)$q->getAttribute('forecast_remaining'); }),
            'actual_remaining' => (float) $quotas->sum(function ($q) { return (float)$q->getAttribute('actual_remaining'); }),
            'active_count' => $quotas->where('is_active', true)->count(),
        ];

        return view('admin.kuota.index', compact('quotas', 'summary'));
    }

    public function create(): View
    {
        $quota = new Quota();

        return view('admin.kuota.form', compact('quota'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['forecast_remaining'] = $data['forecast_remaining'] ?? $data['total_allocation'];
        $data['actual_remaining'] = $data['actual_remaining'] ?? $data['total_allocation'];

        Quota::create($data);

        return redirect()
            ->route('admin.quotas.index')
            ->with('status', 'Kuota berhasil ditambahkan');
    }

    public function edit(Quota $quota): View
    {
        return view('admin.kuota.form', compact('quota'));
    }

    public function update(Request $request, Quota $quota): RedirectResponse
    {
        $data = $this->validateData($request, $quota->id);

        $quota->update($data);

        return redirect()
            ->route('admin.quotas.index')
            ->with('status', 'Kuota berhasil diperbarui');
    }

    public function destroy(Quota $quota): RedirectResponse
    {
        $quota->delete();

        return redirect()
            ->route('admin.quotas.index')
            ->with('status', 'Kuota berhasil dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $quotaId = null): array
    {
        $data = $request->validate([
            'quota_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('quotas', 'quota_number')->ignore($quotaId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'government_category' => ['required', 'string', 'max:255'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'total_allocation' => ['required', 'integer', 'min:0'],
            'forecast_remaining' => ['nullable', 'integer', 'min:0', 'lte:total_allocation'],
            'actual_remaining' => ['nullable', 'integer', 'min:0', 'lte:total_allocation'],
            'status' => ['required', Rule::in([Quota::STATUS_AVAILABLE, Quota::STATUS_LIMITED, Quota::STATUS_DEPLETED])],
            'is_active' => ['nullable', 'boolean'],
            'source_document' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    public function export()
    {
        $quotas = Quota::query()->withCount('products')->orderByDesc('period_start');
        $filename = 'quotas_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($quotas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Quota Number', 'Name', 'Government Category', 'Period Start', 'Period End', 'Total Allocation',
                'Forecast Remaining', 'Actual Remaining', 'Status', 'Active', 'Products Count',
            ]);
            $quotas->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $q) {
                    fputcsv($out, [
                        $q->quota_number,
                        $q->name,
                        $q->government_category,
                        optional($q->period_start)->format('Y-m-d'),
                        optional($q->period_end)->format('Y-m-d'),
                        $q->total_allocation,
                        $q->forecast_remaining,
                        $q->actual_remaining,
                        $q->status,
                        $q->is_active ? 'yes' : 'no',
                        $q->products_count,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
