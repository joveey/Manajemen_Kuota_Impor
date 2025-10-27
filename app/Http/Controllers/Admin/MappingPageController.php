<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MappingPageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read master_data');
        $this->middleware('role:admin|manager|editor');
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

        return view('admin.mapping.mapped', compact('products', 'search', 'onlyActive'));
    }
}
