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
        return DB::transaction(function () use ($shipmentId, $payload, $performedByUserId) {
            $shipment = Shipment::with(['purchaseOrder.quota'])
                ->lockForUpdate()
                ->findOrFail($shipmentId);

            // Validate quantity_received > 0 (integer)
            $qty = (int) ($payload['quantity_received'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'quantity_received' => 'Quantity received must be greater than 0.'
                ]);
            }

            // Validate planned > 0
            $planned = (int) $shipment->quantity_planned;
            if ($planned <= 0) {
                throw ValidationException::withMessages([
                    'quantity_planned' => 'Planned shipment quantity is not valid.'
                ]);
            }

            // Calculate total received so far
            $receivedSoFar = (int) $shipment->receipts()->sum('quantity_received');

            // Prevent receiving more than planned quantity
            if ($receivedSoFar + $qty > $planned) {
                throw ValidationException::withMessages([
                    'quantity_received' => 'Total received exceeds the planned shipment quantity.'
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

            // Pick quota based on PK & receipt date period (without overwriting forecast)
            $po = $shipment->purchaseOrder;
            $product = $po?->product;
            $date = $receipt->receipt_date ?? now()->toDateString();

            if (!$product) {
                throw ValidationException::withMessages(['product' => 'Product for the PO was not found.']);
            }

            $quota = \App\Models\Quota::query()
                ->where('is_active', true)
                ->whereDate('period_start','<=',$date)
                ->whereDate('period_end','>=',$date)
                ->get()
                ->first(function ($q) use ($product) { return $q->matchesProduct($product); });

            if (!$quota) {
                throw ValidationException::withMessages(['quota' => 'No matching quota found for the period/PK.']);
            }

            // Idempotent: avoid repeated decrements for the same receipt
            $existsHist = \Illuminate\Support\Facades\DB::table('quota_histories')
                ->where('change_type', \App\Models\QuotaHistory::TYPE_ACTUAL_DECREASE)
                ->where('meta->receipt_id', $receipt->id)
                ->exists();
            if (!$existsHist) {
                $quota->decrementActual(
                    (int)$qty,
                    sprintf('GR %s on %s', (string)($po?->po_number ?? $shipment->id), (string)$date),
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

            // Update shipment status when all quantity is received
            $totalReceived = (int) $shipment->receipts()->sum('quantity_received');
            if ($totalReceived === $planned) {
                $shipment->status = 'received';
                $shipment->save();
            }

            // Return the newly-created receipt
            return $receipt;
        });
    }
}
