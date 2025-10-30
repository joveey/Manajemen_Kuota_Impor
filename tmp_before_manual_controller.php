<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Support\PkCategoryParser;

class HsPkManualController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(DB::getSchemaBuilder()->hasTable('hs_code_pk_mappings'), 404);

        $period = trim((string) $request->query('period_key', ''));
        $perPage = (int) min(max((int)$request->query('per_page', 50), 1), 200);

        $hasPeriodCol = Schema::hasColumn('hs_code_pk_mappings', 'period_key');
        $hasDescCol = Schema::hasColumn('hs_code_pk_mappings', 'desc');
        $select = ['id','hs_code','pk_capacity','created_at','updated_at'];
        if ($hasPeriodCol) {
            $select[] = 'period_key';
        } else {
            $select[] = DB::raw("'' as period_key");
        }
        if ($hasDescCol) { $select[] = 'desc'; }

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
            'pk_value' => ['required', 'string', 'max:50'],
            'period_key' => ['nullable', 'regex:/^\d{4}$/'],
        ], [
            'period_key.regex' => 'Periode harus format YYYY atau kosong.',
            'pk_value.required' => 'PK wajib diisi (contoh: 8-10, <8, >10, atau angka).',
        ]);

        $period = (string) ($data['period_key'] ?? '');
        $code = trim((string) $data['hs_code']);
        $label = trim((string) $data['pk_value']);

        $parsed = PkCategoryParser::parse($label);
        $min = $parsed['min_pk'];
        $max = $parsed['max_pk'];
        $anchor = null;
        if ($min === null && $max === null) {
            $num = preg_replace('/[^0-9.\-]/', '', $label);
            if ($num !== '' && is_numeric($num)) { $anchor = (float)$num; }
        } else {
            if ($min === null && $max !== null) { $anchor = (float)$max - 0.01; }
            elseif ($min !== null && $max !== null) { $anchor = ((float)$min + (float)$max) / 2.0; }
            elseif ($min !== null && $max === null) { $anchor = (float)$min + 0.01; }
        }

        if ($anchor === null || !is_numeric($anchor) || $anchor < 0) {
            return back()->withErrors(['pk_value' => 'Format PK tidak dikenali. Gunakan 8-10, <8, >10, atau angka ≥ 0.'])->withInput();
        }
        $pk = round((float)$anchor, 2);

        $hasPeriodCol = Schema::hasColumn('hs_code_pk_mappings', 'period_key');
        $hasDescCol = Schema::hasColumn('hs_code_pk_mappings', 'desc');
        $row = [
            'hs_code' => $code,
            'pk_capacity' => $pk,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $keys = ['hs_code'];
        if ($hasPeriodCol) { $row['period_key'] = $period; $keys = ['hs_code','period_key']; }
        if ($hasDescCol) { $row['desc'] = $label; }

        $updateCols = ['pk_capacity', 'updated_at'];
        if ($hasDescCol) { $updateCols[] = 'desc'; }
        DB::table('hs_code_pk_mappings')->upsert([$row], $keys, $updateCols);

        return back()->with('status', 'Mapping disimpan.');
    }
}

