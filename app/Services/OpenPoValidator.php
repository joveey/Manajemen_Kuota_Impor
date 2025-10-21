<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class OpenPoValidator
{
    /**
     * Validate rows and group by PO_NUMBER.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{error_count:int, groups:array<string,array<string,mixed>>}
     */
    public function validate(array $rows, array $modelMap = []): array
    {
        $groups = [];
        $errorCount = 0;

        // build hs master checker (prefer hs_codes if exists, otherwise hs_code_pk_mappings)
        $hsTable = DB::getSchemaBuilder()->hasTable('hs_codes') ? 'hs_codes' : (DB::getSchemaBuilder()->hasTable('hs_code_pk_mappings') ? 'hs_code_pk_mappings' : null);

        $poSeen = [];

        foreach ($rows as $r) {
            $rowNum = (int) ($r['ROW'] ?? 0);
            // normalize fields
            $po = trim((string) ($r['PO_DOC'] ?? ''));
            $poDate = trim((string) ($r['CREATED_DATE'] ?? ''));
            $supplier = trim((string) ($r['VENDOR_NAME'] ?? ''));
            $vendorNo = trim((string) ($r['VENDOR_NO'] ?? ''));
            $lineNo = trim((string) ($r['LINE_NO'] ?? ''));
            $model = trim((string) ($r['ITEM_CODE'] ?? ''));
            $itemDesc = trim((string) ($r['ITEM_DESC'] ?? ''));
            $qtyRaw = $r['QTY'] ?? null;
            $eta = trim((string) ($r['ETA_DATE'] ?? '')); // source doesn't have ETA, keep for future
            $uom = trim((string) ($r['UOM'] ?? '')) ?: null;
            $currency = trim((string) ($r['CURRENCY'] ?? '')) ?: null;
            $note = null; // could embed other fields if needed
            $hsInput = trim((string) ($r['HS_CODE'] ?? ''));

            $errors = [];

            if ($po === '') { $errors[] = 'Kolom PO_DOC kosong'; }
            if ($supplier === '') { $errors[] = 'Kolom VENDOR_NAME kosong'; }
            if ($model === '') { $errors[] = 'Kolom ITEM_CODE kosong'; }

            // date parse
            $poDateNorm = null; $etaNorm = null;
            try { $poDateNorm = Carbon::parse($poDate)->toDateString(); } catch (\Throwable $e) { $errors[] = 'CREATED_DATE tidak valid (gunakan YYYY-MM-DD)'; }
            if ($eta !== '') {
                try { $etaNorm = Carbon::parse($eta)->toDateString(); } catch (\Throwable $e) { $errors[] = 'ETA_DATE tidak valid (gunakan YYYY-MM-DD)'; }
            }

            // qty parse
            $qty = null;
            if ($qtyRaw !== null && $qtyRaw !== '') {
                $num = preg_replace('/[^0-9.\-]/', '', (string)$qtyRaw);
                if ($num !== '' && is_numeric($num)) { $qty = (float)$num; }
            }
            if (!is_numeric($qty) || $qty <= 0) { $errors[] = 'QTY tidak valid (> 0)'; }

            // HS resolve/validate
            $hsCode = null; $expectedHs = null;
            if ($hsInput !== '') {
                $hsCode = $hsInput;
                if ($hsTable) {
                    $exists = DB::table($hsTable)->when($hsTable === 'hs_codes', function($q){ $q->select('id'); }, function($q){ $q->select('id'); })
                        ->where(($hsTable === 'hs_codes') ? 'code' : 'hs_code', $hsCode)->exists();
                    if (!$exists) { $errors[] = 'HS_CODE tidak ada di HS master'; }
                }
                // If there is a model->HS mapping table (not present in this repo), you could check consistency here.
            } else {
                // try resolve from uploaded mapping sheet first
                $mmKey = strtoupper($model);
                if (!empty($modelMap[$mmKey])) {
                    $hsCode = (string) $modelMap[$mmKey];
                } else {
                    // fallback: product.hs_code if exists
                    $product = DB::table('products')->where(function($q) use ($model){ $q->where('sap_model', $model)->orWhere('code', $model); })->first();
                    if ($product && isset($product->hs_code) && $product->hs_code) {
                        $hsCode = (string) $product->hs_code;
                    } else {
                        $errors[] = 'MODEL_CODE belum punya HS mapping';
                    }
                }
            }

            $status = empty($errors) ? 'ok' : 'error';
            if ($status === 'error') { $errorCount++; }

            // init group
            if (!isset($groups[$po])) {
                // detect duplicates in file
                if (isset($poSeen[$po])) {
                    $errorCount++;
                }
                $poSeen[$po] = true;
                $groups[$po] = [
                    'po_date' => $poDateNorm,
                    'supplier' => trim($vendorNo !== '' ? ($vendorNo.' - '.$supplier) : $supplier),
                    'currency' => $currency,
                    'note' => $note,
                    'lines' => [],
                ];
            }

            $groups[$po]['lines'][] = [
                'row' => $rowNum,
                'line_no' => $lineNo,
                'model_code' => $model,
                'item_desc' => $itemDesc,
                'hs_code' => $hsCode,
                'qty_ordered' => $qty,
                'uom' => $uom,
                'eta_date' => $etaNorm,
                'validation_status' => $status,
                'validation_notes' => implode('; ', $errors),
            ];
        }

        return ['error_count' => $errorCount, 'groups' => $groups];
    }
}
