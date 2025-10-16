<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\Shipment;
use App\Models\ShipmentReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure middleware expecting verified users passes
        User::factory()->create(); // warm factory cache if needed
    }

    private function prepareScenario(): void
    {
        $quota = Quota::create([
            'quota_number' => 'Q-001',
            'name' => 'Test Quota',
            'government_category' => '1 PK - 2 PK',
            'period_start' => now()->startOfYear(),
            'period_end' => now()->endOfYear(),
            'total_allocation' => 1000,
            'forecast_remaining' => 600,
            'actual_remaining' => 700,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $product = Product::create([
            'code' => 'PRD-001',
            'name' => 'Produk Uji',
            'sap_model' => 'SAP-01',
            'category' => 'AC',
            'pk_capacity' => 1.5,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'product_id' => $product->id,
            'quota_id' => $quota->id,
            'quantity' => 200,
            'quantity_shipped' => 0,
            'quantity_received' => 0,
            'order_date' => '2025-06-01',
            'plant_name' => 'Plant A',
            'plant_detail' => 'Plant Detail',
            'status' => PurchaseOrder::STATUS_ORDERED,
        ]);

        $shipment = Shipment::create([
            'shipment_number' => 'SHIP-001',
            'purchase_order_id' => $purchaseOrder->id,
            'quantity_planned' => 150,
            'quantity_received' => 0,
            'ship_date' => '2025-06-15',
            'eta_date' => '2025-06-30',
            'status' => Shipment::STATUS_IN_TRANSIT,
        ]);

        ShipmentReceipt::create([
            'shipment_id' => $shipment->id,
            'receipt_date' => '2025-07-10',
            'quantity_received' => 120,
            'document_number' => 'DOC-001',
        ]);
    }

    public function test_actual_mode_dataset_aggregates_receipts(): void
    {
        $this->prepareScenario();
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/analytics/data?mode=actual&start_date=2025-01-01&end_date=2025-12-31');
        $response->assertOk();
        $response->assertJsonPath('mode', 'actual');
        $response->assertJsonPath('labels.primary', 'Actual (Good Receipt)');

        $row = $response->json('table.rows.0');
        $this->assertNotNull($row);
        $this->assertEquals('Q-001', $row['quota_number']);
        $this->assertEquals(1000, $row['initial_quota']);
        $this->assertEquals(120, $row['primary_value']);
        $this->assertEquals(880, $row['secondary_value']);
        $this->assertEquals(12.0, $row['percentage']);

        $this->assertEquals(120, $response->json('summary.total_usage'));
        $this->assertEquals(880, $response->json('summary.total_remaining'));
    }

    public function test_forecast_mode_dataset_aggregates_purchase_orders(): void
    {
        $this->prepareScenario();
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/analytics/data?mode=forecast&start_date=2025-01-01&end_date=2025-12-31');
        $response->assertOk();
        $response->assertJsonPath('mode', 'forecast');
        $response->assertJsonPath('labels.primary', 'Forecast (Purchase Orders)');

        $row = $response->json('table.rows.0');
        $this->assertNotNull($row);
        $this->assertEquals(1000, $row['initial_quota']);
        $this->assertEquals(200, $row['primary_value']);
        $this->assertEquals(800, $row['secondary_value']);
        $this->assertEquals(20.0, $row['percentage']);

        $this->assertEquals(200, $response->json('summary.total_usage'));
        $this->assertEquals(800, $response->json('summary.total_remaining'));
    }
}
