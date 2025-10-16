<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotaImportPageController extends Controller
{
    public function index(): View
    {
        $recent = Import::query()
            ->where('type', 'quota')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('admin.imports.quotas.index', compact('recent'));
    }

    public function uploadForm(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'period_key' => ['required'],
        ]);

        $api = app(ImportController::class);
        $resp = $api->uploadQuotas($request);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if (isset($payload['error'])) {
            return back()->withErrors(['file' => $payload['error']])->withInput();
        }

        $importId = $payload['import_id'] ?? null;
        if (!$importId) {
            return back()->withErrors(['file' => 'Upload failed.'])->withInput();
        }

        return redirect()
            ->route('admin.imports.quotas.preview', ['import' => $importId])
            ->with('status', 'Upload berhasil. Ringkasan siap ditinjau.');
    }

    public function preview(Import $import): View
    {
        abort_unless($import->type === 'quota', 404);
        return view('admin.imports.quotas.preview', compact('import'));
    }

    public function publishForm(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->type === 'quota', 404);

        $api = app(ImportController::class);
        $resp = $api->publishQuotas($request, $import);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if ($resp->getStatusCode() >= 400) {
            $msg = $payload['error'] ?? 'Publish gagal.';
            return back()->withErrors(['publish' => $msg]);
        }

        $applied = $payload['applied'] ?? null;
        $skipped = $payload['skipped'] ?? null;
        $version = $payload['version'] ?? null;
        $extra = !empty($payload['ran_automap']) ? ' + automap' : '';
        $msg = 'Publish berhasil'.($version !== null ? " (v$version)" : '').($applied !== null && $skipped !== null ? ": applied=$applied, skipped=$skipped" : '').$extra.'.';

        return redirect()
            ->route('admin.imports.quotas.preview', $import)
            ->with('status', $msg);
    }
}

