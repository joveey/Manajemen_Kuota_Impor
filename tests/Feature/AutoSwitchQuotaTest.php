<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\Quota;
use App\Models\QuotaHistory;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoSwitchQuotaTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private Product $product;
    private Quota $currentYearQuota;
    private Quota $nextYearQuota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PurchaseOrderService::class);

        $this->product = Product::create([
            'code' => 'AUTO-001',
            'name' => 'AC Auto Switch',
            'sap_model' => 'AUTO-SW',
            'category' => 'Test',
            'pk_capacity' => 1.0,
            'description' => 'Unit for auto switch testing',
            'is_active' => true,
        ]);

        $this->currentYearQuota = Quota::create([
            'quota_number' => 'Q-2025',
            'name' => 'Current Year Quota',
            'government_category' => 'AC 0.5 PK - 2 PK',
            'period_start' => '2025-01-01',
            'period_end' => '2025-12-31',
            'total_allocation' => 10000,
            'forecast_remaining' => 9000,
            'actual_remaining' => 9500,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->nextYearQuota = Quota::create([
            'quota_number' => 'Q-2026',
            'name' => 'Next Year Quota',
            'government_category' => 'AC 0.5 PK - 2 PK',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'total_allocation' => 12000,
            'forecast_remaining' => 12000,
            'actual_remaining' => 12000,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->currentYearQuota->id,
            'priority' => 1,
            'is_primary' => true,
        ]);

        ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->nextYearQuota->id,
            'priority' => 2,
            'is_primary' => false,
        ]);
    }

    public function test_december_order_switches_to_next_year_quota(): void
    {
        $po = $this->service->create([
            'product_id' => $this->product->id,
            'quantity' => 150,
            'order_date' => '2025-12-05',
            'po_number' => 'PO-DEC-001',
            'sequence_number' => 1,
            'plant_name' => 'Plant Test',
            'plant_detail' => 'Jl. Pengujian 1',
        ]);

        $this->assertSame($this->nextYearQuota->id, $po->quota_id, 'POs in December must use next year quota.');

        $this->assertDatabaseHas('quota_histories', [
            'quota_id' => $this->nextYearQuota->id,
            'change_type' => QuotaHistory::TYPE_SWITCH_OVER,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_non_december_order_uses_primary_quota(): void
    {
        $po = $this->service->create([
            'product_id' => $this->product->id,
            'quantity' => 80,
            'order_date' => '2025-11-15',
            'po_number' => 'PO-NOV-001',
            'sequence_number' => 2,
            'plant_name' => 'Plant Test',
            'plant_detail' => 'Jl. Pengujian 1',
        ]);

        $this->assertSame($this->currentYearQuota->id, $po->quota_id, 'POs outside December should still use the primary quota.');

        $this->assertDatabaseMissing('quota_histories', [
            'change_type' => QuotaHistory::TYPE_SWITCH_OVER,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }
}
