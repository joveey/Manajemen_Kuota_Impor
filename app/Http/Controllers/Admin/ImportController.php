<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\ImportItem;
use App\Models\MappingVersion;
use App\Support\PkCategoryParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportController extends Controller
{
    public function uploadHsPk(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'period_key' => ['required'],
        ]);

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return response()->json([
                'error' => 'Missing dependency phpoffice/phpspreadsheet. Install with: composer require phpoffice/phpspreadsheet',
            ], 500);
        }

        $uploaded = $request->file('file');
        $original = $uploaded->getClientOriginalName();
        $safeOriginal = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $original);
        $unique = now()->format('Ymd_His').'_'.Str::random(6).'_'.$safeOriginal;
        $storedPath = $uploaded->storeAs('imports', $unique);

        $import = Import::create([
            'type' => Import::TYPE_HS_PK,
            'period_key' => (string) $data['period_key'],
            'source_filename' => $original,
            'stored_path' => $storedPath,
            'status' => Import::STATUS_VALIDATING,
            'created_by' => Auth::id(),
        ]);

        // Build absolute path using the configured default disk ("local" may point to app/private)
        $fullPath = \Illuminate\Support\Facades\Storage::path($storedPath);

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (\Throwable $e) {
            Log::warning('Failed loading HS-PK import', ['error' => $e->getMessage()]);
            $import->markAs(Import::STATUS_FAILED, 'Failed to load workbook: '.$e->getMessage());
            return response()->json(['error' => 'Failed to load workbook'], 422);
        }

        /** @var Worksheet|null $sheet */
        $sheet = $spreadsheet->getSheetByName('HS code master');
        if (!$sheet) {
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                if (strcasecmp($ws->getTitle(), 'HS code master') === 0) {
                    $sheet = $ws; break;
                }
            }
        }
        if (!$sheet) {
            $import->markAs(Import::STATUS_FAILED, 'Sheet "HS code master" not found');
            return response()->json(['error' => 'Sheet "HS code master" not found'], 422);
        }

        $highestRow = (int) $sheet->getHighestRow();
        $highestColIndex = (int) Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            // PhpSpreadsheet 5.x: use getCell([$col,$row]) instead of removed getCellByColumnAndRow
            $val = (string) $sheet->getCell([$col, 1])->getValue();
            $key = strtoupper(trim($val));
            if ($key !== '') { $headers[$key] = $col; }
        }
        if (!isset($headers['HS_CODE']) || !isset($headers['DESC'])) {
            $import->markAs(Import::STATUS_FAILED, 'Columns HS_CODE or DESC not found');
            return response()->json(['error' => 'Required columns not found: HS_CODE, DESC'], 422);
        }

        $colHs = $headers['HS_CODE'];
        $colDesc = $headers['DESC'];

        $total = 0; $valid = 0; $error = 0;

        DB::beginTransaction();
        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                // Prefer formatted value to preserve numbers/text
                $hsRaw = $sheet->getCell([$colHs, $row])->getFormattedValue();
                $descRaw = $sheet->getCell([$colDesc, $row])->getFormattedValue();

                $hs = trim((string) $hsRaw);
                $desc = is_null($descRaw) ? '' : trim((string) $descRaw);

                // Skip trailing entirely blank rows
                if ($hs === '' && $desc === '') {
                    continue;
                }

                $total++;
                $raw = ['HS_CODE' => $hs, 'DESC' => $desc];
                $errors = [];

                if ($hs === '') {
                    $errors[] = 'HS_CODE required';
                }

                $parsed = PkCategoryParser::parse($desc);
                $min = $parsed['min_pk'];
                $max = $parsed['max_pk'];
                $minIncl = (bool)($parsed['min_incl'] ?? true);
                $maxIncl = (bool)($parsed['max_incl'] ?? true);

                $anchor = null;
                if ($min === null && $max === null) {
                    // Allow non-numeric HS codes such as 'ACC' (Accessories) by assigning anchor 0
                    if (preg_match('/[A-Za-z]/', $hs)) {
                        $anchor = 0.0;
                    } else {
                        $errors[] = 'DESC cannot be parsed';
                    }
                } else {
                    if ($min === null && $max !== null) {
                        $anchor = (float) $max - 0.01;
                    } elseif ($min !== null && $max !== null) {
                        $anchor = ((float) $min + (float) $max) / 2.0;
                    } elseif ($min !== null && $max === null) {
                        $anchor = (float) $min + 0.01;
                    }
                    if ($anchor !== null) { $anchor = round($anchor, 2); }
                }

                if (!empty($errors) || $anchor === null) {
                    ImportItem::create([
                        'import_id' => $import->id,
                        'row_index' => $row,
                        'raw_json' => $raw,
                        'errors_json' => $errors,
                        'status' => 'error',
                    ]);
                    $error++;
                    Log::warning('HS-PK import row invalid', ['row' => $row, 'errors' => $errors]);
                    continue;
                }

                ImportItem::create([
                    'import_id' => $import->id,
                    'row_index' => $row,
                    'raw_json' => $raw,
                    'normalized_json' => [
                        'min_pk' => $min,
                        'max_pk' => $max,
                        'is_min_inclusive' => $minIncl,
                        'is_max_inclusive' => $maxIncl,
                        'pk_anchor' => $anchor,
                    ],
                    'status' => 'normalized',
                ]);
                $valid++;
            }

            $import->fill([
                'total_rows' => $total,
                'valid_rows' => $valid,
                'error_rows' => $error,
            ]);
            $import->markAs(Import::STATUS_READY, sprintf('valid=%d, error=%d', $valid, $error));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $import->markAs(Import::STATUS_FAILED, 'Exception: '.$e->getMessage());
            Log::error('HS-PK upload failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Import failed'], 500);
        }

        return response()->json([
            'import_id' => $import->id,
            'period_key' => (string) $import->period_key,
            'total_rows' => (int) $import->total_rows,
            'valid_rows' => (int) $import->valid_rows,
            'error_rows' => (int) $import->error_rows,
            'status' => (string) $import->status,
        ]);
    }

    public function showSummary(\App\Models\Import $import)
    {
        return response()->json([
            'import_id'   => $import->id,
            'type'        => $import->type,
            'period_key'  => $import->period_key,
            'status'      => $import->status,
            'total_rows'  => (int)($import->total_rows ?? 0),
            'valid_rows'  => (int)($import->valid_rows ?? 0),
            'error_rows'  => (int)($import->error_rows ?? 0),
            'notes'       => $import->notes,
            'created_by'  => $import->created_by,
            'created_at'  => $import->created_at,
        ]);
    }

    public function listItems(\Illuminate\Http\Request $request, \App\Models\Import $import)
    {
        $status = $request->query('status');
        $perPage = (int) min(max((int)$request->query('per_page', 50), 1), 200);

        $q = $import->items()->select(['id','row_index','raw_json','normalized_json','errors_json','status'])
            ->orderBy('row_index');

        if ($status && in_array($status, ['normalized','error'], true)) {
            $q->where('status', $status);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'import_id' => $import->id,
            'status_filter' => $status,
            'per_page' => $perPage,
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'data' => $page->items(),
        ]);
    }

    public function uploadQuotas(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'period_key' => ['required'],
        ]);

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return response()->json([
                'error' => 'Missing dependency phpoffice/phpspreadsheet. Install with: composer require phpoffice/phpspreadsheet',
            ], 500);
        }

        $uploaded = $request->file('file');
        $original = $uploaded->getClientOriginalName();
        $safeOriginal = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $original);
        $unique = now()->format('Ymd_His').'_'.Str::random(6).'_'.$safeOriginal;
        $storedPath = $uploaded->storeAs('imports', $unique);

        $import = Import::create([
            'type' => Import::TYPE_QUOTA,
            'period_key' => (string) $data['period_key'],
            'source_filename' => $original,
            'stored_path' => $storedPath,
            'status' => Import::STATUS_VALIDATING,
            'created_by' => Auth::id(),
        ]);

        // Build absolute path using the configured default disk ("local" may point to app/private)
        $fullPath = \Illuminate\Support\Facades\Storage::path($storedPath);

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (\Throwable $e) {
            Log::warning('Failed loading Quota import', ['error' => $e->getMessage()]);
            $import->markAs(Import::STATUS_FAILED, 'Failed to load workbook: '.$e->getMessage());
            return response()->json(['error' => 'Failed to load workbook'], 422);
        }

        /** @var Worksheet|null $sheet */
        $sheet = $spreadsheet->getSheetByName('Quota master');
        if (!$sheet) {
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                if (strcasecmp($ws->getTitle(), 'Quota master') === 0) {
                    $sheet = $ws; break;
                }
            }
        }
        if (!$sheet) {
            $import->markAs(Import::STATUS_FAILED, 'Sheet "Quota master" not found');
            return response()->json(['error' => 'Sheet "Quota master" not found'], 422);
        }

        $highestRow = (int) $sheet->getHighestRow();
        $highestColIndex = (int) Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Header mapping
        $headers = [];
        $headerOrder = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = (string) $sheet->getCell([$col, 1])->getValue();
            $key = strtoupper(trim($val));
            if ($key !== '') { $headers[$key] = $col; $headerOrder[] = $key; }
        }
        // Basic required columns
        foreach (['LETTER_NO','CATEGORY_LABEL','ALLOCATION'] as $reqCol) {
            if (!isset($headers[$reqCol])) {
                $import->markAs(Import::STATUS_FAILED, 'Required columns not found: LETTER_NO, CATEGORY_LABEL, ALLOCATION');
                return response()->json(['error' => 'Required columns not found: LETTER_NO, CATEGORY_LABEL, ALLOCATION'], 422);
            }
        }

        $colLetter = $headers['LETTER_NO'];
        $colLabel = $headers['CATEGORY_LABEL'];
        $colAlloc = $headers['ALLOCATION'];
        $colStart = $headers['PERIOD_START'] ?? null;
        $colEnd = $headers['PERIOD_END'] ?? null;

        // Helper to parse date cell
        $parseDate = function ($value) {
            if ($value === null || $value === '') { return null; }
            // Excel serial date
            if (is_numeric($value)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject($value);
                    return Carbon::instance($dt)->toDateString();
                } catch (\Throwable $e) {
                    // fallthrough
                }
            }
            try {
                return Carbon::parse((string)$value)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        };

        // Derive period from period_key if needed
        $derivePeriod = function (string $key) {
            $key = trim($key);
            if (preg_match('/^\d{4}$/', $key)) {
                $y = (int)$key; return [sprintf('%04d-01-01',$y), sprintf('%04d-12-31',$y)];
            }
            if (preg_match('/^\d{4}-\d{2}$/', $key)) {
                [$y,$m] = explode('-', $key); $y=(int)$y; $m=(int)$m;
                $start = Carbon::create($y,$m,1);
                $end = $start->copy()->endOfMonth();
                return [$start->toDateString(), $end->toDateString()];
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                return [$key, $key];
            }
            return [null, null];
        };

        $total = 0; $valid = 0; $error = 0;
        $seen = [];

        DB::beginTransaction();
        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                // Build raw_json from all columns
                $raw = [];
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $h = $headerOrder[$col-1] ?? null;
                    if (!$h) { continue; }
                    $raw[$h] = $sheet->getCell([$col, $row])->getFormattedValue();
                }

                $letterNo = trim((string)($raw['LETTER_NO'] ?? ''));
                $label = trim((string)($raw['CATEGORY_LABEL'] ?? ''));
                $allocRaw = $raw['ALLOCATION'] ?? null;

                // If empty row
                if ($letterNo === '' && $label === '' && ($allocRaw === null || $allocRaw === '')) {
                    continue;
                }

                $total++;
                $errors = [];

                // Parse allocation
                $alloc = null;
                if ($allocRaw !== null && $allocRaw !== '') {
                    // remove thousand separators, commas
                    $num = preg_replace('/[^0-9-]/', '', (string)$allocRaw);
                    if ($num !== '' && is_numeric($num)) { $alloc = (int)$num; }
                }
                if (!is_int($alloc) || $alloc <= 0) {
                    $errors[] = 'ALLOCATION must be integer > 0';
                }

                // Parse category label
                $parsed = PkCategoryParser::parse($label);
                $min = $parsed['min_pk']; $max = $parsed['max_pk'];
                $minIncl = (bool)($parsed['min_incl'] ?? true);
                $maxIncl = (bool)($parsed['max_incl'] ?? true);
                if ($min === null && $max === null) {
                    $errors[] = 'CATEGORY_LABEL cannot be parsed';
                }

                // Periods
                $startVal = $colStart ? $sheet->getCell([$colStart, $row])->getValue() : null;
                $endVal = $colEnd ? $sheet->getCell([$colEnd, $row])->getValue() : null;
                $pStart = $parseDate($startVal);
                $pEnd = $parseDate($endVal);
                if (!$pStart || !$pEnd) {
                    [$dStart, $dEnd] = $derivePeriod((string)$data['period_key']);
                    $pStart = $pStart ?: $dStart;
                    $pEnd = $pEnd ?: $dEnd;
                }
                if (!$pStart || !$pEnd) {
                    $errors[] = 'PERIOD_START/END missing and cannot be derived';
                } elseif (Carbon::parse($pStart)->gt(Carbon::parse($pEnd))) {
                    $errors[] = 'PERIOD_START must be <= PERIOD_END';
                }

                // Soft-dup warning by label within this file
                $dupKey = strtoupper($label).'|'.$pStart.'|'.$pEnd;
                if (isset($seen[$dupKey])) {
                    Log::warning('Quota import duplicate label-period in file', ['row' => $row, 'label' => $label, 'period_start' => $pStart, 'period_end' => $pEnd]);
                } else {
                    $seen[$dupKey] = true;
                }

                if (!empty($errors)) {
                    ImportItem::create([
                        'import_id' => $import->id,
                        'row_index' => $row,
                        'raw_json' => $raw,
                        'errors_json' => $errors,
                        'status' => 'error',
                    ]);
                    $error++;
                    continue;
                }

                ImportItem::create([
                    'import_id' => $import->id,
                    'row_index' => $row,
                    'raw_json' => $raw,
                    'normalized_json' => [
                        'letter_no' => $letterNo,
                        'category_label' => $label,
                        'min_pk' => $min,
                        'max_pk' => $max,
                        'is_min_inclusive' => $minIncl,
                        'is_max_inclusive' => $maxIncl,
                        'allocation' => (int)$alloc,
                        'period_start' => $pStart,
                        'period_end' => $pEnd,
                    ],
                    'status' => 'normalized',
                ]);
                $valid++;
            }

            $import->fill([
                'total_rows' => $total,
                'valid_rows' => $valid,
                'error_rows' => $error,
            ]);
            $import->markAs(Import::STATUS_READY, sprintf('valid=%d, error=%d', $valid, $error));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $import->markAs(Import::STATUS_FAILED, 'Exception: '.$e->getMessage());
            Log::error('Quota upload failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Import failed'], 500);
        }

        return response()->json([
            'import_id' => $import->id,
            'type' => $import->type,
            'period_key' => (string) $import->period_key,
            'total_rows' => (int) $import->total_rows,
            'valid_rows' => (int) $import->valid_rows,
            'error_rows' => (int) $import->error_rows,
            'status' => (string) $import->status,
        ]);
    }

    public function publishQuotas(Request $request, Import $import)
    {
        if ($import->type !== Import::TYPE_QUOTA) {
            return response()->json(['error' => 'Unsupported import type'], 422);
        }
        if ($import->status !== Import::STATUS_READY) {
            return response()->json(['error' => 'Import is not ready for publishing'], 409);
        }

        $applied = 0; $skipped = 0;
        $ranAutomap = false; $automapSummary = null;

        DB::transaction(function () use ($import, &$applied, &$skipped) {
            ImportItem::query()
                ->where('import_id', $import->id)
                ->where('status', 'normalized')
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$applied, &$skipped) {
                    foreach ($rows as $item) {
                        $norm = $item->normalized_json ?? [];

                        $letterNo = trim((string)($norm['letter_no'] ?? ''));
                        $label = trim((string)($norm['category_label'] ?? ''));
                        $min = $norm['min_pk'] ?? null;
                        $max = $norm['max_pk'] ?? null;
                        $minIncl = (bool)($norm['is_min_inclusive'] ?? true);
                        $maxIncl = (bool)($norm['is_max_inclusive'] ?? true);
                        $alloc = $norm['allocation'] ?? null;
                        $pStart = $norm['period_start'] ?? null;
                        $pEnd = $norm['period_end'] ?? null;

                        if ($letterNo === '' || $label === '' || !is_numeric($alloc) || (int)$alloc <= 0 || !$pStart || !$pEnd) {
                            $skipped++;
                            continue;
                        }

                        // Find existing quota by unique key.
                        // TODO: If quotas has a dedicated 'letter_no' column, use it instead of 'source_document'.
                        $existing = DB::table('quotas')
                            ->where('source_document', $letterNo)
                            ->where('government_category', $label)
                            ->where('period_start', $pStart)
                            ->where('period_end', $pEnd)
                            ->first();

                        $now = now();
                        $updateFields = [
                            'government_category' => $label,
                            'total_allocation' => (int)$alloc,
                            'min_pk' => $min,
                            'max_pk' => $max,
                            'is_min_inclusive' => $minIncl,
                            'is_max_inclusive' => $maxIncl,
                            'updated_at' => $now,
                        ];

                        if ($existing) {
                            DB::table('quotas')->where('id', $existing->id)->update($updateFields);
                            $applied++;
                        } else {
                            // Insert new quota row.
                            // quotas table requires 'quota_number' and 'name' (not nullable).
                            // TODO: adjust columns if your project has different required fields.
                            $quotaNumber = 'IMP-'.(string)Str::upper(Str::random(10));
                            $name = 'Kuota '.$label.' '.$pStart.'-'.$pEnd;
                            DB::table('quotas')->insert([
                                'quota_number' => $quotaNumber,
                                'name' => $name,
                                'government_category' => $label,
                                'period_start' => $pStart,
                                'period_end' => $pEnd,
                                'total_allocation' => (int)$alloc,
                                'forecast_remaining' => (int)$alloc, // initialize only on insert
                                'actual_remaining' => (int)$alloc,   // initialize only on insert
                                'status' => 'available',
                                'is_active' => true,
                                'source_document' => $letterNo, // store letter_no here; see TODO above
                                'min_pk' => $min,
                                'max_pk' => $max,
                                'is_min_inclusive' => $minIncl,
                                'is_max_inclusive' => $maxIncl,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                            $applied++;
                        }
                    }
                });

            // Versioning
            $currentMax = (int) MappingVersion::query()
                ->where('type', 'quota')
                ->where('period_key', (string)$import->period_key)
                ->max('version');
            $nextVersion = $currentMax + 1;
            MappingVersion::create([
                'type' => 'quota',
                'period_key' => (string)$import->period_key,
                'version' => $nextVersion,
                'notes' => json_encode(['applied' => $applied, 'skipped' => $skipped, 'import_id' => $import->id]),
            ]);

            $import->markAs(Import::STATUS_PUBLISHED, sprintf('applied=%d, skipped=%d', $applied, $skipped));
        });

        $ranAutomap = false; $automapSummary = null;
        if ($request->boolean('run_automap') && !empty($import->period_key)) {
            $automapSummary = app(\App\Services\ProductQuotaAutoMapper::class)->runForPeriod($import->period_key);
            $ranAutomap = true;
        }

        $version = (int) \App\Models\MappingVersion::where('type','quota')->where('period_key',$import->period_key)->max('version');

        $resp = [
            'import_id' => $import->id,
            'type' => $import->type,
            'period_key' => (string)$import->period_key,
            'applied' => $applied,
            'skipped' => $skipped,
            'version' => $version,
            'ran_automap' => $ranAutomap,
        ];
        if ($ranAutomap) {
            $resp['automap_summary'] = $automapSummary;
        }
        return response()->json($resp, 201);
    }

    public function publish(Request $request, Import $import)
    {
        if ($import->type !== Import::TYPE_HS_PK) {
            return response()->json(['error' => 'Unsupported import type'], 422);
        }
        if ($import->status !== Import::STATUS_READY) {
            return response()->json(['error' => 'Import is not ready for publishing'], 409);
        }

        $applied = 0; $skipped = 0;
        $skippedExisting = 0; $duplicatesList = [];
        $ranAutomap = false; $automapSummary = null;
        $now = now();

        DB::transaction(function () use ($import, &$applied, &$skipped, &$skippedExisting, &$duplicatesList, $now) {
            // Process items in chunks for large files
            ImportItem::query()
                ->where('import_id', $import->id)
                ->where('status', 'normalized')
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$applied, &$skipped, &$skippedExisting, &$duplicatesList, $now) {
                    $incoming = [];
                    foreach ($rows as $item) {
                        $raw = $item->raw_json ?? [];
                        $norm = $item->normalized_json ?? [];
                        $hs = trim((string)($raw['HS_CODE'] ?? ''));
                        $anchor = $norm['pk_anchor'] ?? null;

                        if ($hs === '') { $skipped++; continue; }
                        if ($anchor === null || !is_numeric($anchor)) {
                            if (preg_match('/[A-Za-z]/', $hs)) { $anchor = 0.0; }
                            else { $skipped++; continue; }
                        }
                        $incoming[$hs] = round((float)$anchor, 2);
                    }

                    if (empty($incoming)) { return; }

                    $codes = array_keys($incoming);
                    $existing = DB::table('hs_code_pk_mappings')->whereIn('hs_code', $codes)->pluck('hs_code')->all();
                    $existSet = array_flip(array_map('strval', $existing));

                    $toInsert = [];
                    foreach ($incoming as $code => $anchor) {
                        if (isset($existSet[$code])) {
                            $skippedExisting++;
                            $duplicatesList[$code] = true;
                            continue;
                        }
                        $toInsert[] = [
                            'hs_code' => $code,
                            'pk_capacity' => $anchor,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($toInsert)) {
                        DB::table('hs_code_pk_mappings')->insert($toInsert);
                        $applied += count($toInsert);
                    }
                });

            // Create mapping version
            $currentMax = (int) MappingVersion::query()
                ->where('type', 'hs_pk')
                ->where('period_key', (string)$import->period_key)
                ->max('version');
            $nextVersion = $currentMax + 1;
            MappingVersion::create([
                'type' => 'hs_pk',
                'period_key' => (string)$import->period_key,
                'version' => $nextVersion,
                'notes' => json_encode([
                    'applied' => $applied,
                    'skipped' => $skipped,
                    'skipped_existing' => $skippedExisting,
                    'import_id' => $import->id,
                ]),
            ]);

            $import->markAs(Import::STATUS_PUBLISHED, sprintf('applied=%d, skipped=%d', $applied, $skipped));
        });

        if ($request->boolean('run_automap') && !empty($import->period_key)) {
            $automapSummary = app(\App\Services\ProductQuotaAutoMapper::class)->runForPeriod($import->period_key);
            $ranAutomap = true;
        }

        $response = [
            'import_id' => $import->id,
            'type' => $import->type,
            'period_key' => (string) $import->period_key,
            'applied' => $applied,
            'skipped' => $skipped,
            'skipped_existing' => $skippedExisting,
            'duplicates' => array_keys($duplicatesList),
            'version' => (int) ((int) (\App\Models\MappingVersion::where('type','hs_pk')->where('period_key',$import->period_key)->max('version'))),
            'ran_automap' => $ranAutomap,
        ];
        if ($ranAutomap) {
            $response['automap_summary'] = $automapSummary;
        }

        return response()->json($response, 201);
    }
}
