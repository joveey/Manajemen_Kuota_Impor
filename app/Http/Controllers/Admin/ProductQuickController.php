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
use Illuminate\Support\Facades\DB;

class ProductQuickController extends Controller
{
    public function index(Request $request): View
    {
        $model = trim((string) $request->query('model', ''));
        $periodKey = trim((string) $request->query('period_key', ''));
        $returnUrl = (string) $request->query(
            'return',
            url()->previous() ?: route('admin.mapping.mapped.page')
        );
        $recent = Product::query()
            ->select(['id', 'code', 'hs_code', 'pk_capacity', 'updated_at'])
            ->whereNotNull('hs_code')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Enrich with HS→PK label (DESC) if master table exists
        if (Schema::hasTable('hs_code_pk_mappings')) {
            $codes = $recent->pluck('hs_code')->filter()->unique()->values()->all();
            if (!empty($codes)) {
                $hasDesc = Schema::hasColumn('hs_code_pk_mappings', 'desc');
                $hasPeriod = Schema::hasColumn('hs_code_pk_mappings', 'period_key');
                $select = ['hs_code', 'pk_capacity'];
                if ($hasDesc) { $select[] = 'desc'; }
                if ($hasPeriod) { $select[] = 'period_key'; }

                $q = DB::table('hs_code_pk_mappings')
                    ->whereIn('hs_code', $codes);
                if ($hasPeriod) {
                    $year = now()->format('Y');
                    $q->where(function ($w) use ($year) {
                        $w->where('period_key', $year)->orWhere('period_key', '');
                    })->orderByRaw("CASE WHEN period_key = ? THEN 0 WHEN period_key = '' THEN 1 ELSE 2 END", [$year]);
                }
                $rows = $q->orderBy('hs_code')->get($select);

                $labelByCode = [];
                foreach ($rows as $r) {
                    if (!isset($labelByCode[$r->hs_code])) {
                        $label = null;
                        if ($hasDesc) { $label = (string) ($r->desc ?? ''); }
                        if ($label === '') { $label = null; }
                        if (strtoupper((string)$r->hs_code) === 'ACC') { $label = 'Accesory'; }
                        $labelByCode[$r->hs_code] = $label;
                    }
                }

                foreach ($recent as $p) {
                    $p->setAttribute('hs_desc', $labelByCode[$p->hs_code] ?? null);
                }
            }
        }

        // Seed HS options for manual form (like Input Kuota)
        $hsSeedOptions = [];
        if (Schema::hasTable('hs_code_pk_mappings')) {
            $hasDesc = Schema::hasColumn('hs_code_pk_mappings', 'desc');
            $rows = DB::table('hs_code_pk_mappings')
                ->select(array_filter(['hs_code', 'pk_capacity', $hasDesc ? 'desc' : null]))
                ->orderBy('hs_code')
                ->limit(200)
                ->get();
            foreach ($rows as $row) {
                $desc = $hasDesc ? ($row->desc ?? '') : '';
                if (strtoupper((string)$row->hs_code) === 'ACC') { $desc = 'Accesory'; }
                if ($desc === '') { $desc = $this->formatPkLabel((float) ($row->pk_capacity ?? 0)); }
                $hsSeedOptions[] = [
                    'id' => $row->hs_code,
                    'text' => $row->hs_code,
                    'desc' => $desc,
                    'pk' => $row->pk_capacity,
                ];
            }
        }

        return view('admin.products.quick_index_hs', compact('recent', 'hsSeedOptions', 'model', 'periodKey', 'returnUrl'));
    }

    // Removed dedicated create() page; index now accepts query params

    private function formatPkLabel(?float $anchor): string
    {
        if ($anchor === null) { return 'PK N/A'; }
        if ($anchor <= 0.0) { return 'ACC'; }
        $rounded = round($anchor, 2);
        $fraction = $rounded - floor($rounded);
        if (abs($fraction - 0.99) < 0.02) { return '<'.number_format(floor($rounded) + 1, 0, '.', ''); }
        if (abs($fraction - 0.01) < 0.02) { return '>'.number_format(floor($rounded), 0, '.', ''); }
        if (abs($fraction) < 0.01) { return number_format($rounded, 0, '.', ''); }
        return number_format($rounded, 2, '.', '');
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
