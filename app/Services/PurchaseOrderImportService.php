<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\QuotaAllocationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PurchaseOrderImportService
{
    private const REQUIRED_COLUMNS = [
        'po_doc',
        'created_date',
        'line_no',
        'item_code',
        'qty',
    ];

    /**
     * Cache for product lookups keyed by lowercase item_code.
     *
     * @var array<string,int>
     */
    private array $productCache = [];

    public function import(UploadedFile $file): array
    {
        $rows = $this->loadSheetRows($file, 'List PO');
        if ($rows->isEmpty()) {
            throw new \RuntimeException('Sheet "List PO" tidak memiliki data.');
        }

        $firstRow = $rows->first();
        $availableColumns = $firstRow ? array_keys($firstRow) : [];
        $missingColumns = array_diff(self::REQUIRED_COLUMNS, $availableColumns);
        if (!empty($missingColumns)) {
            throw new \RuntimeException('Kolom wajib belum lengkap: '.implode(', ', $missingColumns));
        }

        $allocationService = app(QuotaAllocationService::class);

        $result = [
            'total_rows' => $rows->count(),
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            [$data, $errors] = $this->normalizeRow($row);

            if (empty($errors)) {
                try {
                    $data['product_id'] = $this->resolveProductId($data['item_code'], $data['item_desc']);
                } catch (\Throwable $e) {
                    $errors[] = 'Gagal menentukan product_id: '.$e->getMessage();
                }
            }

            if (!empty($errors)) {
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => implode('; ', $errors),
                ];
                continue;
            }

            try {
                DB::transaction(function () use (&$result, $data, $allocationService) {
                    $existing = PurchaseOrder::query()
                        ->where('po_doc', $data['po_doc'])
                        ->where('line_no', $data['line_no'])
                        ->lockForUpdate()
                        ->first();

                    $payload = $this->buildPayload($data);
                    $poModel = null;

                    if ($existing) {
                        $existing->fill($payload['update'])->save();
                        $poModel = $existing->fresh();
                        $result['updated']++;
                    } else {
                        $poModel = PurchaseOrder::create($payload['insert']);
                        $result['inserted']++;
                    }

                    if ($poModel) {
                        $this->allocateForecastIfNeeded($poModel, $data, $allocationService);
                    }
                });
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function loadSheetRows(UploadedFile $file, string $sheetName): Collection
    {
        $sheetCollector = new class implements
            \Maatwebsite\Excel\Concerns\ToCollection,
            \Maatwebsite\Excel\Concerns\WithHeadingRow,
            \Maatwebsite\Excel\Concerns\SkipsEmptyRows,
            \Maatwebsite\Excel\Concerns\WithCalculatedFormulas {
            public ?Collection $rows = null;

            public function collection(Collection $collection): void
            {
                $this->rows = $collection;
            }
        };

        $import = new class($sheetName, $sheetCollector) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
            public function __construct(
                private string $targetSheet,
                private $delegate
            ) {
            }

            public function sheets(): array
            {
                return [
                    $this->targetSheet => $this->delegate,
                ];
            }
        };

        Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::XLSX);

        if (!$sheetCollector->rows instanceof Collection) {
            throw new \RuntimeException('Sheet "'.$sheetName.'" tidak ditemukan di file.');
        }

        return $sheetCollector->rows->map(function ($row) {
            if ($row instanceof Collection) {
                return $row->toArray();
            }
            return (array) $row;
        })->map(function (array $row) {
            return array_change_key_case($row, CASE_LOWER);
        });
    }

    private function normalizeRow(array $row): array
    {
        $errors = [];

        $poDoc = $this->normalizePoDoc($row['po_doc'] ?? null);
        if ($poDoc === null) {
            $errors[] = 'PO_DOC wajib diisi';
        }

        $lineNo = $this->toInt($row['line_no'] ?? null);
        if ($lineNo === null) {
            $errors[] = 'LINE_NO wajib diisi (numerik)';
        }

        $createdDate = $this->parseDate($row['created_date'] ?? null);
        if (!$createdDate) {
            $errors[] = 'CREATED_DATE tidak valid';
        }

        $itemCode = $this->trimValue($row['item_code'] ?? null);
        if ($itemCode === null) {
            $errors[] = 'ITEM_CODE wajib diisi';
        }

        $qty = $this->toDecimal($row['qty'] ?? null);
        if ($qty === null) {
            $errors[] = 'QTY wajib diisi';
        }

        $amount = $this->toDecimal($row['amount'] ?? null);

        $data = [
            'po_doc' => $poDoc,
            'line_no' => $lineNo,
            'created_date' => $createdDate?->toDateString(),
            'period' => $createdDate?->format('Y-m'),
            'sap_reference' => $poDoc,
            'vendor_no' => $this->trimValue($row['vendor_no'] ?? null),
            'vendor_name' => $this->trimValue($row['vendor_name'] ?? null),
            'item_code' => $itemCode,
            'item_desc' => $this->trimValue($row['item_desc'] ?? null),
            'wh_code' => $this->trimValue($row['wh_code'] ?? null),
            'wh_name' => $this->trimValue($row['wh_name'] ?? null),
            'wh_source' => $this->trimValue($row['wh_source'] ?? null),
            'subinv_code' => $this->trimValue($row['subinv_code'] ?? null),
            'subinv_name' => $this->trimValue($row['subinv_name'] ?? null),
            'subinv_source' => $this->trimValue($row['subinv_source'] ?? null),
            'qty' => $qty,
            'amount' => $amount,
            'cat_po' => $this->trimValue($row['cat_po'] ?? null),
            'cat_desc' => $this->trimValue($row['cat_desc'] ?? null),
            'mat_grp' => $this->trimValue($row['mat_grp'] ?? null),
        ];

        return [$data, $errors];
    }

    private function buildPayload(array $data): array
    {
        $defaults = [
            'plant_name' => 'N/A',
            'plant_detail' => 'N/A',
            'status' => 'ordered',
            'status_po_display' => 'Ordered',
            'quantity_shipped' => 0,
            'quantity_received' => 0,
            'voyage_bl' => null,
            'voyage_etd' => null,
            'voyage_eta' => null,
            'voyage_factory' => null,
            'voyage_status' => null,
            'voyage_issue_date' => null,
            'voyage_expired_date' => null,
            'voyage_remark' => null,
        ];

        $insert = array_merge($defaults, $data);

        return [
            'insert' => $insert,
            'update' => $data,
        ];
    }

    private function normalizePoDoc($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = is_string($value) ? trim($value) : $value;

        if ($string === '' || $string === null) {
            return null;
        }

        if (is_string($string)) {
            $normalized = trim($string);
            if ($normalized === '') {
                return null;
            }
            if (stripos($normalized, 'e') !== false && is_numeric($normalized)) {
                return sprintf('%.0f', (float) $normalized);
            }
            if (preg_match('/^\d+(\.0+)?$/', $normalized)) {
                return preg_replace('/\.0+$/', '', $normalized);
            }
            return $normalized;
        }

        if (is_numeric($string)) {
            return sprintf('%.0f', (float) $string);
        }

        if ($string === '') {
            return null;
        }
        return (string) $string;
    }

    private function trimValue($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function toInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return $filtered === false ? null : (int) $filtered;
    }

    private function toDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        if (is_numeric($value)) {
            try {
                $date = ExcelDate::excelToDateTimeObject((float) $value);
                return Carbon::instance($date);
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveProductId(string $itemCode, ?string $itemDesc): int
    {
        $normalized = mb_strtolower($itemCode);
        if (isset($this->productCache[$normalized])) {
            return $this->productCache[$normalized];
        }

        $productQuery = Product::withTrashed()
            ->whereRaw('LOWER(code) = ?', [$normalized])
            ->orWhereRaw('LOWER(sap_model) = ?', [$normalized]);

        $product = $productQuery->first();

        if ($product) {
            if (method_exists($product, 'trashed') && $product->trashed()) {
                $product->restore();
            }
            return $this->productCache[$normalized] = $product->id;
        }

        $payload = [
            'code' => $itemCode,
            'name' => $itemDesc ?: $itemCode,
            'sap_model' => $itemCode,
            'description' => $itemDesc,
            'is_active' => true,
        ];

        try {
            $product = Product::create($payload);
        } catch (QueryException $e) {
            $product = $productQuery->first();
            if (!$product) {
                throw $e;
            }
        }

        if ($product->trashed()) {
            $product->restore();
        }

        return $this->productCache[$normalized] = $product->id;
    }

    private function allocateForecastIfNeeded(PurchaseOrder $po, array $data, QuotaAllocationService $service): void
    {
        if (!$po->product_id) {
            return;
        }

        $qty = isset($data['qty']) ? (int) round((float) $data['qty']) : 0;
        if ($qty <= 0 || empty($data['created_date'])) {
            return;
        }

        $hasPivot = DB::table('purchase_order_quota')
            ->where('purchase_order_id', $po->id)
            ->exists();

        if ($hasPivot) {
            return;
        }

        $service->allocateForecast($po->product_id, $qty, $data['created_date'], $po);
    }
}
