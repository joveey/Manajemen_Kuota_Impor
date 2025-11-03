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
use Illuminate\Support\Facades\Schema;

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
            return back()->withErrors(['file' => 'Failed to read file: '.$e->getMessage()])->withInput();
        }

        $result = $validator->validate($rows, $modelMap);
        // Keep raw rows so we can re-validate after fixing mappings without re-uploading
        session(['openpo.preview' => $result, 'openpo.model_map' => $modelMap, 'openpo.rows' => $rows]);

        return view('admin.openpo.preview', [
            'summary' => [
                'groups' => count($result['groups']),
                'rows' => collect($result['groups'])->sum(fn($g) => count($g['lines'] ?? [])),
                'error_count' => (int) $result['error_count'],
            ],
            'result' => $result,
        ]);
    }

    public function previewPage(Request $request, OpenPoValidator $validator): View|RedirectResponse
    {
        // If we have the original rows, re-validate against current mappings so users don't need to re-upload
        $rows = session('openpo.rows');
        $modelMap = session('openpo.model_map', []);
        $result = session('openpo.preview');
        if (is_array($rows)) {
            try {
                $result = $validator->validate($rows, is_array($modelMap) ? $modelMap : []);
                session(['openpo.preview' => $result]);
            } catch (\Throwable $e) {
                // fall back to existing preview if re-validation fails for any reason
            }
        }
        if (!$result || !is_array($result)) {
            return redirect()->route('admin.openpo.form')->withErrors(['file' => 'Preview not found. Reupload the file.']);
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
            return redirect()->route('admin.openpo.form')->withErrors(['file' => 'Preview not found. Reupload the file.']);
        }
        if (($result['error_count'] ?? 0) > 0) {
            return back()->withErrors(['publish' => 'Fix the errors before publishing.']);
        }

        $groups = $result['groups'] ?? [];
        $modelMap = session('openpo.model_map', []);
        // Read publish mode as plain string (avoid Stringable so strict comparisons work)
        $mode = (string) $request->input('publish_mode', 'insert'); // 'insert' | 'replace'
        $inserted = 0; $skippedExisting = 0; $updatedExisting = 0; $replaced = 0; $leftoverAll = 0; $allocatedTotal = 0; $needsReallocCount = 0;

        try {
            DB::transaction(function () use ($groups, $modelMap, $mode, &$inserted, &$skippedExisting, &$updatedExisting, &$replaced, &$leftoverAll, &$allocatedTotal, &$needsReallocCount) {
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

                    // Build header attributes only for columns that exist, for safety when migrations are not fully applied
                    $headerAttrs = [
                        'po_date' => $payload['po_date'] ?? now()->toDateString(),
                        'supplier' => $payload['supplier'] ?? '',
                        'published_at' => now(),
                        'created_by' => Auth::id(),
                    ];
                    if (Schema::hasColumn('po_headers','vendor_number')) { $headerAttrs['vendor_number'] = $payload['vendor_number'] ?? null; }
                    if (Schema::hasColumn('po_headers','currency')) { $headerAttrs['currency'] = $payload['currency'] ?? null; }
                    if (Schema::hasColumn('po_headers','note')) { $headerAttrs['note'] = $payload['note'] ?? null; }
                    $header = PoHeader::{$mode === 'replace' ? 'updateOrCreate' : 'firstOrCreate'}(
                        ['po_number' => (string) $poNumber],
                        $headerAttrs
                    );

                    // Do not write to the product master from the PO mapping sheet.
                    // Mapping in the sheet is only used when resolving HS for lines if needed.

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
                            // Determine the PO year for period-aware mapping
                            $poYear = null;
                            $poDateStr = $payload['po_date'] ?? null;
                            if (!empty($poDateStr)) {
                                try { $poYear = \Illuminate\Support\Carbon::parse((string)$poDateStr)->format('Y'); } catch (\Throwable $e) { $poYear = null; }
                            }

                            if ($hsTable === 'hs_code_pk_mappings') {
                                $hasPeriodCol = Schema::hasColumn($hsTable, 'period_key');
                                if ($poYear) {
                                    $qb = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode);
                                    if ($hasPeriodCol) {
                                        $qb->where(function($q) use ($poYear){ $q->where('period_key',$poYear)->orWhere('period_key',''); })
                                           ->orderByRaw("CASE WHEN period_key = ? THEN 0 WHEN period_key = '' THEN 1 ELSE 2 END", [$poYear]);
                                    }
                                    $row = $qb->first();
                                } else {
                                    $qb = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode);
                                    if ($hasPeriodCol) {
                                        $qb->orderByRaw("CASE WHEN period_key = '' THEN 0 ELSE 1 END")
                                           ->orderByDesc('period_key');
                                    }
                                    $row = $qb->first();
                                }

                                if (!$row && preg_match('/[A-Za-z]/', (string)$hsCode)) {
                                    // Create special HS code (alphabetic, e.g., 'ACC') for this period (or legacy if no date)
                                    $payload = [
                                        $hsCodeCol => $hsCode,
                                        'pk_capacity' => DB::getSchemaBuilder()->hasColumn($hsTable, 'pk_capacity') ? 0 : null,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                                    if ($hasPeriodCol) { $payload['period_key'] = $poYear ?: ''; }
                                    DB::table($hsTable)->insert($payload);

                                    $qb2 = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode);
                                    if ($hasPeriodCol) {
                                        $qb2->when($poYear, function($q) use ($poYear){ $q->where('period_key',$poYear); }, function($q){ $q->where('period_key',''); });
                                    }
                                    $row = $qb2->first();
                                }
                                $hsId = $row->id ?? null;
                            } else {
                                // Fallback path for other hs master schemas
                                $row = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode)->first();
                                if (!$row && preg_match('/[A-Za-z]/', (string)$hsCode)) {
                                    DB::table($hsTable)->insert([
                                        $hsCodeCol => $hsCode,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                    $row = DB::table($hsTable)->select('id')->where($hsCodeCol, $hsCode)->first();
                                }
                                $hsId = $row->id ?? null;
                            }
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
                        // Optional new columns only if present in schema
                        if (Schema::hasColumn('po_lines','qty_to_invoice')) { $payloadAttrs['qty_to_invoice'] = isset($line['qty_to_invoice']) ? (float)$line['qty_to_invoice'] : null; }
                        if (Schema::hasColumn('po_lines','qty_to_deliver')) { $payloadAttrs['qty_to_deliver'] = isset($line['qty_to_deliver']) ? (float)$line['qty_to_deliver'] : null; }
                        if (Schema::hasColumn('po_lines','storage_location')) { $payloadAttrs['storage_location'] = $line['storage_location'] ?? null; }

                        if ($mode === 'replace') {
                            $poLine = PoLine::create(array_merge($unique, $payloadAttrs));
                            $inserted++;
                        } else { // insert-or-diff-update
                            $existingLine = PoLine::where($unique)->first();
                            if ($existingLine) {
                                $prevQty = (float) ($existingLine->qty_ordered ?? 0);
                                $newQty = (float) ($line['qty_ordered'] ?? 0);
                                $delta = (int) round($newQty - $prevQty);

                                // Update attributes regardless
                                $existingLine->fill($payloadAttrs);
                                $existingLine->qty_ordered = $newQty;
                                $existingLine->save();
                                $poLine = $existingLine; // for allocation marking below
                                $updatedExisting++;

                                if ($delta !== 0) {
                                    $allocDate = $line['eta_date'] ?? ($payload['po_date'] ?? $po->order_date ?? now()->toDateString());
                                    $candidates = \App\Models\Quota::query()
                                        ->where('is_active', true)
                                        ->whereDate('period_start', '<=', $allocDate)
                                        ->whereDate('period_end', '>=', $allocDate)
                                        ->get()
                                        ->filter(function ($q) use ($product) { return $q->matchesProduct($product); });
                                    $choose = function ($q) {
                                        $min = is_null($q->min_pk) ? null : (float)$q->min_pk;
                                        $max = is_null($q->max_pk) ? null : (float)$q->max_pk;
                                        if ($min === null || $max === null) { return INF; }
                                        return max(0.0, $max - $min);
                                    };
                                    $quota = $candidates->sortBy($choose)->first();

                                    if ($delta > 0) {
                                        // allocate additional quantity, no carry-over
                                        $left = $delta;
                                        if ($quota) {
                                            $avail = (int) max((int) ($quota->forecast_remaining ?? 0), 0);
                                            $take = min($left, $avail);
                                            if ($take > 0) {
                                                $quota->decrementForecast($take, 'Forecast delta+ for PO '.$po->po_number.' (line '.$poLine->line_no.')', $po, new \DateTimeImmutable((string)$allocDate), Auth::id());
                                                // Upsert pivot
                                                $existing = DB::table('purchase_order_quota')
                                                    ->where('purchase_order_id', $po->id)
                                                    ->where('quota_id', $quota->id)
                                                    ->first();
                                                if ($existing) {
                                                    DB::table('purchase_order_quota')
                                                        ->where('id', $existing->id)
                                                        ->update(['allocated_qty' => (int)$existing->allocated_qty + $take, 'updated_at' => now()]);
                                                } else {
                                                    DB::table('purchase_order_quota')->insert([
                                                        'purchase_order_id' => $po->id,
                                                        'quota_id' => $quota->id,
                                                        'allocated_qty' => $take,
                                                        'created_at' => now(),
                                                        'updated_at' => now(),
                                                    ]);
                                                }
                                                $allocatedTotal += (int) $take;
                                                $left -= (int) $take;
                                            }
                                        }
                                        if ($left > 0) { $leftoverAll += (int)$left; $needsReallocCount++; DB::table('po_lines')->where('id',$poLine->id)->update(['needs_reallocation'=>true]); }
                                    } else { // $delta < 0, refund
                                        $refund = -$delta;
                                        if ($quota) {
                                            $existing = DB::table('purchase_order_quota')
                                                ->where('purchase_order_id', $po->id)
                                                ->where('quota_id', $quota->id)
                                                ->first();
                                            $canRefund = (int) min($refund, (int) ($existing->allocated_qty ?? 0));
                                            if ($canRefund > 0) {
                                                // Increase forecast back
                                                $quota->incrementForecast($canRefund, 'Forecast delta- for PO '.$po->po_number.' (line '.$poLine->line_no.')', $po, new \DateTimeImmutable((string)$allocDate), Auth::id());
                                                // Update pivot
                                                DB::table('purchase_order_quota')
                                                    ->where('purchase_order_id', $po->id)
                                                    ->where('quota_id', $quota->id)
                                                    ->update(['allocated_qty' => (int)max(0, ((int)($existing->allocated_qty ?? 0)) - $canRefund), 'updated_at' => now()]);
                                                $refund -= $canRefund;
                                            }
                                        }
                                        // If some portion cannot be refunded (likely moved via Move Quota), leave as-is.
                                    }
                                }
                            } else {
                                $poLine = PoLine::create(array_merge($unique, $payloadAttrs));
                                $inserted++;
                            }
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

                        // Allocate per line quantity by delivery date WITHOUT carry-over
                        $lineQty = (int) ($line['qty_ordered'] ?? 0);
                        if ($lineQty > 0 && (empty($poLine->forecast_allocated_at))) {
                            $allocDate = $line['eta_date'] ?? ($payload['po_date'] ?? $po->order_date ?? now()->toDateString());

                            // Find single matching quota for the delivery date
                            $candidates = \App\Models\Quota::query()
                                ->where('is_active', true)
                                ->whereDate('period_start', '<=', $allocDate)
                                ->whereDate('period_end', '>=', $allocDate)
                                ->get()
                                ->filter(function ($q) use ($product) { return $q->matchesProduct($product); });

                            // choose the narrowest PK range when multiple match
                            $choose = function ($q) {
                                $min = is_null($q->min_pk) ? null : (float)$q->min_pk;
                                $max = is_null($q->max_pk) ? null : (float)$q->max_pk;
                                if ($min === null || $max === null) { return INF; }
                                return max(0.0, $max - $min);
                            };
                            $quota = $candidates->sortBy($choose)->first();

                            $left = $lineQty;
                            if ($quota) {
                                $avail = (int) max((int) ($quota->forecast_remaining ?? 0), 0);
                                $take = min($lineQty, $avail);
                                if ($take > 0) {
                                    $quota->decrementForecast($take, 'Forecast allocated for PO '.$po->po_number.' (line '.$poLine->line_no.')', $po, new \DateTimeImmutable((string)$allocDate), Auth::id());

                                    // Upsert/accumulate pivot
                                    $existing = DB::table('purchase_order_quota')
                                        ->where('purchase_order_id', $po->id)
                                        ->where('quota_id', $quota->id)
                                        ->first();
                                    if ($existing) {
                                        DB::table('purchase_order_quota')
                                            ->where('id', $existing->id)
                                            ->update(['allocated_qty' => (int)$existing->allocated_qty + $take, 'updated_at' => now()]);
                                    } else {
                                        DB::table('purchase_order_quota')->insert([
                                            'purchase_order_id' => $po->id,
                                            'quota_id' => $quota->id,
                                            'allocated_qty' => $take,
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ]);
                                    }
                                    $allocatedTotal += (int) $take;
                                    $left -= (int) $take;
                                }
                            }
                            if ($left > 0) { $leftoverAll += (int)$left; $needsReallocCount++; if (Schema::hasTable('po_lines') && Schema::hasColumn('po_lines','needs_reallocation')) { DB::table('po_lines')->where('id',$poLine->id)->update(['needs_reallocation'=>true]); } }
                            // mark line allocated to avoid re-run duplication
                            if (Schema::hasColumn('po_lines', 'forecast_allocated_at')) { PoLine::where('id', $poLine->id)->update(['forecast_allocated_at' => now()]); }
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['publish' => 'Failed to publish: '.$e->getMessage()]);
        }

        session()->forget('openpo.preview');
        session()->forget('openpo.model_map');
        session()->forget('openpo.rows');
        $msg = 'Open PO published successfully. Mode: '.($mode === 'replace' ? 'Replace' : 'Insert').'. Added: '.$inserted.'.'
            .($skippedExisting>0 ? ' Duplicates skipped: '.$skippedExisting.'.' : '')
            .($updatedExisting>0 ? ' Updated: '.$updatedExisting.'.' : '')
            .($replaced>0 ? ' Headers replaced: '.$replaced.'.' : '')
            .' Allocated qty: '.number_format($allocatedTotal).'. Needs reallocation lines: '.number_format($needsReallocCount).'.';
        $redir = redirect()->route('admin.openpo.form')->with('status', $msg);
        if ($leftoverAll > 0) {
            $redir->with('warning', 'Unallocated quantity (insufficient quota): '.number_format($leftoverAll).' units. Use Move Quota to resolve.');
        }
        return $redir;
    }
}

