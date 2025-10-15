<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentReceipt;
use App\Models\Quota;
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

            // Lock baris Quota dan update actual_remaining
            $quotaId = $shipment->purchaseOrder->quota_id;
            $quota = Quota::whereKey($quotaId)->lockForUpdate()->firstOrFail();
            $quota->actual_remaining = max(0, (int)$quota->actual_remaining - $qty);

            // Update status quota (fallback sederhana bila updateStatus() belum ada)
            if (method_exists($quota, 'updateStatus')) {
                $quota->updateStatus();
            } else {
                $alloc = max(1, (int)$quota->total_allocation);
                $ratio = $quota->actual_remaining / $alloc;
                if ($ratio < 0.2) {
                    $quota->status = 'depleted';
                } elseif ($ratio < 0.5) {
                    $quota->status = 'limited';
                } else {
                    $quota->status = 'available';
                }
            }
            $quota->save();

            // Opsional: update status shipment bila total diterima == planned
            $totalAfter = $receivedSoFar + $qty;
            if ($totalAfter === $planned && method_exists($shipment, 'setReceivedStatus')) {
                $shipment->setReceivedStatus();
            }

            // Kembalikan receipt yang baru dibuat
            return $receipt;
        });
    }
}
