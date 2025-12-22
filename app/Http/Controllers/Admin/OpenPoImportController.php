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
use App\Models\Quota;
use App\Models\QuotaHistory;
use App\Models\PoLineVoyageSplit;
use Illuminate\Support\Facades\Log;

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

        // Record uploaded filename for audit trail
        $request->attributes->set('audit_extra', [
            'file' => basename($storedPath),
        ]);

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

        // Prefetch current product -> HS mapping to hint the preview about missing model mappings
        try {
            $modelKeys = [];
            foreach (($result['groups'] ?? []) as $g) {
                foreach (($g['lines'] ?? []) as $ln) {
                    $m = strtoupper((string)($ln['model_code'] ?? ''));
                    if ($m !== '') { $modelKeys[$m] = true; }
                }
            }
            $models = array_keys($modelKeys);
            $productHsMap = [];
            if (!empty($models)) {
                $rows2 = DB::table('products')
                    ->select(['sap_model','code','hs_code'])
                    ->whereIn('sap_model', $models)
                    ->orWhereIn('code', $models)
                    ->get();
                foreach ($rows2 as $pr) {
                    if (!empty($pr->sap_model)) { $productHsMap[strtoupper((string)$pr->sap_model)] = (string)($pr->hs_code ?? ''); }
                    if (!empty($pr->code)) { $productHsMap[strtoupper((string)$pr->code)] = (string)($pr->hs_code ?? ''); }
                }
            }
        } catch (\Throwable $e) { $productHsMap = []; }

        return view('admin.openpo.preview', [
            'summary' => [
                'groups' => count($result['groups']),
                'rows' => collect($result['groups'])->sum(fn($g) => count($g['lines'] ?? [])),
                'error_count' => (int) $result['error_count'],
            ],
            'result' => $result,
            'productHsMap' => $productHsMap,
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

        // Prefetch current product -> HS mapping for the models in this preview
        try {
            $modelKeys = [];
            foreach (($result['groups'] ?? []) as $g) {
                foreach (($g['lines'] ?? []) as $ln) {
                    $m = strtoupper((string)($ln['model_code'] ?? ''));
                    if ($m !== '') { $modelKeys[$m] = true; }
                }
            }
            $models = array_keys($modelKeys);
            $productHsMap = [];
            if (!empty($models)) {
                $rows2 = DB::table('products')
                    ->select(['sap_model','code','hs_code'])
                    ->whereIn('sap_model', $models)
                    ->orWhereIn('code', $models)
                    ->get();
                foreach ($rows2 as $pr) {
                    if (!empty($pr->sap_model)) { $productHsMap[strtoupper((string)$pr->sap_model)] = (string)($pr->hs_code ?? ''); }
                    if (!empty($pr->code)) { $productHsMap[strtoupper((string)$pr->code)] = (string)($pr->hs_code ?? ''); }
                }
            }
        } catch (\Throwable $e) { $productHsMap = []; }

        return view('admin.openpo.preview', compact('summary', 'result', 'productHsMap'));
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
        $sampleRows = [];
        // Read publish mode as plain string (avoid Stringable so strict comparisons work)
        $mode = (string) $request->input('publish_mode', 'insert'); // 'insert' | 'replace'
        $inserted = 0; $skippedExisting = 0; $updatedExisting = 0; $replaced = 0; $leftoverAll = 0; $allocatedTotal = 0; $needsReallocCount = 0;
        $poQtyColumn = Schema::hasColumn('purchase_orders', 'qty')
            ? 'qty'
            : (Schema::hasColumn('purchase_orders', 'quantity') ? 'quantity' : null);

        try {
            DB::transaction(function () use ($groups, $modelMap, $mode, &$inserted, &$skippedExisting, &$updatedExisting, &$replaced, &$leftoverAll, &$allocatedTotal, &$needsReallocCount, &$sampleRows, $poQtyColumn) {
                $hsTable = DB::getSchemaBuilder()->hasTable('hs_codes') ? 'hs_codes' : 'hs_code_pk_mappings';
                $hsCodeCol = $hsTable === 'hs_codes' ? 'code' : 'hs_code';
                $modelMapUpper = collect($modelMap ?? [])->mapWithKeys(fn($v,$k)=>[strtoupper((string)$k)=>$v])->all();

                foreach ($groups as $poNumber => $payload) {
                    $poNumberStr = (string) $poNumber;
                    if ($mode === 'replace') {
                        $target = PoHeader::where('po_number', $poNumberStr)->first();
                        // Roll back existing forecast/pivot effects before re-importing
                        $this->rollbackPoForReplace($poNumberStr, $target);
                        if ($target) { $replaced++; }
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
                                    try {
                                        $quota = $this->resolveQuotaForDeliveryDate(
                                            $allocDate,
                                            $product,
                                            $poNumberStr,
                                            (string)($line['line_no'] ?? ''),
                                            (string)($line['model_code'] ?? ''),
                                            $payload['po_date'] ?? null
                                        );
                                    } catch (\RuntimeException $e) {
                                        $quota = null;
                                    }

                                    if ($delta > 0) {
                                        // allocate additional quantity, no carry-over
                                        $left = $delta;
                                        try {
                                            if ($quota) {
                                                $avail = (int) max((int) ($quota->forecast_remaining ?? 0), 0);
                                                $take = min($left, $avail);
                                                if ($take > 0) {
                                                    $quota->decrementForecast($take, 'Forecast delta+ for PO '.$po->po_number.' (line '.$poLine->line_no.')', $po, new \DateTimeImmutable((string)$allocDate), Auth::id());
                                                    $this->applyPivotDelta($po, $quota, (int)$take, $allocDate, 'delta+', (string)($line['line_no'] ?? ''), (string)($line['model_code'] ?? ''));
                                                    $allocatedTotal += (int) $take;
                                                    $left -= (int) $take;
                                                }
                                            }
                                        } catch (\RuntimeException $e) {
                                            // mark for reallocation below
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
                                                $this->applyPivotDelta($po, $quota, (int) -$canRefund, $allocDate, 'delta-', (string)($line['line_no'] ?? ''), (string)($line['model_code'] ?? ''));
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

                        // capture up to 3 sample rows for audit details
                        if (count($sampleRows) < 3) {
                            $sampleRows[] = [
                                'po_number' => (string) $poNumber,
                                'line_no' => (string) ($line['line_no'] ?? ''),
                                'model_code' => (string) ($line['model_code'] ?? ''),
                                'qty_ordered' => (float) ($line['qty_ordered'] ?? 0),
                                'eta_date' => (string) ($line['eta_date'] ?? ''),
                            ];
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
                                'hs_code' => $hsCode ?: null,
                                'pk_capacity' => null,
                            ]);
                        }
                        // Ensure product carries HS/PK for quota matching
                        $resolvedPk = null;
                        if (!empty($hsCode)) {
                            try { $resolvedPk = app(\App\Services\HsCodeResolver::class)->resolvePkForHsCode((string)$hsCode, $poYear); } catch (\Throwable $e) { $resolvedPk = null; }
                        }
                        $needsSave = false;
                        if (!empty($hsCode) && (string) ($product->hs_code ?? '') !== (string) $hsCode) {
                            $product->hs_code = (string) $hsCode;
                            $needsSave = true;
                        }
                        if ($product->pk_capacity === null && $resolvedPk !== null) {
                            $product->pk_capacity = $resolvedPk;
                            $needsSave = true;
                        }
                        if ($needsSave) {
                            try { $product->save(); } catch (\Throwable $e) { /* tolerate if product table pruned */ }
                        }

                        // PurchaseOrder keyed only by po_doc (table has unique on po_doc)
                        $po = PurchaseOrder::updateOrCreate(
                            [
                                'po_doc' => (string) $poNumber,
                            ],
                            [
                                'product_id' => $product->id,
                                'quantity' => (int) max(
                                    (int)($line['qty_ordered'] ?? 0),
                                    $poQtyColumn
                                        ? (int) (DB::table('purchase_orders')
                                            ->where('po_doc', (string) $poNumber)
                                            ->value($poQtyColumn) ?? 0)
                                        : 0
                                ),
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
                            // IMPORTANT: choose quota strictly by PK match AND delivery_date inside period, never overflow to next period/year here
                            try {
                                $quota = $this->resolveQuotaForDeliveryDate(
                                    $allocDate,
                                    $product,
                                    $poNumberStr,
                                    (string)($line['line_no'] ?? ''),
                                    (string)($line['model_code'] ?? ''),
                                    $payload['po_date'] ?? null
                                );
                            } catch (\RuntimeException $e) {
                                $quota = null;
                            }

                            $left = $lineQty;
                            if ($quota) {
                                $avail = (int) max((int) ($quota->forecast_remaining ?? 0), 0);
                                $take = min($lineQty, $avail);
                                if ($take > 0) {
                                    $quota->decrementForecast($take, 'Forecast allocated for PO '.$po->po_number.' (line '.$poLine->line_no.')', $po, new \DateTimeImmutable((string)$allocDate), Auth::id());

                                    // Upsert/accumulate pivot
                                    $this->applyPivotDelta($po, $quota, (int) $take, $allocDate, 'alloc', (string)($line['line_no'] ?? ''), (string)($line['model_code'] ?? ''));
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

        // Provide a concise summary to the audit logger, including tables/columns and sample rows
        $request->attributes->set('audit_extra', [
            'publish_mode' => (string) $mode,
            'created' => (int) $inserted,
            'updated' => (int) $updatedExisting,
            'duplicated' => (int) $skippedExisting,
            'replaced_headers' => (int) $replaced,
            'allocated' => (int) $allocatedTotal,
            'needs_reallocation' => (int) $needsReallocCount,
            'tables' => [
                'po_headers' => [
                    ($mode === 'replace' ? 'upsert' : 'firstOrCreate') => [
                        'po_number','po_date','supplier',
                        (Schema::hasColumn('po_headers','vendor_number') ? 'vendor_number' : null),
                        (Schema::hasColumn('po_headers','currency') ? 'currency' : null),
                        (Schema::hasColumn('po_headers','note') ? 'note' : null),
                        'published_at','created_by'
                    ]
                ],
                'po_lines' => [
                    ($mode === 'replace' ? 'insert' : 'insert/update') => [
                        'item_desc','hs_code_id','qty_ordered','qty_received','uom','eta_date',
                        (Schema::hasColumn('po_lines','warehouse_code') ? 'warehouse_code' : null),
                        (Schema::hasColumn('po_lines','warehouse_name') ? 'warehouse_name' : null),
                        (Schema::hasColumn('po_lines','warehouse_source') ? 'warehouse_source' : null),
                        (Schema::hasColumn('po_lines','subinventory_code') ? 'subinventory_code' : null),
                        (Schema::hasColumn('po_lines','subinventory_name') ? 'subinventory_name' : null),
                        (Schema::hasColumn('po_lines','subinventory_source') ? 'subinventory_source' : null),
                        (Schema::hasColumn('po_lines','amount') ? 'amount' : null),
                        (Schema::hasColumn('po_lines','category_code') ? 'category_code' : null),
                        (Schema::hasColumn('po_lines','category') ? 'category' : null),
                        (Schema::hasColumn('po_lines','material_group') ? 'material_group' : null),
                        (Schema::hasColumn('po_lines','sap_order_status') ? 'sap_order_status' : null),
                        (Schema::hasColumn('po_lines','qty_to_invoice') ? 'qty_to_invoice' : null),
                        (Schema::hasColumn('po_lines','qty_to_deliver') ? 'qty_to_deliver' : null),
                        (Schema::hasColumn('po_lines','storage_location') ? 'storage_location' : null),
                        (Schema::hasColumn('po_lines','forecast_allocated_at') ? 'forecast_allocated_at' : null),
                        (Schema::hasColumn('po_lines','needs_reallocation') ? 'needs_reallocation' : null),
                    ]
                ],
                'purchase_order_quota' => [
                    'insert/update' => ['purchase_order_id','quota_id','allocated_qty']
                ],
            ],
            'sample_rows' => $sampleRows,
        ]);

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

    /*
     * purchase_order_quota writers:
     * - Admin\OpenPoImportController::publish (Open PO import/REPLACE) — MUST use resolveQuotaForDeliveryDate + applyPivotDelta
     * - Admin\PurchaseOrderVoyageController::bulkUpdate / ::moveSplitQuota (voyage split/move workflow)
     * - Admin\PurchaseOrderController::store/update quota moves (manual adjustments)
     * - Console\Commands\AllocBackfillForecast::handle / RebuildForecast::handle (maintenance/backfill)
     */

    /**
     * Resolve a single active quota that matches the product PK bucket AND covers the delivery date.
     * All Open PO allocations must call this; allocating a PO line to a quota whose period does not
     * contain the delivery date is illegal and will throw.
     */
    private function resolveQuotaForDeliveryDate($deliveryDate, Product $product, string $poNumber, string $lineNo, string $material = '', ?string $poDate = null): Quota
    {
        $dateStr = $this->normalizeDeliveryDate($deliveryDate, $poNumber, $lineNo, $poDate);

        $baseQuery = Quota::query()
            ->where('is_active', true)
            ->whereDate('period_start', '<=', $dateStr)
            ->whereDate('period_end', '>=', $dateStr)
            ->orderBy('period_start')
            ->orderBy('period_end')
            ->orderBy('id');

        $candidates = $baseQuery->get()
            ->filter(function ($q) use ($product) { return $q->matchesProduct($product); })
            ->values();

        if ($candidates->count() === 1) {
            $quota = $candidates->first();
            Log::info('resolveQuotaForDeliveryDate', [
                'po_number' => $poNumber,
                'line_no' => $lineNo,
                'material' => $material,
                'delivery_date' => $dateStr,
                'pk_bucket' => $product->pk_capacity ?? null,
                'selected_quota' => $quota->id,
                'period_start' => $this->formatDateValue($quota->period_start),
                'period_end' => $this->formatDateValue($quota->period_end),
            ]);
            return $quota;
        }

        if ($candidates->isEmpty()) {
            // Fallback 1: product-quota mapping (legacy) ignoring PK buckets
            if (\Illuminate\Support\Facades\Schema::hasTable('product_quota_mappings')) {
                $mapped = $baseQuery
                    ->whereHas('products', fn($q) => $q->where('products.id', $product->id ?? 0))
                    ->first();
                if ($mapped) {
                    Log::warning('resolveQuotaForDeliveryDate_fallback_mapped', [
                        'po_number' => $poNumber,
                        'line_no' => $lineNo,
                        'material' => $material,
                        'delivery_date' => $dateStr,
                        'mapped_quota_id' => $mapped->id,
                    ]);
                    return $mapped;
                }
            }

            // Fallback 2: any active quota covering the date (ignoring PK)
            $any = $baseQuery->first();
            if ($any) {
                Log::warning('resolveQuotaForDeliveryDate_fallback_any', [
                    'po_number' => $poNumber,
                    'line_no' => $lineNo,
                    'material' => $material,
                    'delivery_date' => $dateStr,
                    'quota_id' => $any->id,
                ]);
                return $any;
            }

            throw new \RuntimeException('No quota found for delivery '.$dateStr.' (PO '.$poNumber.', line '.($lineNo ?: '-').').');
        }

        throw new \RuntimeException('Multiple overlapping quotas for delivery '.$dateStr.' (PO '.$poNumber.', line '.($lineNo ?: '-').').');
    }

    private function normalizeDeliveryDate($deliveryDate, string $poNumber, string $lineNo, ?string $poDate = null): string
    {
        if (empty($deliveryDate)) {
            throw new \RuntimeException('Delivery date missing for PO '.$poNumber.' (line '.($lineNo ?: '-').').');
        }

        try {
            $parsed = \Illuminate\Support\Carbon::parse((string)$deliveryDate);
            $parsedStr = $parsed->toDateString();
            // Guard against outlier years (e.g., Excel 2-digit year) by snapping to PO year when available
            if ($poDate) {
                try {
                    $poYear = \Illuminate\Support\Carbon::parse((string)$poDate)->year;
                    if ($parsed->year > $poYear + 1) {
                        $fallback = \Illuminate\Support\Carbon::parse((string)$poDate)->toDateString();
                        \Log::warning('normalizeDeliveryDate_out_of_range', [
                            'po_number' => $poNumber,
                            'line_no' => $lineNo,
                            'raw' => (string) $deliveryDate,
                            'parsed' => $parsedStr,
                            'po_date' => $fallback,
                            'snap_to_po_year' => true,
                        ]);
                        return $fallback;
                    }
                } catch (\Throwable $e) {
                    // ignore and return parsed
                }
            }
            return $parsedStr;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid delivery date for PO '.$poNumber.' (line '.($lineNo ?: '-').').');
        }
    }

    private function formatDateValue($value): ?string
    {
        if (empty($value)) { return null; }
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function guardQuotaCoversDate(Quota $quota, string $dateStr, string $poNumber, string $lineNo): void
    {
        $start = $this->formatDateValue($quota->period_start);
        $end = $this->formatDateValue($quota->period_end);
        if (($start && $start > $dateStr) || ($end && $end < $dateStr)) {
            throw new \RuntimeException(
                "Invariant violated: quota {$quota->id} period {$start}..{$end} does not cover delivery date {$dateStr} (PO {$poNumber}, line ".($lineNo ?: '-').')'
            );
        }
    }

    private function applyPivotDelta(PurchaseOrder $po, Quota $quota, int $deltaQty, $deliveryDate, string $reason, string $lineNo = '', string $material = ''): void
    {
        $dateStr = $this->normalizeDeliveryDate($deliveryDate, (string) $po->po_number, $lineNo);
        $this->guardQuotaCoversDate($quota, $dateStr, (string) $po->po_number, $lineNo);

        Log::info('po_quota_insert', [
            'po_id' => $po->id ?? null,
            'po_number' => $po->po_number ?? null,
            'quota_id' => $quota->id,
            'allocated_qty_delta' => $deltaQty,
            'delivery_date' => $dateStr,
            'reason' => $reason,
            'line_no' => $lineNo,
            'material' => $material,
        ]);

        $pivot = DB::table('purchase_order_quota')
            ->where('purchase_order_id', $po->id)
            ->where('quota_id', $quota->id)
            ->lockForUpdate()
            ->first();

        $newAlloc = ($pivot ? (int) $pivot->allocated_qty : 0) + $deltaQty;

        if ($newAlloc > 0) {
            if ($pivot) {
                DB::table('purchase_order_quota')
                    ->where('id', $pivot->id)
                    ->update(['allocated_qty' => $newAlloc, 'updated_at' => now()]);
            } else {
                DB::table('purchase_order_quota')->insert([
                    'purchase_order_id' => $po->id,
                    'quota_id' => $quota->id,
                    'allocated_qty' => $newAlloc,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($pivot) {
            DB::table('purchase_order_quota')->where('id', $pivot->id)->delete();
        }
    }

    /**
     * Roll back all forecast/pivot effects of an existing PO before REPLACE.
     */
    private function rollbackPoForReplace(string $poNumber, ?PoHeader $header): void
    {
        $poModel = PurchaseOrder::where('po_doc', $poNumber)->first();
        $poDate = $header?->po_date ?? $poModel?->order_date;
        $occurredOn = $poDate ? new \DateTimeImmutable((string) $poDate) : null;
        $userId = Auth::id();

        $netByQuota = [];

        if ($poModel) {
            $hist = DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(quantity_change) as net_qty'))
                ->whereIn('change_type', [QuotaHistory::TYPE_FORECAST_DECREASE, QuotaHistory::TYPE_FORECAST_INCREASE])
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $poModel->id)
                ->groupBy('quota_id')
                ->get();

            foreach ($hist as $row) {
                $qid = (int) ($row->quota_id ?? 0);
                if ($qid > 0) {
                    $netByQuota[$qid] = ($netByQuota[$qid] ?? 0) + (int) $row->net_qty;
                }
            }
        }

        $splitIds = [];
        if ($header && Schema::hasTable('po_line_voyage_splits')) {
            $lineIds = DB::table('po_lines')
                ->where('po_header_id', $header->id)
                ->pluck('id');

            if ($lineIds->isNotEmpty()) {
                $splitIds = DB::table('po_line_voyage_splits')
                    ->whereIn('po_line_id', $lineIds->all())
                    ->pluck('id')
                    ->all();
            }
        }

        if (!empty($splitIds)) {
            $histSplit = DB::table('quota_histories')
                ->select('quota_id', DB::raw('SUM(quantity_change) as net_qty'))
                ->whereIn('change_type', [QuotaHistory::TYPE_FORECAST_DECREASE, QuotaHistory::TYPE_FORECAST_INCREASE])
                ->where('reference_type', PoLineVoyageSplit::class)
                ->whereIn('reference_id', $splitIds)
                ->groupBy('quota_id')
                ->get();

            foreach ($histSplit as $row) {
                $qid = (int) ($row->quota_id ?? 0);
                if ($qid > 0) {
                    $netByQuota[$qid] = ($netByQuota[$qid] ?? 0) + (int) $row->net_qty;
                }
            }
        }

        if (!empty($netByQuota)) {
            foreach ($netByQuota as $quotaId => $net) {
                if ((int) $net === 0) { continue; }
                $quota = Quota::lockForUpdate()->find($quotaId);
                if (!$quota) { continue; }

                $desc = 'PO replace rollback';
                if ($net > 0) {
                    $quota->decrementForecast((int) $net, $desc, $poModel, $occurredOn, $userId);
                } else {
                    $quota->incrementForecast((int) -$net, $desc, $poModel, $occurredOn, $userId);
                }
            }

            if ($poModel) {
                foreach ($netByQuota as $quotaId => $net) {
                    $pivot = DB::table('purchase_order_quota')
                        ->where('purchase_order_id', $poModel->id)
                        ->where('quota_id', $quotaId)
                        ->lockForUpdate()
                        ->first();

                    $newAlloc = ($pivot ? (int) $pivot->allocated_qty : 0) + (int) $net;
                    if ($newAlloc > 0) {
                        if ($pivot) {
                            DB::table('purchase_order_quota')->where('id', $pivot->id)->update([
                                'allocated_qty' => $newAlloc,
                                'updated_at' => now(),
                            ]);
                        } else {
                            DB::table('purchase_order_quota')->insert([
                                'purchase_order_id' => $poModel->id,
                                'quota_id' => $quotaId,
                                'allocated_qty' => $newAlloc,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } elseif ($pivot) {
                        DB::table('purchase_order_quota')->where('id', $pivot->id)->delete();
                    }
                }
            }
        }

        if (!empty($splitIds)) {
            DB::table('po_line_voyage_splits')->whereIn('id', $splitIds)->delete();
        }

        if ($header) {
            PoLine::where('po_header_id', $header->id)->delete();
        }
    }
}
