<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\Shipment;
use App\Services\Exceptions\InsufficientQuotaException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly QuotaAllocator $allocator)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @throws InsufficientQuotaException
     */
    public function create(array $data, ?Authenticatable $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $product = Product::with('quotaMappings')
                ->findOrFail($data['product_id']);

            $quantity = (int) $data['quantity'];

            $allocation = $this->allocator->allocate($product, $quantity);
            $selectedQuota = $allocation->selectedQuota;

            $sequence = $data['sequence_number']
                ?? ((int) (PurchaseOrder::lockForUpdate()->max('sequence_number')) + 1);

            $orderDate = Carbon::parse($data['order_date']);
            $period = $data['period'] ?? $orderDate->format('Y-m');

            $po = PurchaseOrder::create([
                'sequence_number' => $sequence,
                'period' => $period,
                'po_number' => $data['po_number'],
                'sap_reference' => Arr::get($data, 'sap_reference'),
                'product_id' => $product->id,
                'quota_id' => $selectedQuota->id,
                'quantity' => $quantity,
                'order_date' => $orderDate->toDateString(),
                'pgi_branch' => Arr::get($data, 'pgi_branch'),
                'customer_name' => Arr::get($data, 'customer_name'),
                'pic_name' => Arr::get($data, 'pic_name'),
                'status' => PurchaseOrder::STATUS_ORDERED,
                'status_po_display' => Arr::get($data, 'status_po_display', 'Released'),
                'truck' => Arr::get($data, 'truck'),
                'moq' => Arr::get($data, 'moq'),
                'category' => Arr::get($data, 'category'),
                'plant_name' => $data['plant_name'],
                'plant_detail' => $data['plant_detail'],
                'remarks' => Arr::get($data, 'remarks'),
                'created_by' => $user?->getAuthIdentifier(),
                'forecast_deducted_at' => now(),
            ]);

            $selectedQuota->decrementForecast(
                $quantity,
                sprintf('Forecast dikurangi oleh PO %s', $po->po_number),
                $po,
                $orderDate,
                $user?->getAuthIdentifier()
            );

            if ($allocation->switched()) {
                $selectedQuota->switchOver(
                    sprintf('Auto switch ke kuota %s untuk PO %s', $selectedQuota->quota_number, $po->po_number),
                    $po,
                    $user?->getAuthIdentifier()
                );
            }

            return $po;
        });
    }

    public function registerShipment(PurchaseOrder $purchaseOrder, array $data): Shipment
    {
        return DB::transaction(function () use ($purchaseOrder, $data) {
            $shipment = $purchaseOrder->shipments()->create([
                'shipment_number' => $data['shipment_number'] ?? null,
                'quantity_planned' => $data['quantity_planned'],
                'ship_date' => Arr::get($data, 'ship_date'),
                'eta_date' => Arr::get($data, 'eta_date'),
                'status' => Shipment::STATUS_IN_TRANSIT,
                'detail' => Arr::get($data, 'detail'),
                'auto_generated' => Arr::get($data, 'auto_generated', false),
            ]);

            $purchaseOrder->refreshAggregates();

            return $shipment;
        });
    }

    public function registerShipmentReceipt(Shipment $shipment, array $data, ?Authenticatable $user = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $user) {
            $receiptDate = Carbon::parse($data['receipt_date']);
            $quantity = (int) $data['quantity_received'];

            $shipment->recordReceipt(
                $quantity,
                $receiptDate,
                Arr::get($data, 'notes'),
                Arr::get($data, 'document_number'),
                $user?->getAuthIdentifier()
            );

            $purchaseOrder = $shipment->purchaseOrder;
            $purchaseOrder->refreshAggregates();

            /** @var Quota $quota */
            $quota = $purchaseOrder->quota()->lockForUpdate()->first();

            $quota->decrementActual(
                $quantity,
                sprintf('Aktual dikurangi oleh receipt %s', $shipment->shipment_number),
                $shipment,
                $receiptDate,
                $user?->getAuthIdentifier()
            );

            if ($shipment->status === Shipment::STATUS_DELIVERED) {
                $purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
                $purchaseOrder->actual_completed_at = now();
                $purchaseOrder->save();
            }

            return $shipment->fresh(['receipts']);
        });
    }
}
