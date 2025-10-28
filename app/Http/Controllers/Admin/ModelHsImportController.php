<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModelHsImportController extends Controller
{
    public function index(): View
    {
        return view('admin.mapping.model_hs.index');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return back()->withErrors(['file' => 'Package phpoffice/phpspreadsheet belum terpasang. Jalankan: composer require phpoffice/phpspreadsheet'])->withInput();
        }

        $uploaded = $request->file('file');
        $stored = $uploaded->storeAs('imports', now()->format('Ymd_His').'_model_hs.'.($uploaded->getClientOriginalExtension() ?: 'xlsx'));
        $path = \Illuminate\Support\Facades\Storage::path($stored);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file: '.$e->getMessage()])->withInput();
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = (int) \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Build header map
        $hdr = [];
        for ($c = 1; $c <= $highestCol; $c++) {
            $val = (string) $sheet->getCell([$c,1])->getValue();
            $key = strtoupper(trim(preg_replace('/\s+/', '_', str_replace(['-',' '], '_', $val))));
            if ($key !== '') { $hdr[$c] = $key; }
        }

        $reqCols = ['MODEL','HS_CODE'];
        foreach ($reqCols as $rc) {
            if (!in_array($rc, array_values($hdr), true)) {
                return back()->withErrors(['file' => 'Kolom wajib tidak ditemukan. Harus ada: MODEL, HS_CODE'])->withInput();
            }
        }

        // Detect column positions
        $colModel = array_search('MODEL', $hdr, true);
        $colHs = array_search('HS_CODE', $hdr, true);

        $rows = [];
        $errors = 0; $valid = 0; $seen = [];
        for ($r=2; $r <= $highestRow; $r++) {
            $model = trim((string) $sheet->getCell([$colModel, $r])->getFormattedValue());
            // Gunakan formatted value agar titik pada HS (mis. 8415.10.20) tetap terbaca apa adanya
            $hsRaw = (string) $sheet->getCell([$colHs, $r])->getFormattedValue();
            $hs = strtoupper(trim($hsRaw));
            if ($model === '' && $hs === '') { continue; }

            $status = 'ok';
            $notes = [];

            if ($model === '') { $status='error'; $notes[]='MODEL kosong'; }
            if ($hs === '') { $status='error'; $notes[]='HS_CODE kosong'; }

            $mkey = strtoupper($model);
            if (isset($seen[$mkey])) { $status='error'; $notes[]='Duplikat MODEL pada file'; }
            $seen[$mkey] = true;

            // Product lookup
            $product = null;
            if ($model !== '') {
                $product = DB::table('products')
                    ->whereRaw('LOWER(sap_model) = ?', [strtolower($model)])
                    ->orWhereRaw('LOWER(code) = ?', [strtolower($model)])
                    ->first();
                if (!$product) { $notes[]='Model belum ada; akan dibuat'; }
            }

            // HS must exist in hs_code_pk_mappings (has PK)
            if ($hs !== '') {
                // Cek eksistensi HS persis seperti yang disimpan pada master (dengan/atau tanpa titik sesuai input HS→PK)
                $existsHs = DB::table('hs_code_pk_mappings')->where('hs_code', $hs)->exists();
                if (!$existsHs) { $status='error'; $notes[]='HS belum punya PK (import HS→PK dulu)'; }
            }

            // Do not overwrite existing different HS
            if ($product && !empty($product->hs_code)) {
                if (strcasecmp((string)$product->hs_code, $hs) !== 0) {
                    $status='error'; $notes[]='Model sudah punya HS berbeda (tidak di-overwrite)';
                } else {
                    $status = $status === 'ok' ? 'skip' : $status; // same mapping
                    $notes[]='Sama dengan HS yang sudah ada (skip)';
                }
            }

            if ($status === 'ok') { $valid++; } else { if ($status === 'error') { $errors++; } }

            $rows[] = [
                'row' => $r,
                'model' => $model,
                'hs_code' => $hs,
                'status' => $status,
                'notes' => implode('; ', $notes),
            ];
        }

        session(['modelhs.preview' => [
            'total' => count($rows),
            'valid' => $valid,
            'error_count' => $errors,
            'rows' => $rows,
        ]]);

        return redirect()->route('admin.mapping.model_hs.preview')
            ->with('status', 'Upload berhasil. Silakan tinjau hasil sebelum publish.');
    }

    public function preview(): View
    {
        $data = session('modelhs.preview');
        if (!$data) {
            return redirect()->route('admin.mapping.model_hs.index')
                ->withErrors(['file' => 'Preview tidak ditemukan. Upload file terlebih dahulu.']);
        }
        return view('admin.mapping.model_hs.preview', ['preview' => $data]);
    }

    public function publish(Request $request)
    {
        $data = session('modelhs.preview');
        if (!$data || empty($data['rows'])) {
            return back()->withErrors(['publish' => 'Tidak ada data untuk dipublish.']);
        }

        $updated = 0; $skipped = 0; $failed = 0;
        // Secara default, buat produk baru jika belum ada (sesuai konsep: product == model)
        $createMissing = $request->boolean('create_missing', true);
        DB::beginTransaction();
        try {
            foreach ($data['rows'] as $row) {
                $model = (string) ($row['model'] ?? '');
                // Simpan HS sesuai format aslinya (mis. 8415.10.20 atau ACC) agar konsisten dengan master dan PO
                $hs = strtoupper(trim((string) ($row['hs_code'] ?? '')));
                // Ensure again PK exists
                $existsHs = DB::table('hs_code_pk_mappings')->where('hs_code', $hs)->exists();
                if (!$existsHs) { $failed++; continue; }

                $product = DB::table('products')
                    ->whereRaw('LOWER(sap_model) = ?', [strtolower($model)])
                    ->orWhereRaw('LOWER(code) = ?', [strtolower($model)])
                    ->first();
                if (!$product) {
                    if ($createMissing) {
                        $newId = DB::table('products')->insertGetId([
                            'code' => $model,
                            'name' => $model,
                            'sap_model' => $model,
                            'hs_code' => $hs,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        if ($newId) { $updated++; } else { $failed++; }
                        continue;
                    }
                    $failed++; continue;
                }
                if (!empty($product->hs_code)) { $skipped++; continue; }

                DB::table('products')->where('id', $product->id)->update([
                    'hs_code' => $hs,
                    'updated_at' => now(),
                ]);
                $updated++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['publish' => 'Gagal publish: '.$e->getMessage()]);
        }

        session()->forget('modelhs.preview');
        return redirect()->route('admin.mapping.model_hs.index')
            ->with('status', sprintf('Publish selesai. Updated=%d, Skipped=%d, Failed=%d', $updated, $skipped, $failed));
    }
}
