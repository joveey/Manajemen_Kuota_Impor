<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentReceiptService
{
    public function processReceipt($shipmentId, array $payload, $performedByUserId = null): ShipmentReceipt
    {
        return DB::transaction(function () use ($shipmentId, $payload) {
            $shipment = Shipment::with(['purchaseOrder.quota'])
                ->lockForUpdate()
                ->findOrFail($shipmentId);

            // Validasi quantity_received > 0 (integer)
            $qty = (int) ($payload['quantity_received'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'quantity_received' => 'Quantity received harus lebih dari 0.'
                ]);
            }

            // Validasi planned > 0
            $planned = (int) $shipment->quantity_planned;
            if ($planned <= 0) {
                throw ValidationException::withMessages([
                    'quantity_planned' => 'Quantity planned shipment tidak valid.'
                ]);
            }

            // Hitung total yang sudah diterima sejauh ini
            $receivedSoFar = (int) $shipment->receipts()->sum('quantity_received');

            // Validasi over-receive terhadap quantity planned
            if ($receivedSoFar + $qty > $planned) {
                throw ValidationException::withMessages([
                    'quantity_received' => 'Total penerimaan melebihi quantity planned shipment.'
                ]);
            }

            // Simpan receipt
            /** @var ShipmentReceipt $receipt */
            $receipt = new ShipmentReceipt();
            $receipt->shipment_id = $shipment->id;
            $receipt->receipt_date = $payload['receipt_date'] ?? now()->toDateString();
            $receipt->quantity_received = $qty;
            $receipt->document_number = $payload['document_number'] ?? null;
            $receipt->notes = $payload['notes'] ?? null;
            $receipt->save();

            // Tentukan kuota berdasarkan PK & periode tanggal penerimaan (tanpa overwrite forecast)
            $po = $shipment->purchaseOrder;
            $product = $po?->product;
            $date = $receipt->receipt_date ?? now()->toDateString();

            if (!$product) {
                throw ValidationException::withMessages(['product' => 'Produk untuk PO tidak ditemukan.']);
            }

            $quota = \App\Models\Quota::query()
                ->where('is_active', true)
                ->whereDate('period_start','<=',$date)
                ->whereDate('period_end','>=',$date)
                ->get()
                ->first(function ($q) use ($product) { return $q->matchesProduct($product); });

            if (!$quota) {
                throw ValidationException::withMessages(['quota' => 'Kuota yang cocok untuk periode/PK tidak ditemukan.']);
            }

            // Idempoten: hindari pengurangan berulang untuk receipt yang sama
            $existsHist = \Illuminate\Support\Facades\DB::table('quota_histories')
                ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
                ->where('meta->receipt_id', $receipt->id)
                ->exists();
            if (!$existsHist) {
                $quota->decrementActual(
                    (int)$qty,
                    sprintf('GR %s pada %s', (string)($po?->po_number ?? $shipment->id), (string)$date),
                    $receipt,
                    new \DateTimeImmutable((string)$date),
                    $performedByUserId,
                    [
                        'receipt_id' => $receipt->id,
                        'po_no' => (string)($po?->po_number ?? ''),
                        'shipment_id' => $shipment->id,
                    ]
                );
            }

            // Update status shipment bila seluruh quantity sudah diterima
            $totalReceived = (int) $shipment->receipts()->sum('quantity_received');
            if ($totalReceived === $planned) {
                $shipment->status = 'received';
                $shipment->save();
            }

            // Kembalikan receipt yang baru dibuat
            return $receipt;
        });
    }
}
