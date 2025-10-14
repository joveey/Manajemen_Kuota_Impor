<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductQuotaAutoMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductQuotaAutoMapper $autoMapper)
    {
        $this->middleware('permission:read master_data')->only(['index']);
        $this->middleware('permission:create master_data')->only(['create', 'store']);
        $this->middleware('permission:update master_data')->only(['edit', 'update']);
        $this->middleware('permission:delete master_data')->only(['destroy']);
    }
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['quotaMappings.quota'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('sap_model', 'like', $term);
                });
            })
            ->orderBy('code')
            ->get();

        return view('admin.master_data.index', compact('products'));
    }

    public function create(): View
    {
        $product = new Product();

        return view('admin.master_data.form', compact('product'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $product = Product::create($data);
        $this->autoMapper->sync($product);

        return redirect()
            ->route('admin.master-data.index')
            ->with('status', 'Produk berhasil ditambahkan');
    }

    public function edit(Product $product): View
    {
        return view('admin.master_data.form', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateData($request, $product->id);

        $product->update($data);
        $product->refresh();
        $this->autoMapper->sync($product);

        return redirect()
            ->route('admin.master-data.index')
            ->with('status', 'Produk berhasil diperbarui');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('admin.master-data.index')
            ->with('status', 'Produk berhasil dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $productId = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'code')->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sap_model' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'pk_capacity' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
