<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\Quota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotaController extends Controller
{
    public function index(): View
    {
        $quotas = Quota::query()
            ->withCount('products')
            ->orderByDesc('period_start')
            ->get();

        $summary = [
            'total_quota' => $quotas->sum('total_allocation'),
            'forecast_remaining' => $quotas->sum('forecast_remaining'),
            'actual_remaining' => $quotas->sum('actual_remaining'),
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

    public function show(Quota $quota): View
    {
        $quota->load(['products', 'histories' => function ($query) {
            $query->latest();
        }]);

        $availableProducts = Product::active()
            ->whereNotIn('id', $quota->products->pluck('id'))
            ->get()
            ->filter(fn (Product $product) => $quota->matchesProduct($product))
            ->sortBy('name');

        return view('admin.kuota.show', compact('quota', 'availableProducts'));
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

    public function attachProduct(Request $request, Quota $quota): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')],
            'priority' => ['required', 'integer', 'min:1'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        ProductQuotaMapping::updateOrCreate(
            [
                'quota_id' => $quota->id,
                'product_id' => $data['product_id'],
            ],
            [
                'priority' => $data['priority'],
                'is_primary' => $request->boolean('is_primary'),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('status', 'Mapping produk berhasil disimpan');
    }

    public function detachProduct(Quota $quota, Product $product): RedirectResponse
    {
        ProductQuotaMapping::where('quota_id', $quota->id)
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('status', 'Mapping produk berhasil dihapus');
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
            'forecast_remaining' => ['nullable', 'integer', 'min:0'],
            'actual_remaining' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in([Quota::STATUS_AVAILABLE, Quota::STATUS_LIMITED, Quota::STATUS_DEPLETED])],
            'is_active' => ['nullable', 'boolean'],
            'source_document' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
