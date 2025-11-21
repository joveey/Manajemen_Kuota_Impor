<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Models\ImportItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InvoiceImportService
{
    /**
     * Handle invoice upload end-to-end: store file, create Import record,
     * parse workbook and persist normalized rows into import_items.
     */
    public function handle(UploadedFile $file): Import
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('Missing dependency phpoffice/phpspreadsheet. Install with: composer require phpoffice/phpspreadsheet');
        }

        $original    = $file->getClientOriginalName();
        $safeOriginal = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $original);
        $unique       = now()->format('Ymd_His') . '_' . Str::random(6) . '_' . $safeOriginal;
        $storedPath   = $file->storeAs('imports', $unique);

        $import = Import::create([
            'type'            => Import::TYPE_INVOICE,
            'period_key'      => '',
            'source_filename' => $original,
            'stored_path'     => $storedPath,
            'status'          => Import::STATUS_VALIDATING,
            'created_by'      => Auth::id(),
        ]);

        $fullPath = Storage::path($storedPath);

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (\Throwable $e) {
            $import->markAs(Import::STATUS_FAILED, 'Failed to load workbook: ' . $e->getMessage());
            throw new \RuntimeException('Failed to load workbook', 0, $e);
        }

        $sheet          = $spreadsheet->getSheet(0);
        $highestRow     = (int) $sheet->getHighestRow();
        $highestColIndex = (int) \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = (string) $sheet->getCell([$col, 1])->getValue();
            $key = strtoupper(trim($val));
            if ($key !== '') {
                $headers[$key] = $col;
            }
        }

        foreach (['PO_NO', 'LINE_NO', 'INVOICE_NO', 'INVOICE_DATE', 'QTY'] as $req) {
            if (!isset($headers[$req])) {
                $import->markAs(Import::STATUS_FAILED, 'Required columns not found: PO_NO, LINE_NO, INVOICE_NO, INVOICE_DATE, QTY');
                throw new \RuntimeException('Required columns not found: PO_NO, LINE_NO, INVOICE_NO, INVOICE_DATE, QTY');
            }
        }

        $colP = $headers['PO_NO'];
        $colL = $headers['LINE_NO'];
        $colI = $headers['INVOICE_NO'];
        $colD = $headers['INVOICE_DATE'];
        $colQ = $headers['QTY'];

        $total  = 0;
        $valid  = 0;
        $error  = 0;

        DB::beginTransaction();
        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                $po      = trim((string) $sheet->getCell([$colP, $row])->getFormattedValue());
                $ln      = trim((string) $sheet->getCell([$colL, $row])->getFormattedValue());
                $inv     = trim((string) $sheet->getCell([$colI, $row])->getFormattedValue());
                $dateRaw = $sheet->getCell([$colD, $row])->getValue();
                $qtyRaw  = $sheet->getCell([$colQ, $row])->getValue();

                if ($po === '' && $ln === '' && $inv === '') {
                    continue;
                }
                $total++;

                $date = null;
                if (is_numeric($dateRaw)) {
                    try {
                        $date = \Illuminate\Support\Carbon::instance(ExcelDate::excelToDateTimeObject($dateRaw))->toDateString();
                    } catch (\Throwable) {
                        $date = null;
                    }
                } else {
                    try {
                        $date = \Illuminate\Support\Carbon::parse((string) $dateRaw)->toDateString();
                    } catch (\Throwable) {
                        $date = null;
                    }
                }

                $qty    = (float) $qtyRaw;
                $errors = [];
                if ($po === '') {
                    $errors[] = 'PO_NO required';
                }
                if ($ln === '') {
                    $errors[] = 'LINE_NO required';
                }
                if ($inv === '') {
                    $errors[] = 'INVOICE_NO required';
                }
                if ($date === null) {
                    $errors[] = 'INVOICE_DATE invalid';
                }
                if ($qty <= 0) {
                    $errors[] = 'QTY must be > 0';
                }

                $status = empty($errors) ? Import::STATUS_READY : Import::STATUS_FAILED;
                if ($status === Import::STATUS_READY) {
                    $valid++;
                } else {
                    $error++;
                }

                ImportItem::create([
                    'import_id'       => $import->id,
                    'row_index'       => $row,
                    'raw_json'        => [
                        'PO_NO'        => $po,
                        'LINE_NO'      => $ln,
                        'INVOICE_NO'   => $inv,
                        'INVOICE_DATE' => $dateRaw,
                        'QTY'          => $qtyRaw,
                    ],
                    'normalized_json' => [
                        'po_no'        => $po,
                        'line_no'      => $ln,
                        'invoice_no'   => $inv,
                        'invoice_date' => $date,
                        'qty'          => $qty,
                    ],
                    'errors_json'     => $errors,
                    'status'          => $status === Import::STATUS_READY ? 'normalized' : 'error',
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $import->markAs(Import::STATUS_FAILED, 'Exception: ' . $e->getMessage());
            throw $e;
        }

        $import->update([
            'status'      => $error > 0 ? Import::STATUS_FAILED : Import::STATUS_READY,
            'total_rows'  => $total,
            'valid_rows'  => $valid,
            'error_rows'  => $error,
        ]);

        return $import->fresh();
    }
}

