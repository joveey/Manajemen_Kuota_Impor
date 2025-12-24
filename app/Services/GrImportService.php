<?php

namespace App\Services;

use App\Models\GrReceipt;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GrImportService
{
    private const ERROR_LIMIT = 20;

    private array $requiredColumns = [
        'PO_NO' => ['PO_NO','PO NO','PO','PO DOC','PODOC','PO NUMBER','PONUMBER'],
        'LINE_NO' => ['LINE_NO','LINE NO','LINE','ITEM','PO_ITEM','PO ITEM','POITEM'],
        'RECEIVE_DATE' => ['RECEIVE_DATE','RECEIVE DATE','RECEIPT DATE','GR DATE','POSTING DATE','DATE'],
        'QTY' => ['QTY','QUANTITY','RECEIVE QTY','QTY RECEIVED','GR QTY'],
        'CAT_PO' => ['CAT_PO','CAT PO','CATPO','CATEGORY'],
    ];

    private array $optionalColumns = [
        'INVOICE_NO' => ['INVOICE_NO','INVOICE NO','INVOICE','INV','INV NO'],
        'ITEM_NAME' => ['ITEM_NAME','ITEM NAME','ITEM','DESC','DESCRIPTION'],
        'VENDOR_CODE' => ['VENDOR_CODE','VENDOR CODE','VENDOR NO','VENDOR'],
        'VENDOR_NAME' => ['VENDOR_NAME','VENDOR NAME'],
        'WH_CODE' => ['WH_CODE','WH CODE','WAREHOUSE CODE'],
        'WH_NAME' => ['WH_NAME','WH NAME','WAREHOUSE NAME'],
        'SLOC_CODE' => ['SLOC_CODE','SLOC CODE','SUBINV CODE'],
        'SLOC_NAME' => ['SLOC_NAME','SLOC NAME','SUBINV NAME'],
        'CURRENCY' => ['CURRENCY','CURR'],
        'AMOUNT' => ['AMOUNT','AMT'],
        'DELIV_AMOUNT' => ['DELIV_AMOUNT','DELIVERY AMOUNT','DELIV AMOUNT'],
    ];

    private array $preferredSheets = ['PO GR','PO_GR','PO GR (GOOD RECEIPT)','GOOD RECEIPT','GR','PO GOOD RECEIPT'];

    public function import(UploadedFile $file): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('Missing dependency phpoffice/phpspreadsheet.');
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $this->locateSheet($spreadsheet);
        if (!$sheet) {
            $titles = [];
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                $titles[] = $ws->getTitle();
            }
            throw new \RuntimeException('Sheet PO GR tidak ditemukan. Sheets: '.implode(', ', $titles));
        }

        [$reqMap, $optMap] = $this->detectColumns($sheet);
        foreach (['PO_NO','LINE_NO','RECEIVE_DATE','QTY','CAT_PO'] as $key) {
            if (empty($reqMap[$key])) {
                throw new \RuntimeException('Kolom wajib tidak ditemukan (PO_NO, LINE_NO, RECEIVE_DATE, QTY, CAT_PO).');
            }
        }

        $summary = [
            'total_rows' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $normalized = [];
        $fileUniques = [];
        $highestRow = (int) $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $rawPo = trim((string) $sheet->getCell([$reqMap['PO_NO'], $row])->getFormattedValue());
            $rawLine = trim((string) $sheet->getCell([$reqMap['LINE_NO'], $row])->getFormattedValue());
            if ($rawPo === '' && $rawLine === '') {
                continue;
            }

            $summary['total_rows']++;
            $dateValue = $sheet->getCell([$reqMap['RECEIVE_DATE'], $row])->getValue();
            $qtyValue = $sheet->getCell([$reqMap['QTY'], $row])->getValue();
            $catValue = trim((string) $sheet->getCell([$reqMap['CAT_PO'], $row])->getFormattedValue());
            $invoice = $optMap['INVOICE_NO'] ? trim((string) $sheet->getCell([$optMap['INVOICE_NO'], $row])->getFormattedValue()) : null;

            $date = $this->parseDate($dateValue);
            $qty = is_numeric($qtyValue) ? (float) $qtyValue : (float) str_replace([','], '', (string) $qtyValue);
            $lineNorm = $this->normalizeLineNumber($rawLine);

            $rowErrors = [];
            if ($rawPo === '') { $rowErrors[] = 'PO_DOC wajib diisi'; }
            if ($lineNorm === '') { $rowErrors[] = 'LINE_NO wajib diisi'; }
            if (!$date) { $rowErrors[] = 'RECEIVE_DATE tidak valid'; }
            if ($qty <= 0) { $rowErrors[] = 'QTY harus > 0'; }
            if ($catValue === '') { $rowErrors[] = 'CAT_PO wajib diisi'; }

            $extras = [];
            foreach ($optMap as $key => $colIndex) {
                if ($key === 'INVOICE_NO') { continue; }
                $extras[$key] = $colIndex ? trim((string) $sheet->getCell([$colIndex, $row])->getFormattedValue()) : null;
            }

            $unique = $this->buildSignature($rawPo, $lineNorm, $date, $invoice, $row);
            if (isset($fileUniques[$unique])) {
                $rowErrors[] = 'Duplikat GR (PO/Line/Date/Invoice)';
            }

            if ($rowErrors) {
                $summary['skipped']++;
                $this->pushError($summary['errors'], $row, implode('; ', $rowErrors));
                continue;
            }

            $fileUniques[$unique] = true;
            $normalized[] = [
                'row' => $row,
                'po' => $rawPo,
                'line' => $lineNorm,
                'date' => $date,
                'qty' => $qty,
                'cat' => $catValue,
                'invoice' => $invoice,
                'unique' => $unique,
                'extras' => $extras,
            ];
        }

        DB::transaction(function () use (&$summary, $normalized) {
            $chunks = array_chunk($normalized, 300);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $entry) {
                    if ($this->receiptExists($entry['unique'])) {
                        $summary['skipped']++;
                        continue;
                    }

                    $poModel = $this->locatePurchaseOrder($entry['po'], $entry['line']);
                    if (!$poModel) {
                        $summary['skipped']++;
                        $this->pushError($summary['errors'], $entry['row'], 'PO / Line tidak ditemukan di sistem');
                        continue;
                    }

                    $product = $this->resolveProductForPurchaseOrder($poModel);
                    if (!$product) {
                        $summary['skipped']++;
                        $this->pushError($summary['errors'], $entry['row'], 'Produk tidak ditemukan di purchase_orders');
                        continue;
                    }

                    $quota = $this->resolveQuotaForProduct($product, $entry['date']);
                    if (!$quota) {
                        $summary['skipped']++;
                        $this->pushError($summary['errors'], $entry['row'], 'Quota aktif yang cocok tidak ditemukan');
                        continue;
                    }

                    $gr = GrReceipt::create([
                        'po_no' => $entry['po'],
                        'line_no' => $entry['line'],
                        'invoice_no' => $entry['invoice'],
                        'receive_date' => $entry['date'],
                        'qty' => $entry['qty'],
                        'gr_unique' => $entry['unique'],
                        'cat_po' => $entry['cat'],
                        'item_name' => $entry['extras']['ITEM_NAME'] ?? null,
                        'vendor_code' => $entry['extras']['VENDOR_CODE'] ?? null,
                        'vendor_name' => $entry['extras']['VENDOR_NAME'] ?? null,
                        'wh_code' => $entry['extras']['WH_CODE'] ?? null,
                        'wh_name' => $entry['extras']['WH_NAME'] ?? null,
                        'sloc_code' => $entry['extras']['SLOC_CODE'] ?? null,
                        'sloc_name' => $entry['extras']['SLOC_NAME'] ?? null,
                        'currency' => $entry['extras']['CURRENCY'] ?? null,
                        'amount' => $entry['extras']['AMOUNT'] ?? null,
                        'deliv_amount' => $entry['extras']['DELIV_AMOUNT'] ?? null,
                    ]);

                    $poModel->quantity_received = (float) ($poModel->quantity_received ?? 0) + (float) $entry['qty'];
                    if ($poModel->quantity_received >= $poModel->qty) {
                        $poModel->status = PurchaseOrder::STATUS_COMPLETED;
                        $poModel->actual_completed_at = now();
                    }
                    $poModel->save();

                    $quota->decrementActual(
                        (int) round($entry['qty']),
                        sprintf('GR import %s/%s pada %s', $entry['po'], $entry['line'], $entry['date']),
                        $gr,
                        new \DateTimeImmutable($entry['date']),
                        Auth::id(),
                        [
                            'gr_unique' => $entry['unique'],
                            'po_no' => $entry['po'],
                            'line_no' => $entry['line'],
                        ]
                    );

                    $summary['inserted']++;
                }
            }
        });

        return $summary;
    }

    private function locateSheet($spreadsheet): ?Worksheet
    {
        $normalize = fn ($value) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $titleNorm = $normalize($ws->getTitle());
            foreach ($this->preferredSheets as $preferred) {
                if ($titleNorm === $normalize($preferred)) {
                    return $ws;
                }
            }
        }
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            return $ws; // fallback to first sheet
        }

        return null;
    }

    private function detectColumns(Worksheet $sheet): array
    {
        $normalize = fn ($value) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
        $highestCol = (int) Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $map = [];
        for ($col = 1; $col <= $highestCol; $col++) {
            $header = $normalize($sheet->getCell([$col, 1])->getValue());
            if ($header !== '') {
                $map[$header] = $col;
            }
        }

        $resolve = function (array $list) use ($map, $normalize) {
            foreach ($list as $candidate) {
                $norm = $normalize($candidate);
                if (isset($map[$norm])) {
                    return $map[$norm];
                }
            }
            return null;
        };

        $req = [];
        foreach ($this->requiredColumns as $key => $synonyms) {
            $req[$key] = $resolve($synonyms);
        }
        $opt = [];
        foreach ($this->optionalColumns as $key => $synonyms) {
            $opt[$key] = $resolve($synonyms);
        }

        return [$req, $opt];
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeLineNumber(?string $value): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '';
        }

        if (is_numeric($trimmed)) {
            return (string) ((int) $trimmed);
        }

        return $trimmed;
    }

    private function pushError(array &$errors, int $row, string $message): void
    {
        if (count($errors) >= self::ERROR_LIMIT) {
            return;
        }
        $errors[] = ['row' => $row, 'message' => $message];
    }

    private function receiptExists(string $signature): bool
    {
        return GrReceipt::query()
            ->where('gr_unique', $signature)
            ->exists();
    }

    private function locatePurchaseOrder(string $poDoc, string $lineNumber): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->where('po_doc', $poDoc)
            ->where('line_no', $lineNumber)
            ->lockForUpdate()
            ->first();
    }

    private function resolveProductForPurchaseOrder(PurchaseOrder $po): ?Product
    {
        if (!$po->product_id) {
            return null;
        }

        $product = $po->relationLoaded('product')
            ? $po->product
            : Product::find($po->product_id);

        if (!$product) {
            return null;
        }

        return ($product->pk_capacity !== null && !empty($product->hs_code)) ? $product : null;
    }

    private function resolveQuotaForProduct(?Product $product, string $date): ?Quota
    {
        if (!$product) {
            return null;
        }

        return Quota::query()
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('period_start')->orWhere('period_start', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('period_end')->orWhere('period_end', '>=', $date);
            })
            ->get()
            ->first(function (Quota $quota) use ($product) {
                return $quota->matchesProduct($product);
            });
    }

    /**
     * Build a deterministic signature per GR row to enforce idempotency.
     * Priority: invoice number → SAP GR doc → row index fallback.
     */
    private function buildSignature(string $po, string $line, ?string $date, ?string $invoice, int $rowIndex = 0): string
    {
        $base = $po.'|'.$line.'|'.($date ?? '');

        if (!empty($invoice)) {
            return sha1($base.'|'.$invoice);
        }

        return sha1($base.'|row:'.$rowIndex);
    }
}
