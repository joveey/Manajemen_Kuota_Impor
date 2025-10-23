<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsPkImportPageController extends Controller
{
    public function index(): View
    {
        $recent = Import::query()
            ->where('type', 'hs_pk')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('admin.imports.hs_pk.index', compact('recent'));
    }

    public function uploadForm(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'period_key' => ['required'],
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
            ->with('status', 'Upload berhasil. Ringkasan siap ditinjau.');
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
            $msg = $payload['error'] ?? 'Publish gagal.';
            return back()->withErrors(['publish' => $msg]);
        }

        $status = 'Publish berhasil'.(!empty($payload['ran_automap']) ? ' + automap' : '').'.';
        $warn = null;
        if (!empty($payload['skipped_existing'])) {
            $count = (int) $payload['skipped_existing'];
            $dups = $payload['duplicates'] ?? [];
            $sample = implode(', ', array_slice($dups, 0, 10));
            $warn = $count.' HS sudah ada di master dan dilewati'.($sample ? ': '.$sample.((count($dups) > 10) ? ' …' : '') : '');
        }

        $redir = redirect()->route('admin.imports.hs_pk.preview', $import)->with('status', $status);
        if ($warn) { $redir->with('warning', $warn); }
        return $redir;
    }
}
