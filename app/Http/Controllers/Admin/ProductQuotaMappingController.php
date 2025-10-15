<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProductQuotaMappingController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = ProductQuotaMapping::query()
            ->with(['product', 'quota'])
            ->when($request->filled('product_id'), function ($builder) use ($request) {
                $builder->where('product_id', (int) $request->input('product_id'));
            })
            ->when($request->filled('quota_id'), function ($builder) use ($request) {
                $builder->where('quota_id', (int) $request->input('quota_id'));
            })
            ->when($request->filled('search'), function ($builder) use ($request) {
                $term = '%'.$request->input('search').'%';
                $builder->where(function ($nested) use ($term) {
                    $nested->whereHas('product', function ($productQuery) use ($term) {
                        $productQuery->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    })->orWhereHas('quota', function ($quotaQuery) use ($term) {
                        $quotaQuery->where('quota_number', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    });
                });
            })
            ->orderBy('product_id')
            ->orderBy('priority');

        if ($request->wantsJson()) {
            $perPage = max(1, min(100, (int) $request->input('per_page', 25)));
            $mappings = $query->paginate($perPage);

            return response()->json($mappings);
        }

        $products = Product::orderBy('code')->get(['id', 'code', 'name']);
        $quotas = Quota::orderBy('quota_number')
            ->orderBy('name')
            ->get(['id', 'quota_number', 'name', 'government_category']);
        $initialMappings = $query->limit(200)->get();

        return view('admin.kuota.mapping', compact('products', 'quotas', 'initialMappings'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quota_id' => ['required', 'integer', Rule::exists('quotas', 'id')],
            'priority' => ['nullable', 'integer', 'min:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $quota = Quota::findOrFail($data['quota_id']);

        if (!$quota->matchesProduct($product)) {
            throw ValidationException::withMessages([
                'quota_id' => ['Kuota tidak sesuai dengan rentang produk yang dipilih.'],
            ]);
        }

        $exists = ProductQuotaMapping::query()
            ->where('product_id', $product->id)
            ->where('quota_id', $quota->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'quota_id' => ['Produk sudah dimapping ke kuota ini.'],
            ]);
        }

        $priority = $data['priority']
            ?? ((int) ProductQuotaMapping::where('product_id', $product->id)->max('priority') + 1);

        $explicitPrimary = array_key_exists('is_primary', $data);
        $isPrimary = $explicitPrimary ? (bool) $data['is_primary'] : false;

        if (!$explicitPrimary) {
            $hasExistingPrimary = ProductQuotaMapping::where('product_id', $product->id)
                ->where('is_primary', true)
                ->exists();
            $isPrimary = !$hasExistingPrimary;
        }

        $mapping = null;
        DB::transaction(function () use ($product, $quota, $priority, $isPrimary, $data, &$mapping) {
            $mapping = ProductQuotaMapping::create([
                'product_id' => $product->id,
                'quota_id' => $quota->id,
                'priority' => $priority,
                'is_primary' => $isPrimary,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($isPrimary) {
                ProductQuotaMapping::where('product_id', $product->id)
                    ->where('id', '!=', $mapping->id)
                    ->update(['is_primary' => false]);
            }
        });

        $mapping->load(['product', 'quota']);

        if ($request->wantsJson()) {
            return response()->json($mapping, Response::HTTP_CREATED);
        }

        return redirect()
            ->back()
            ->with('status', 'Mapping produk berhasil ditambahkan.');
    }

    public function update(Request $request, ProductQuotaMapping $productQuotaMapping): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'priority' => ['sometimes', 'integer', 'min:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $changes = [];
        if (array_key_exists('priority', $data)) {
            $changes['priority'] = (int) $data['priority'];
        }

        if (array_key_exists('notes', $data)) {
            $changes['notes'] = $data['notes'];
        }

        $togglePrimary = array_key_exists('is_primary', $data);
        $setPrimary = $togglePrimary ? (bool) $data['is_primary'] : null;

        DB::transaction(function () use ($productQuotaMapping, $changes, $togglePrimary, $setPrimary) {
            if (!empty($changes)) {
                $productQuotaMapping->fill($changes)->save();
            }

            if ($togglePrimary) {
                if ($setPrimary) {
                    ProductQuotaMapping::where('product_id', $productQuotaMapping->product_id)
                        ->where('id', '!=', $productQuotaMapping->id)
                        ->update(['is_primary' => false]);
                    $productQuotaMapping->is_primary = true;
                    $productQuotaMapping->save();
                } else {
                    $productQuotaMapping->is_primary = false;
                    $productQuotaMapping->save();

                    $otherPrimary = ProductQuotaMapping::where('product_id', $productQuotaMapping->product_id)
                        ->where('is_primary', true)
                        ->where('id', '!=', $productQuotaMapping->id)
                        ->exists();

                    if (!$otherPrimary) {
                        $fallback = ProductQuotaMapping::where('product_id', $productQuotaMapping->product_id)
                            ->where('id', '!=', $productQuotaMapping->id)
                            ->orderBy('priority')
                            ->first();

                        if ($fallback) {
                            $fallback->is_primary = true;
                            $fallback->save();
                        } else {
                            $productQuotaMapping->is_primary = true;
                            $productQuotaMapping->save();
                        }
                    }
                }
            }
        });

        $productQuotaMapping->refresh()->load(['product', 'quota']);

        if ($request->wantsJson()) {
            return response()->json($productQuotaMapping);
        }

        return redirect()
            ->back()
            ->with('status', 'Mapping produk berhasil diperbarui.');
    }

    public function destroy(Request $request, ProductQuotaMapping $productQuotaMapping): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($productQuotaMapping) {
            $productId = $productQuotaMapping->product_id;
            $wasPrimary = $productQuotaMapping->is_primary;

            $productQuotaMapping->delete();

            if ($wasPrimary) {
                $replacement = ProductQuotaMapping::where('product_id', $productId)
                    ->orderBy('priority')
                    ->first();

                if ($replacement) {
                    $replacement->is_primary = true;
                    $replacement->save();
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json(null, Response::HTTP_NO_CONTENT);
        }

        return redirect()
            ->back()
            ->with('status', 'Mapping produk berhasil dihapus.');
    }

    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', Rule::exists('product_quota_mappings', 'id')],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['order']) as $index => $mappingId) {
                $updated = ProductQuotaMapping::where('product_id', $data['product_id'])
                    ->where('id', $mappingId)
                    ->update(['priority' => $index + 1]);

                if ($updated === 0) {
                    throw ValidationException::withMessages([
                        'order' => ['Urutan mapping tidak valid untuk produk yang dipilih.'],
                    ]);
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()
            ->back()
            ->with('status', 'Prioritas mapping berhasil diperbarui.');
    }
}
