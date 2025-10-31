<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MappingPageController extends Controller
{
    public function __construct()
    {
        // Guard by permission only; allow read-limited users via Gate alias
        $this->middleware('permission:read master_data');
    }

    public function unmapped(Request $request): View
    {
        $period   = (string)($request->query('period') ?: now()->format('Y'));
        $reason   = $request->query('reason');
        $perPage  = (int) min(max((int)$request->query('per_page', 20), 1), 200);

        return view('admin.mapping.unmapped', [
            'period' => $period,
            'reason' => $reason,
            'perPage' => $perPage,
        ]);
    }

    public function mapped(Request $request): View
    {
        $perPage = (int) min(max((int)$request->query('per_page', 20), 1), 200);
        $search = trim((string)$request->query('search', ''));
        $onlyActive = (bool) $request->boolean('only_active', false);

        $query = \App\Models\Product::query()
            ->with(['quotaMappings.quota'])
            ->whereNotNull('hs_code')
            ->where('hs_code', '!=', '')
            ->when($onlyActive, fn($q) => $q->where('is_active', true))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('sap_model', 'like', $like)
                        ->orWhere('hs_code', 'like', $like);
                });
            })
            ->orderBy('code');

        $products = $query->paginate($perPage)->withQueryString();

        // Enrich with HS → PK label (DESC) for current year if available
        if (Schema::hasTable('hs_code_pk_mappings')) {
            $codes = $products->getCollection()->pluck('hs_code')->filter()->unique()->values()->all();
            if (!empty($codes)) {
                $hasDesc = Schema::hasColumn('hs_code_pk_mappings', 'desc');
                $hasPeriod = Schema::hasColumn('hs_code_pk_mappings', 'period_key');
                $select = ['hs_code', 'pk_capacity'];
                if ($hasDesc) { $select[] = 'desc'; }
                if ($hasPeriod) { $select[] = 'period_key'; }

                $q = DB::table('hs_code_pk_mappings')->whereIn('hs_code', $codes);
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
                $products->getCollection()->transform(function ($p) use ($labelByCode) {
                    $p->setAttribute('hs_desc', $labelByCode[$p->hs_code] ?? null);
                    return $p;
                });
            }
        }

        return view('admin.mapping.mapped', compact('products', 'search', 'onlyActive'));
    }
}
