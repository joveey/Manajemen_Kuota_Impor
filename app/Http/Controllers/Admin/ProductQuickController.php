<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuickProductRequest;
use App\Models\Product;
use App\Services\ProductQuotaAutoMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class ProductQuickController extends Controller
{
    public function index(): View
    {
        $recent = Product::query()
            ->select(['id', 'code', 'hs_code', 'pk_capacity', 'updated_at'])
            ->whereNotNull('hs_code')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('admin.products.quick_index_hs', compact('recent'));
    }

    public function create(Request $request): View
    {
        $model = trim((string) $request->query('model', ''));
        $periodKey = trim((string) $request->query('period_key', ''));
        $returnUrl = (string) $request->query(
            'return',
            url()->previous() ?: route('admin.mapping.mapped.page')
        );

        return view('admin.products.quick_create_hs', compact('model', 'periodKey', 'returnUrl'));
    }

    public function store(StoreQuickProductRequest $request, ProductQuotaAutoMapper $autoMapper): RedirectResponse
    {
        $data = $request->validated();
        $model = trim((string) $data['model']);
        $hs = trim((string) $data['hs_code']);
        $pk = $data['pk_capacity'] !== null ? (float) $data['pk_capacity'] : null;
        $category = isset($data['category']) ? trim((string) $data['category']) : null;
        $periodKey = $data['period_key'] ?? null;
        $return = $data['return'] ?? route('admin.mapping.mapped.page');

        // Pastikan kolom hs_code tersedia pada schema
        if (!Schema::hasColumn('products', 'hs_code')) {
            return back()->withErrors([
                'hs_code' => 'Kolom hs_code belum ada pada tabel products. Jalankan migrasi: php artisan migrate (pastikan migration 2025_10_21_000004_add_hs_code_to_products_table.php telah terapply).',
            ])->withInput();
        }

        // Cari produk berdasarkan sap_model atau code (case-insensitive)
        $product = Product::query()
            ->whereRaw('LOWER(sap_model) = ?', [strtolower($model)])
            ->orWhereRaw('LOWER(code) = ?', [strtolower($model)])
            ->first();

        if (!$product) {
            $product = Product::create([
                'code' => $model,
                'name' => $model,
                'sap_model' => $model,
                'category' => $category,
                'hs_code' => $hs,
                'pk_capacity' => $pk,
                'is_active' => true,
            ]);
        } else {
            $product->update([
                'category' => $category,
                'hs_code' => $hs,
                'pk_capacity' => $pk,
            ]);
        }

        // Optional auto-mapping by period
        if (!empty($periodKey)) {
            try {
                $autoMapper->runForPeriod($periodKey);
            } catch (\Throwable $e) {
                // swallow errors to keep UX smooth
            }
        } else {
            // Fallback: ensure mappings broadly
            try { $autoMapper->sync($product); } catch (\Throwable $e) {}
        }

        return redirect($return)->with('status', 'Model berhasil ditambahkan/diupdate. Silakan refresh preview.');
    }
}
