<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quota;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Models\ShipmentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_final_report_page_is_accessible(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'admin')->firstOrFail();
        $user->roles()->attach($role);

        $quota = Quota::create([
            'quota_number' => 'RP-001',
            'name' => 'Quota Report Test',
            'government_category' => 'AC 1 PK - 2 PK',
            'total_allocation' => 5000,
            'forecast_remaining' => 3200,
            'actual_remaining' => 2900,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $product = Product::create([
            'code' => 'REP-01',
            'name' => 'Report Product',
            'sap_model' => 'REP-01',
            'category' => 'Testing',
            'pk_capacity' => 1.5,
            'description' => 'Product for final report testing',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'sequence_number' => 10,
            'period' => '2025-10',
            'po_number' => 'PO-REPORT-001',
            'product_id' => $product->id,
            'quota_id' => $quota->id,
            'quantity' => 400,
            'quantity_received' => 200,
            'order_date' => '2025-10-01',
            'status' => PurchaseOrder::STATUS_IN_TRANSIT,
            'status_po_display' => 'In Transit',
            'plant_name' => 'Test Plant',
            'plant_detail' => 'Testing Street',
        ]);

        $shipment = Shipment::create([
            'shipment_number' => 'SHIP-REPORT-001',
            'purchase_order_id' => $po->id,
            'quantity_planned' => 400,
            'quantity_received' => 200,
            'ship_date' => '2025-10-05',
            'eta_date' => '2025-10-20',
            'status' => Shipment::STATUS_PARTIAL,
        ]);

        ShipmentReceipt::create([
            'shipment_id' => $shipment->id,
            'receipt_date' => '2025-10-21',
            'quantity_received' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('admin.reports.final'));

        $response->assertOk();
        $response->assertSee('Laporan Gabungan');
        $response->assertSee('Outstanding Shipment');
        $response->assertViewHas('rows');
        $response->assertViewHas('charts');
    }

    public function test_final_report_export_csv(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'admin')->firstOrFail();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.reports.final.export.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }
}
