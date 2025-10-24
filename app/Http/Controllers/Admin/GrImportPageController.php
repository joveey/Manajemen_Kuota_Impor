<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrImportPageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read quota')->only(['index','preview']);
        $this->middleware('permission:create quota')->only(['uploadForm','publishForm']);
    }

    public function index(): View
    {
        $recent = Import::query()
            ->where('type', Import::TYPE_GR)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();
        return view('admin.imports.gr.index', compact('recent'));
    }

    public function uploadForm(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required','file','mimes:xlsx,xls']]);
        $api = app(ImportController::class);
        $resp = $api->uploadGr($request);
        $payload = json_decode($resp->getContent(), true) ?: [];
        if (isset($payload['error'])) {
            return back()->withErrors(['file' => $payload['error']])->withInput();
        }
        $importId = $payload['import_id'] ?? null;
        if (!$importId) { return back()->withErrors(['file'=>'Upload failed'])->withInput(); }
        return redirect()->route('admin.imports.gr.preview', ['import'=>$importId])
            ->with('status','Upload berhasil. Ringkasan siap ditinjau.');
    }

    public function preview(Import $import): View
    {
        abort_unless($import->type === Import::TYPE_GR, 404);
        return view('admin.imports.gr.preview', compact('import'));
    }

    public function publishForm(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->type === Import::TYPE_GR, 404);
        $api = app(ImportController::class);
        $resp = $api->publishGr($request, $import);
        $payload = json_decode($resp->getContent(), true) ?: [];
        if ($resp->getStatusCode() >= 400) {
            $msg = $payload['error'] ?? 'Publish gagal.';
            return back()->withErrors(['file' => $msg]);
        }
        return redirect()->route('admin.imports.gr.index')->with('status','Publish berhasil.');
    }
}
