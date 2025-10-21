<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenPoUploadRequest;
use App\Models\PoHeader;
use App\Models\PoLine;
use App\Services\OpenPoReader;
use App\Services\OpenPoValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class OpenPoImportController extends Controller
{
    public function form(): View
    {
        return view('admin.openpo.import');
    }

    public function preview(OpenPoUploadRequest $request, OpenPoReader $reader, OpenPoValidator $validator): View|RedirectResponse
    {
        $file = $request->file('file');
        $storedPath = $file->storeAs('imports', now()->format('Ymd_His').'_'.\Illuminate\Support\Str::random(6).'_openpo.xlsx');
        $full = Storage::path($storedPath);

        try {
            $payload = $reader->read($full);
            $rows = $payload['rows'] ?? [];
            $modelMap = $payload['model_map'] ?? [];
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file: '.$e->getMessage()])->withInput();
        }

        $result = $validator->validate($rows, $modelMap);
        // Simpan ke session untuk publish
        session(['openpo.preview' => $result, 'openpo.model_map' => $modelMap]);

        return view('admin.openpo.preview', [
            'summary' => [
                'groups' => count($result['groups']),
                'rows' => collect($result['groups'])->sum(fn($g) => count($g['lines'] ?? [])),
                'error_count' => (int) $result['error_count'],
            ],
            'result' => $result,
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $result = session('openpo.preview');
        if (!$result || !is_array($result)) {
            return redirect()->route('admin.openpo.form')->withErrors(['file' => 'Preview tidak ditemukan. Upload ulang file.']);
        }
        if (($result['error_count'] ?? 0) > 0) {
            return back()->withErrors(['publish' => 'Perbaiki error sebelum publish.']);
        }

        $groups = $result['groups'] ?? [];
        $modelMap = session('openpo.model_map', []);

        DB::transaction(function () use ($groups, $modelMap) {
            $hsTable = DB::getSchemaBuilder()->hasTable('hs_codes') ? 'hs_codes' : 'hs_code_pk_mappings';
            $hsCodeCol = $hsTable === 'hs_codes' ? 'code' : 'hs_code';

            foreach ($groups as $poNumber => $payload) {
                $header = PoHeader::firstOrCreate(
                    ['po_number' => (string) $poNumber],
                    [
                        'po_date' => $payload['po_date'] ?? now()->toDateString(),
                        'supplier' => $payload['supplier'] ?? '',
                        'currency' => $payload['currency'] ?? null,
                        'note' => $payload['note'] ?? null,
                        'published_at' => now(),
                        'created_by' => Auth::id(),
                    ]
                );

                foreach ($payload['lines'] as $line) {
                    $hsId = null;
                    if (!empty($line['hs_code'])) {
                        $row = DB::table($hsTable)->select('id')->where($hsCodeCol, $line['hs_code'])->first();
                        $hsId = $row->id ?? null;
                    }

                    PoLine::updateOrCreate(
                        ['po_header_id' => $header->id, 'model_code' => (string) $line['model_code'], 'line_no' => (string)($line['line_no'] ?? '')],
                        [
                            'item_desc' => $line['item_desc'] ?? null,
                            'hs_code_id' => $hsId,
                            'qty_ordered' => (float) $line['qty_ordered'],
                            'qty_received' => 0,
                            'uom' => $line['uom'] ?? null,
                            'eta_date' => $line['eta_date'] ?? null,
                            'validation_status' => 'ok',
                            'validation_notes' => null,
                        ]
                    );
                }

                // Persist mapping from mapping sheet into products.hs_code
                foreach (($modelMap ?? []) as $model => $hs) {
                    if (!$model || !$hs) { continue; }
                    DB::table('products')
                        ->where('sap_model', $model)
                        ->orWhere('code', $model)
                        ->update(['hs_code' => $hs, 'updated_at' => now()]);
                }
            }
        });

        session()->forget('openpo.preview');
        session()->forget('openpo.model_map');
        return redirect()->route('admin.openpo.form')->with('status', 'Open PO berhasil dipublish.');
    }
}
