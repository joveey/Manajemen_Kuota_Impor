<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HsPkImportPageController extends Controller
{
    public function index(Request $request): View
    {
        // Display manual input form and mapping list on the same page.
        $period = trim((string) $request->query('period_key', ''));
        $perPage = (int) min(max((int) $request->query('per_page', 25), 1), 200);

        $hasPeriodCol = Schema::hasColumn('hs_code_pk_mappings', 'period_key');
        $hasDescCol = Schema::hasColumn('hs_code_pk_mappings', 'desc');
        $select = ['id','hs_code','pk_capacity','created_at','updated_at'];
        if ($hasPeriodCol) { $select[] = 'period_key'; } else { $select[] = DB::raw("'' as period_key"); }
        if ($hasDescCol) { $select[] = 'desc'; }

        $q = DB::table('hs_code_pk_mappings')->select($select);
        if ($period !== '' && $hasPeriodCol) { $q->where('period_key', $period); }
        if ($hasPeriodCol) {
            $q->orderByRaw("CASE WHEN period_key = '' THEN 1 ELSE 0 END")
              ->orderByDesc('period_key');
        }
        $q->orderBy('hs_code');

        $rows = $q->paginate($perPage)->appends(['period_key' => $period]);

        return view('admin.imports.hs_pk.index', compact('rows', 'period'));
    }

    public function uploadForm(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        // Delegate to backend API controller directly
        $api = app(ImportController::class);
        $resp = $api->uploadHsPk($request);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if (isset($payload['error'])) {
            return back()->withErrors(['file' => $payload['error']])->withInput();
        }

        $importId = $payload['import_id'] ?? null;
        if (!$importId) {
            return back()->withErrors(['file' => 'Upload failed.'])->withInput();
        }

        return redirect()
            ->route('admin.imports.hs_pk.preview', ['import' => $importId])
            ->with('status', 'Upload successful. Summary ready for review.');
    }

    public function preview(Import $import): View
    {
        abort_unless($import->type === 'hs_pk', 404);
        return view('admin.imports.hs_pk.preview', compact('import'));
    }

    public function publishForm(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->type === 'hs_pk', 404);

        $api = app(ImportController::class);
        $resp = $api->publish($request, $import);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if ($resp->getStatusCode() >= 400) {
            $msg = $payload['error'] ?? 'Publish failed.';
            return back()->withErrors(['publish' => $msg]);
        }

        $status = 'Publish successful'.(!empty($payload['ran_automap']) ? ' + automap' : '').'.';
        $warn = null;
        if (!empty($payload['skipped_existing'])) {
            $count = (int) $payload['skipped_existing'];
            $dups = $payload['duplicates'] ?? [];
            $sample = implode(', ', array_slice($dups, 0, 10));
            $warn = $count.' HS already exist in the master and were skipped'.($sample ? ': '.$sample.((count($dups) > 10) ? ' ...' : '') : '');
        }

        $redir = redirect()->route('admin.imports.hs_pk.preview', $import)->with('status', $status);
        if ($warn) { $redir->with('warning', $warn); }
        return $redir;
    }
}

