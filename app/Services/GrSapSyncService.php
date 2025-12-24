<?php

namespace App\Services;

use App\Models\GrReceipt;
use App\Models\PeriodSyncLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Repositories\Sap\GrReceiptRepository;
use App\Support\PeriodRange;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GrSapSyncService
{
    private const ERROR_LIMIT = 20;

    public function __construct(
        private readonly GrReceiptRepository $repository
    ) {
    }

    /**
     * Sync GR rows from SAP for a specific month (period key YYYY-MM).
     *
     * @return array{period:string,start:string,end:string,rows_read:int,inserted:int,updated:int,skipped:int,errors:array<int,array{message:string,context:array}>}
     */
    public function sync(string $periodKey): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
            throw new \InvalidArgumentException('Period key must be in YYYY-MM format.');
        }

        $year = (int) substr($periodKey, 0, 4);
        $month = (int) substr($periodKey, 5, 2);
        [$start, $exclusiveEnd] = PeriodRange::monthYear($month, $year);
        $end = $exclusiveEnd->copy()->subDay();
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $sapRows = $this->repository->fetchByPeriod($start->copy(), $end->copy());

        $summary = [
            'period' => $periodKey,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'rows_read' => (int) $sapRows->count(),
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $seen = [];
        $sapRows->each(function ($row, int $index) use (&$summary, &$seen) {
            $payload = $this->normalizeRow($row, $index);
            if (isset($payload['error'])) {
                $summary['skipped']++;
                $this->pushError($summary['errors'], $payload['error'], $payload['context']);
                return;
            }

            if (isset($seen[$payload['signature']])) {
                $summary['skipped']++;
                $this->pushError($summary['errors'], 'Duplikasi data SAP (signature sama dalam batch)', [
                    'po' => $payload['po_no'],
                    'line' => $payload['line_no'],
                    'signature' => $payload['signature'],
                ]);
                return;
            }
            $seen[$payload['signature']] = true;

            try {
                $result = DB::transaction(fn () => $this->processRow($payload));
            } catch (\Throwable $e) {
                $summary['skipped']++;
                $this->pushError($summary['errors'], $e->getMessage(), [
                    'po' => $payload['po_no'],
                    'line' => $payload['line_no'],
                    'row' => $payload['row_index'],
                ]);
                return;
            }

            if ($result['action'] === 'inserted') {
                $summary['inserted']++;
            } elseif ($result['action'] === 'updated') {
                $summary['updated']++;
            } else {
                $summary['skipped']++;
            }
        });

        PeriodSyncLog::record('gr_receipts', $start, $exclusiveEnd, ['summary' => $summary]);

        return $summary;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{action:string}
     */
    private function processRow(array $payload): array
    {
        $po = PurchaseOrder::query()
            ->with('product')
            ->where('po_doc', $payload['po_no'])
            ->where('line_no', $payload['line_no'])
            ->lockForUpdate()
            ->first();

        if (!$po) {
            throw new \RuntimeException('PO / Line tidak ditemukan di sistem');
        }

        $product = $this->resolveProductForPurchaseOrder($po);
        if (!$product) {
            throw new \RuntimeException('Produk belum terhubung atau tidak memiliki HS/PK');
        }

        $quota = $this->resolveQuotaForProduct($product, $payload['date']);
        if (!$quota) {
            throw new \RuntimeException('Quota aktif yang cocok tidak ditemukan');
        }

        $existing = GrReceipt::query()
            ->where('gr_unique', $payload['signature'])
            ->lockForUpdate()
            ->first();

        $receiptData = $this->buildReceiptData($payload, $po);
        $delta = (float) $payload['qty'];

        if ($existing) {
            $delta = (float) $payload['qty'] - (float) $existing->qty;
            $existing->fill($receiptData);
            if ($existing->isDirty()) {
                $existing->save();
            }

            if ($delta <= 0.00001) {
                return ['action' => 'noop'];
            }

            $receipt = $existing;
            $action = 'updated';
        } else {
            $receipt = GrReceipt::create($receiptData);
            $action = 'inserted';
        }

        $this->applyReceiveDelta($po, $delta);
        $this->deductActual($quota, $delta, $receipt, $payload);

        return ['action' => $action];
    }

    /**
     * @param mixed $row
     * @return array<string,mixed>
     */
    private function normalizeRow($row, int $index): array
    {
        $poNo = $this->normalizeString($row->po_number ?? $row->po_no ?? '');
        $line = $this->normalizeLineNumber($row->po_line ?? $row->line_no ?? '');
        $qty = $this->normalizeQuantity($row->qty ?? null);
        $date = $this->parseDate($row->posting_date ?? $row->receive_date ?? null);
        $invoice = $this->normalizeString($row->invoice_no ?? $row->invoice ?? '');

        if ($poNo === '' || $line === '' || !$date || $qty <= 0) {
            return [
                'error' => 'Data SAP tidak lengkap (PO/Line/Date/QTY)',
                'context' => [
                    'row' => $index + 1,
                    'po' => $poNo,
                    'line' => $line,
                ],
            ];
        }

        $signature = $this->buildSignature(
            $row->material_doc ?? null,
            $row->material_doc_year ?? $row->doc_year ?? $row->fiscal_year ?? null,
            $row->material_doc_item ?? null,
            $poNo,
            $line,
            $date,
            $invoice,
            $index
        );

        return [
            'row_index' => $index + 1,
            'po_no' => $poNo,
            'line_no' => $line,
            'date' => $date,
            'qty' => $qty,
            'invoice' => $invoice ?: null,
            'signature' => $signature,
            'mat_doc' => $this->normalizeString($row->material_doc ?? ''),
            'mat_doc_year' => $this->normalizeString($row->material_doc_year ?? $row->doc_year ?? $row->fiscal_year ?? ''),
            'mat_doc_item' => $this->normalizeString($row->material_doc_item ?? ''),
            'sap_category' => $this->normalizeString($row->category_code ?? $row->cat ?? ''),
            'sap_category_desc' => $this->normalizeString($row->category_desc ?? $row->cat_desc ?? ''),
            'item_desc' => $this->normalizeString($row->item_desc ?? ''),
            'vendor_code' => $this->normalizeString($row->vendor_code ?? ''),
            'vendor_name' => $this->normalizeString($row->vendor_name ?? ''),
            'wh_code' => $this->normalizeString($row->plant_code ?? $row->wh_code ?? ''),
            'wh_name' => $this->normalizeString($row->plant_name ?? $row->wh_name ?? ''),
            'sloc_code' => $this->normalizeString($row->storage_location ?? $row->sloc_code ?? ''),
            'sloc_name' => $this->normalizeString($row->sloc_name ?? ''),
            'currency' => $this->normalizeString($row->currency ?? ''),
            'amount' => is_numeric($row->amount ?? null) ? (float) $row->amount : null,
            'deliv_amount' => is_numeric($row->deliv_amount ?? null) ? (float) $row->deliv_amount : null,
        ];
    }

    private function buildReceiptData(array $payload, PurchaseOrder $po): array
    {
        return [
            'po_no' => $payload['po_no'],
            'line_no' => $payload['line_no'],
            'invoice_no' => $payload['invoice'],
            'receive_date' => $payload['date'],
            'qty' => $payload['qty'],
            'gr_unique' => $payload['signature'],
            'cat_po' => $po->cat_po ?? $payload['sap_category'],
            'cat_po_desc' => $po->cat_desc ?? $payload['sap_category_desc'],
            'item_name' => $payload['item_desc'] ?: ($po->item_desc ?? null),
            'vendor_code' => $payload['vendor_code'] ?: ($po->vendor_no ?? null),
            'vendor_name' => $payload['vendor_name'] ?: ($po->vendor_name ?? null),
            'wh_code' => $payload['wh_code'] ?: ($po->wh_code ?? null),
            'wh_name' => $payload['wh_name'] ?: ($po->wh_name ?? null),
            'sloc_code' => $payload['sloc_code'] ?: ($po->subinv_code ?? null),
            'sloc_name' => $payload['sloc_name'] ?: ($po->subinv_name ?? null),
            'currency' => $payload['currency'] ?: null,
            'amount' => $payload['amount'],
            'deliv_amount' => $payload['deliv_amount'],
            'mat_doc' => $payload['mat_doc'],
            'cat' => $payload['sap_category'],
            'cat_desc' => $payload['sap_category_desc'],
        ];
    }

    private function applyReceiveDelta(PurchaseOrder $po, float $delta): void
    {
        if ($delta <= 0) {
            return;
        }

        $po->quantity_received = (float) ($po->quantity_received ?? 0) + $delta;

        if ($po->quantity_received >= (float) ($po->qty ?? 0)) {
            $po->status = PurchaseOrder::STATUS_COMPLETED;
            $po->actual_completed_at = now();
        } elseif ($po->quantity_received > 0) {
            $po->status = PurchaseOrder::STATUS_PARTIAL;
        }

        $po->save();
    }

    private function deductActual(Quota $quota, float $delta, GrReceipt $receipt, array $payload): void
    {
        $qtyInt = (int) round($delta);
        if ($qtyInt <= 0) {
            return;
        }

        $quota->decrementActual(
            $qtyInt,
            sprintf('SAP GR %s/%s pada %s', $payload['po_no'], $payload['line_no'], $payload['date']),
            $receipt,
            new \DateTimeImmutable($payload['date']),
            Auth::id(),
            [
                'gr_unique' => $payload['signature'],
                'source' => 'sap_sync',
                'mat_doc' => $payload['mat_doc'],
                'mat_doc_year' => $payload['mat_doc_year'] ?? null,
            ]
        );
    }

    private function resolveProductForPurchaseOrder(PurchaseOrder $po): ?Product
    {
        if (!$po->product_id) {
            return null;
        }

        $product = $po->relationLoaded('product') ? $po->product : Product::find($po->product_id);
        if (!$product) {
            return null;
        }

        if ($product->pk_capacity === null || empty($product->hs_code)) {
            return null;
        }

        return $product;
    }

    private function resolveQuotaForProduct(Product $product, string $date): ?Quota
    {
        return Quota::query()
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('period_start')->orWhere('period_start', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('period_end')->orWhere('period_end', '>=', $date);
            })
            ->get()
            ->first(fn (Quota $quota) => $quota->matchesProduct($product));
    }

    private function normalizeString(?string $value): string
    {
        return trim((string) $value);
    }

    private function normalizeLineNumber(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return (string) ((int) $value);
        }

        return ltrim($value, '0') ?: '0';
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeQuantity($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace([','], [''], $value);
            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return 0.0;
    }

    /**
     * Build deterministic signature. Priority is SAP material document (MBLNR/MJAHR/ZEILE),
     * falling back to PO + invoice and finally row index to avoid accidental duplicates.
     */
    private function buildSignature(?string $matDoc, ?string $matYear, ?string $matItem, string $poNo, string $line, string $date, ?string $invoice, int $index): string
    {
        $matDoc = $this->normalizeString($matDoc ?? '');
        $matYear = $this->normalizeString($matYear ?? '');
        $matItem = $this->normalizeString($matItem ?? '');
        if ($matDoc !== '' && $matYear !== '' && $matItem !== '') {
            return sha1('SAP|'.$matDoc.'|'.$matYear.'|'.$matItem);
        }

        if (!empty($invoice)) {
            return sha1($poNo.'|'.$line.'|'.$date.'|'.$invoice);
        }

        return sha1($poNo.'|'.$line.'|'.$date.'|row:'.$index);
    }

    private function pushError(array &$errors, string $message, array $context = []): void
    {
        if (count($errors) >= self::ERROR_LIMIT) {
            return;
        }

        $errors[] = [
            'message' => $message,
            'context' => $context,
        ];
    }
}
