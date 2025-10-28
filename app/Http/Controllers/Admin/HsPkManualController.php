<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class HsPkManualController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(DB::getSchemaBuilder()->hasTable('hs_code_pk_mappings'), 404);

        $period = trim((string) $request->query('period_key', ''));
        $perPage = (int) min(max((int)$request->query('per_page', 50), 1), 200);

        $hasPeriodCol = Schema::hasColumn('hs_code_pk_mappings', 'period_key');
        $select = ['id','hs_code','pk_capacity','created_at','updated_at'];
        if ($hasPeriodCol) {
            $select[] = 'period_key';
        } else {
            $select[] = DB::raw("'' as period_key");
        }

        $q = DB::table('hs_code_pk_mappings')->select($select);
        if ($period !== '' && $hasPeriodCol) {
            $q->where('period_key', $period);
        }
        if ($hasPeriodCol) {
            $q->orderByRaw("CASE WHEN period_key = '' THEN 1 ELSE 0 END")
              ->orderByDesc('period_key');
        }
        $q->orderBy('hs_code');

        $rows = $q->paginate($perPage)->appends(['period_key' => $period]);

        return view('admin.hs_pk_manual.index', compact('rows', 'period'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(DB::getSchemaBuilder()->hasTable('hs_code_pk_mappings'), 404);

        $data = $request->validate([
            'hs_code' => ['required', 'string', 'max:50'],
            'pk_capacity' => ['required', 'numeric', 'min:0'],
            'period_key' => ['nullable', 'regex:/^\d{4}$/'],
        ], [
            'period_key.regex' => 'Periode harus format YYYY atau kosong.',
        ]);

        $period = (string) ($data['period_key'] ?? '');
        $code = trim((string) $data['hs_code']);
        $pk = round((float) $data['pk_capacity'], 2);

        DB::table('hs_code_pk_mappings')->upsert([
            [
                'hs_code' => $code,
                'period_key' => $period,
                'pk_capacity' => $pk,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['hs_code', 'period_key'], ['pk_capacity', 'updated_at']);

        return back()->with('status', 'Mapping disimpan.');
    }
}
