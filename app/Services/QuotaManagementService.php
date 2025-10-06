<?php
// app/Services/QuotaManagementService.php

namespace App\Services;

use App\Models\Quota;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\QuotaHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class QuotaManagementService
{
    /**
     * Create new Purchase Order with quota validation
     */
    public function createPurchaseOrder($data)
    {
        return DB::transaction(function () use ($data) {
            // 1. Get quota and validate
            $quota = Quota::with('product')->findOrFail($data['quota_id']);
            
            if (!$quota->isAvailable($data['quantity'])) {
                throw new Exception("Kuota tidak mencukupi. Tersisa: {$quota->remaining} unit");
            }

            // 2. Check if quota is still in period
            if (now()->lt($quota->period_start) || now()->gt($quota->period_end)) {
                throw new Exception("Kuota sudah tidak berlaku pada periode ini");
            }

            // 3. Create Purchase Order
            $po = PurchaseOrder::create([
                'po_number' => $data['po_number'],
                'quota_id' => $quota->id,
                'product_id' => $quota->product_id,
                'factory_id' => $data['factory_id'],
                'quantity' => $data['quantity'],
                'po_date' => $data['po_date'],
                'status' => 'created',
                'notes' => $data['notes'] ?? null
            ]);

            // 4. Update quota forecast (reserve)
            $balanceBefore = $quota->remaining;
            $quota->actual_quantity += $data['quantity'];
            $quota->updateStatus();
            $quota->save();

            // 5. Create history log
            QuotaHistory::create([
                'quota_id' => $quota->id,
                'action' => 'po_created',
                'quantity_change' => $data['quantity'],
                'balance_before' => $balanceBefore,
                'balance_after' => $quota->remaining,
                'user_id' => Auth::id(),
                'description' => "PO {$po->po_number} created with {$data['quantity']} units"
            ]);

            return $po;
        });
    }

    /**
     * Create shipment from PO
     */
    public function createShipment($poId, $data)
    {
        return DB::transaction(function () use ($poId, $data) {
            // 1. Get PO and validate
            $po = PurchaseOrder::with('quota')->findOrFail($poId);
            
            if ($po->status !== 'created') {
                throw new Exception("Purchase Order sudah diproses atau dibatalkan");
            }

            // Validate quantity
            if ($data['quantity_shipped'] > $po->quantity) {
                throw new Exception("Quantity shipment melebihi quantity PO");
            }

            // 2. Create Shipment
            $shipment = Shipment::create([
                'shipment_number' => $data['shipment_number'],
                'purchase_order_id' => $po->id,
                'quantity_shipped' => $data['quantity_shipped'],
                'quantity_received' => 0,
                'shipment_date' => $data['shipment_date'],
                'estimated_arrival' => $data['estimated_arrival'],
                'status' => 'new',
                'vessel_name' => $data['vessel_name'] ?? null,
                'port_origin' => $data['port_origin'] ?? null,
                'port_destination' => $data['port_destination'] ?? null,
                'shipping_details' => $data['shipping_details'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);

            // 3. Update PO status
            $po->status = 'in_shipment';
            $po->save();

            // 4. Create history log
            QuotaHistory::create([
                'quota_id' => $po->quota_id,
                'action' => 'shipment_created',
                'quantity_change' => 0,
                'balance_before' => $po->quota->remaining,
                'balance_after' => $po->quota->remaining,
                'user_id' => Auth::id(),
                'description' => "Shipment {$shipment->shipment_number} created for PO {$po->po_number}"
            ]);

            return $shipment;
        });
    }

    /**
     * Receive goods from shipment
     */
    public function receiveShipment($shipmentId, $quantityReceived = null)
    {
        return DB::transaction(function () use ($shipmentId, $quantityReceived) {
            // 1. Get shipment with relations
            $shipment = Shipment::with(['purchaseOrder.quota'])->findOrFail($shipmentId);
            
            if ($shipment->status === 'completed') {
                throw new Exception("Shipment sudah selesai diterima");
            }

            // Default to full quantity if not specified
            $qtyReceived = $quantityReceived ?? $shipment->quantity_shipped;

            // Validate quantity
            if ($qtyReceived > $shipment->quantity_shipped) {
                throw new Exception("Quantity received melebihi quantity shipped");
            }

            // 2. Update shipment
            $shipment->quantity_received = $qtyReceived;
            $shipment->actual_arrival = now();
            $shipment->status = 'completed';
            $shipment->save();

            // 3. Update PO status if all shipments completed
            $po = $shipment->purchaseOrder;
            if ($po->isFullyReceived()) {
                $po->status = 'completed';
                $po->save();
            }

            // 4. Update quota actual (confirmed received)
            $quota = $po->quota;
            // Note: actual_quantity sudah di-update saat PO dibuat (forecast)
            // Kita hanya perlu update status
            $quota->updateStatus();

            // 5. Create history log
            QuotaHistory::create([
                'quota_id' => $quota->id,
                'action' => 'shipment_received',
                'quantity_change' => 0,
                'balance_before' => $quota->remaining,
                'balance_after' => $quota->remaining,
                'user_id' => Auth::id(),
                'description' => "Shipment {$shipment->shipment_number} received: {$qtyReceived} units"
            ]);

            return $shipment;
        });
    }

    /**
     * Cancel Purchase Order
     */
    public function cancelPurchaseOrder($poId, $reason = null)
    {
        return DB::transaction(function () use ($poId, $reason) {
            $po = PurchaseOrder::with('quota', 'shipments')->findOrFail($poId);
            
            // Check if PO can be cancelled
            if ($po->status === 'completed') {
                throw new Exception("PO yang sudah completed tidak dapat dibatalkan");
            }

            if ($po->shipments()->whereIn('status', ['in_transit', 'completed'])->count() > 0) {
                throw new Exception("PO dengan shipment aktif tidak dapat dibatalkan");
            }

            // Restore quota
            $quota = $po->quota;
            $balanceBefore = $quota->remaining;
            $quota->actual_quantity -= $po->quantity;
            $quota->updateStatus();
            $quota->save();

            // Update PO status
            $po->status = 'cancelled';
            $po->notes = ($po->notes ?? '') . "\nCancelled: " . ($reason ?? 'No reason');
            $po->save();

            // Create history log
            QuotaHistory::create([
                'quota_id' => $quota->id,
                'action' => 'po_cancelled',
                'quantity_change' => -$po->quantity,
                'balance_before' => $balanceBefore,
                'balance_after' => $quota->remaining,
                'user_id' => Auth::id(),
                'description' => "PO {$po->po_number} cancelled. Quota restored: {$po->quantity} units"
            ]);

            return $po;
        });
    }

    /**
     * Get quota utilization report
     */
    public function getQuotaUtilizationReport($period = null)
    {
        $query = Quota::with(['product', 'purchaseOrders']);

        if ($period) {
            $query->where('period_start', '<=', $period)
                  ->where('period_end', '>=', $period);
        }

        return $query->get()->map(function ($quota) {
            return [
                'quota_number' => $quota->quota_number,
                'product_name' => $quota->product->name,
                'government_qty' => $quota->government_quantity,
                'forecast_qty' => $quota->forecast_quantity,
                'actual_qty' => $quota->actual_quantity,
                'remaining' => $quota->remaining,
                'usage_percentage' => $quota->usage_percentage,
                'status' => $quota->status,
                'period' => $quota->period_start->format('Y-m-d') . ' to ' . $quota->period_end->format('Y-m-d')
            ];
        });
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        $activeQuotas = Quota::where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get();

        $totalGovernmentQty = $activeQuotas->sum('government_quantity');
        $totalForecastQty = $activeQuotas->sum('forecast_quantity');
        $totalActualQty = $activeQuotas->sum('actual_quantity');
        $totalRemaining = $activeQuotas->sum('remaining');

        $activePOs = PurchaseOrder::whereIn('status', ['created', 'in_shipment'])->count();
        $activeShipments = Shipment::whereIn('status', ['new', 'in_transit'])->count();

        return [
            'active_quotas' => $activeQuotas->count(),
            'total_government_qty' => $totalGovernmentQty,
            'total_forecast_qty' => $totalForecastQty,
            'total_actual_qty' => $totalActualQty,
            'total_remaining' => $totalRemaining,
            'utilization_percentage' => $totalForecastQty > 0 ? round(($totalActualQty / $totalForecastQty) * 100, 2) : 0,
            'active_purchase_orders' => $activePOs,
            'active_shipments' => $activeShipments,
            'quotas_by_status' => [
                'available' => $activeQuotas->where('status', 'available')->count(),
                'low' => $activeQuotas->where('status', 'low')->count(),
                'exhausted' => $activeQuotas->where('status', 'exhausted')->count(),
            ]
        ];
    }
}