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
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\QuotaAllocationService;

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

    public function previewPage(Request $request): View|RedirectResponse
    {
        $result = session('openpo.preview');
        if (!$result || !is_array($result)) {
            return redirect()->route('admin.openpo.form')->withErrors(['file' => 'Preview tidak ditemukan. Upload ulang file.']);
        }

        $summary = [
            'groups' => count($result['groups'] ?? []),
            'rows' => collect($result['groups'] ?? [])->sum(fn($g) => count($g['lines'] ?? [])),
            'error_count' => (int) ($result['error_count'] ?? 0),
        ];

        return view('admin.openpo.preview', compact('summary', 'result'));
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
        // Read publish mode as plain string (avoid Stringable so strict comparisons work)
        $mode = (string) $request->input('publish_mode', 'insert'); // 'insert' | 'replace'
        $inserted = 0; $skippedExisting = 0; $replaced = 0; $leftoverAll = 0;

        try {
            DB::transaction(function () use ($groups, $modelMap, $mode, &$inserted, &$skippedExisting, &$replaced, &$leftoverAll) {
                $hsTable = DB::getSchemaBuilder()->hasTable('hs_codes') ? 'hs_codes' : 'hs_code_pk_mappings';
                $hsCodeCol = $hsTable === 'hs_codes' ? 'code' : 'hs_code';
                $modelMapUpper = collect($modelMap ?? [])->mapWithKeys(fn($v,$k)=>[strtoupper((string)$k)=>$v])->all();

                foreach ($groups as $poNumber => $payload) {
                    if ($mode === 'replace') {
                        $target = PoHeader::where('po_number', (string)$poNumber)->first();
                        if ($target) {
                            PoLine::where('po_header_id', $target->id)->delete();
                            $replaced++;
                        }
                    }

                    $header = PoHeader::{$mode === 'replace' ? 'updateOrCreate' : 'firstOrCreate'}(
                        ['po_number' => (string) $poNumber],
                        [
                            'po_date' => $payload['po_date'] ?? now()->toDateString(),
                            'supplier' => $payload['supplier'] ?? '',
                            'vendor_number' => $payload['vendor_number'] ?? null,
                            'currency' => $payload['currency'] ?? null,
                            'note' => $payload['note'] ?? null,
                            'published_at' => now(),
                            'created_by' => Auth::id(),
                        ]
                    );

                    // Persist mapping from mapping sheet into products.hs_code first (ensures fallback available)
                    foreach (($modelMap ?? []) as $model => $hs) {
                        if (!$model || !$hs) { continue; }
                        DB::table('products')
                            ->where('sap_model', $model)
                            ->orWhere('code', $model)
                            ->update(['hs_code' => $hs, 'updated_at' => now()]);
                    }

                    // Prefetch product->hs map for models in this PO
                    $models = collect($payload['lines'])->pluck('model_code')->filter()->map(fn($m)=> (string)$m)->unique()->values()->all();
                    $productRows = DB::table('products')
                        ->select(['sap_model','code','hs_code'])
                        ->whereIn('sap_model', $models)
                        ->orWhereIn('code', $models)
                        ->get();
                    $productHsMap = [];
                    foreach ($productRows as $pr) {
                        if (!empty($pr->sap_model)) { $productHsMap[strtoupper($pr->sap_model)] = $pr->hs_code; }
                        if (!empty($pr->code)) { $productHsMap[strtoupper($pr->code)] = $pr->hs_code; }
                    }

                    foreach ($payload['lines'] as $line) {
                        $modelCode = strtoupper((string) ($line['model_code'] ?? ''));
                        $hsCode = $line['hs_code'] ?? null;
                        if (empty($hsCode) && $modelCode !== '') {
                            $hsCode = $modelMapUpper[$modelCode] ?? ($productHsMap[$modelCode] ?? null);
                        }

                        $hsId = null;
                        if (!empty($hsCode)) {
                            $row = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode)->first();
                            if (!$row && preg_match('/[A-Za-z]/', (string)$hsCode)) {
                                // Create special HS code (alphabetic, e.g., 'ACC') with default pk_capacity=0
                                DB::table($hsTable)->insert([
                                    $hsCodeCol => $hsCode,
                                    // add pk_capacity only if the column exists (hs_code_pk_mappings schema)
                                    'pk_capacity' => DB::getSchemaBuilder()->hasColumn($hsTable, 'pk_capacity') ? 0 : null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                $row = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode)->first();
                            }
                            $hsId = $row->id ?? null;
                        }

                        // If hs_id still null while HS table exists and is required, stop with a clear message
                        if ($hsId === null && $hsTable) {
                            throw new \RuntimeException('HS->PK atau Quick HS.');
                        }

                        $unique = ['po_header_id' => $header->id, 'model_code' => (string) $line['model_code'], 'line_no' => (string)($line['line_no'] ?? '')];

                        $payloadAttrs = [
                                'item_desc' => $line['item_desc'] ?? null,
                                'hs_code_id' => $hsId,
                                'qty_ordered' => (float) $line['qty_ordered'],
                                'qty_received' => 0,
                                'uom' => $line['uom'] ?? null,
                                'eta_date' => $line['eta_date'] ?? null,
                                'warehouse_code' => $line['wh_code'] ?? null,
                                'warehouse_name' => $line['wh_name'] ?? null,
                                'warehouse_source' => $line['wh_source'] ?? null,
                                'subinventory_code' => $line['subinv_code'] ?? null,
                                'subinventory_name' => $line['subinv_name'] ?? null,
                                'subinventory_source' => $line['subinv_source'] ?? null,
                                'amount' => isset($line['amount']) ? (float) $line['amount'] : null,
                                'category_code' => $line['cat_code'] ?? null,
                                'category' => $line['cat_desc'] ?? null,
                                'material_group' => $line['mat_grp'] ?? null,
                                'sap_order_status' => $line['sap_status'] ?? null,
                                'validation_status' => 'ok',
                                'validation_notes' => null,
                            ];

                        if ($mode === 'replace') {
                            $poLine = PoLine::create(array_merge($unique, $payloadAttrs));
                            $inserted++;
                        } else { // insert-only
                            $exists = PoLine::where($unique)->exists();
                            if ($exists) { $skippedExisting++; continue; }
                            $poLine = PoLine::create(array_merge($unique, $payloadAttrs));
                            $inserted++;
                        }

                        // Create/Update PurchaseOrder per PO line to drive forecast allocation
                        $product = Product::query()
                            ->whereRaw('LOWER(sap_model) = ?', [strtolower((string)$line['model_code'])])
                            ->orWhereRaw('LOWER(code) = ?', [strtolower((string)$line['model_code'])])
                            ->first();
                        if (!$product) {
                            // Minimal fallback product to keep pipeline moving
                            $product = Product::create([
                                'code' => (string)$line['model_code'],
                                'name' => (string)$line['model_code'],
                                'sap_model' => (string)$line['model_code'],
                                'is_active' => true,
                            ]);
                        }

                        // PurchaseOrder keyed only by po_number (table has unique on po_number)
                        $po = PurchaseOrder::updateOrCreate(
                            [
                                'po_number' => (string) $poNumber,
                            ],
                            [
                                'product_id' => $product->id,
                                'quantity' => (int) max((int)($line['qty_ordered'] ?? 0), (int) (\App\Models\PurchaseOrder::where('po_number',(string)$poNumber)->value('quantity') ?? 0)),
                                'amount' => isset($line['amount']) ? (float)$line['amount'] : null,
                                'order_date' => $payload['po_date'] ?? now()->toDateString(),
                                'vendor_number' => $payload['vendor_number'] ?? null,
                                'vendor_name' => $payload['supplier'] ?? null,
                                'status' => \App\Models\PurchaseOrder::STATUS_ORDERED,
                                'remarks' => 'Imported via Open PO',
                                'plant_name' => 'Open PO',
                                'plant_detail' => 'Imported via Open PO flow',
                                'created_by' => Auth::id(),
                            ]
                        );

                        // Allocate per line quantity (pivot aggregates per PO). Mark line processed to avoid duplicates.
                        $lineQty = (int) ($line['qty_ordered'] ?? 0);
                        if ($lineQty > 0 && (empty($poLine->forecast_allocated_at))) {
                            [$allocs, $left] = app(QuotaAllocationService::class)
                                ->allocateForecast($product->id, $lineQty, $po->order_date, $po);
                            $leftoverAll += (int) $left;
                            // mark line allocated to avoid re-run duplication
                            PoLine::where('id', $poLine->id)->update(['forecast_allocated_at' => now()]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['publish' => 'Gagal publish: '.$e->getMessage()]);
        }

        session()->forget('openpo.preview');
        session()->forget('openpo.model_map');
        $msg = 'Open PO berhasil dipublish. Mode: '.($mode === 'replace' ? 'Replace' : 'Insert').'. Ditambahkan: '.$inserted.'.'.($skippedExisting>0 ? ' Duplikat dilewati: '.$skippedExisting.'.' : '').($replaced>0 ? ' Header diganti: '.$replaced.'.' : '');
        $redir = redirect()->route('admin.openpo.form')->with('status', $msg);
        if ($leftoverAll > 0) {
            $redir->with('warning', 'Sebagian tidak teralokasi: '.number_format($leftoverAll).' unit. Mohon lengkapi kuota periode berikutnya.');
        }
        return $redir;
    }
}
