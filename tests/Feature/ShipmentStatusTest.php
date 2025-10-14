<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\Shipment;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShipmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private PurchaseOrder $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PurchaseOrderService::class);

        $product = Product::create([
            'code' => 'SHIP-001',
            'name' => 'Unit Pending Test',
            'sap_model' => 'SHIP-PENDING',
            'category' => 'Test',
            'pk_capacity' => 1.0,
            'description' => 'Produk uji shipment',
            'is_active' => true,
        ]);

        $quota = Quota::create([
            'quota_number' => 'QT-TEST',
            'name' => 'Kuota Shipment Test',
            'government_category' => 'AC 0.5 PK - 2 PK',
            'period_start' => '2025-01-01',
            'period_end' => '2026-12-31',
            'total_allocation' => 50000,
            'forecast_remaining' => 50000,
            'actual_remaining' => 50000,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->purchaseOrder = PurchaseOrder::create([
            'sequence_number' => 1,
            'period' => '2025-11',
            'po_number' => 'PO-TEST-001',
            'sap_reference' => 'SAP-TEST',
            'product_id' => $product->id,
            'quota_id' => $quota->id,
            'quantity' => 400,
            'quantity_shipped' => 0,
            'quantity_received' => 0,
            'order_date' => '2025-11-01',
            'pgi_branch' => 'TEST BRANCH',
            'customer_name' => 'PT Testing',
            'pic_name' => 'QA Team',
            'status' => PurchaseOrder::STATUS_ORDERED,
            'status_po_display' => 'Released',
            'truck' => 'Internal',
            'moq' => 'Standard',
            'category' => 'Testing',
            'plant_name' => 'Test Plant',
            'plant_detail' => 'Jl. Test No 1',
            'remarks' => 'PO untuk pengujian shipment',
        ]);
    }

    public function test_pending_shipment_transitions_to_in_transit(): void
    {
        Carbon::setTestNow('2025-12-01');

        $shipment = $this->service->registerShipment($this->purchaseOrder, [
            'quantity_planned' => 150,
            'ship_date' => '2025-12-20',
            'eta_date' => '2025-12-28',
        ]);

        $shipment->refresh()->load('statusLogs');

        $this->assertSame(Shipment::STATUS_PENDING, $shipment->status);
        $this->assertTrue($shipment->statusLogs->pluck('status')->contains(Shipment::STATUS_PENDING));

        Carbon::setTestNow('2025-12-21');
        $shipment->syncScheduledStatus('Status otomatis pengujian.');
        $shipment->refresh()->load('statusLogs');

        $this->assertSame(Shipment::STATUS_IN_TRANSIT, $shipment->status);
        $this->assertTrue($shipment->statusLogs->pluck('status')->contains(Shipment::STATUS_IN_TRANSIT));

        Carbon::setTestNow();
    }

    public function test_partial_and_follow_up_shipments_log_statuses(): void
    {
        Carbon::setTestNow('2026-01-01');

        $shipment = $this->service->registerShipment($this->purchaseOrder, [
            'quantity_planned' => 200,
            'ship_date' => '2025-12-15',
        ]);

        $shipment = $shipment->refresh();
        $this->assertSame(Shipment::STATUS_IN_TRANSIT, $shipment->status);

        $this->service->registerShipmentReceipt($shipment, [
            'receipt_date' => '2026-01-05',
            'quantity_received' => 80,
            'document_number' => 'RCPT-TEST-001',
        ]);

        $shipment->refresh()->load('statusLogs');
        $this->assertSame(Shipment::STATUS_PARTIAL, $shipment->status);
        $this->assertTrue($shipment->statusLogs->pluck('status')->contains(Shipment::STATUS_PARTIAL));

        $this->purchaseOrder->refresh();

        $followUp = $this->purchaseOrder->shipments()
            ->where('auto_generated', true)
            ->latest('id')
            ->first();

        $this->assertNotNull($followUp);
        $this->assertSame(Shipment::STATUS_PENDING, $followUp->status);

        $this->service->registerShipmentReceipt($followUp, [
            'receipt_date' => '2026-01-12',
            'quantity_received' => $followUp->quantity_planned,
        ]);

        $followUp->refresh()->load('statusLogs');

        $this->assertSame(Shipment::STATUS_DELIVERED, $followUp->status);
        $this->assertTrue($followUp->statusLogs->pluck('status')->contains(Shipment::STATUS_DELIVERED));

        Carbon::setTestNow();
    }
}
