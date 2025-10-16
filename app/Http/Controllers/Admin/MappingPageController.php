<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MappingPageController extends Controller
{
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
}

