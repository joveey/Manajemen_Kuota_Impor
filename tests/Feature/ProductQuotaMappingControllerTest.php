<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductQuotaMapping;
use App\Models\Quota;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class ProductQuotaMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Quota $quotaPrimary;
    private Quota $quotaSecondary;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        $paths = config('view.paths', []);
        array_unshift($paths, resource_path('views'));
        config()->set('view.paths', array_unique($paths));

        $this->user = User::factory()->create();
        $role = Role::create([
            'name' => 'test-admin',
            'display_name' => 'Test Admin',
            'description' => 'Role khusus pengujian',
            'is_active' => true,
        ]);

        $readQuota = Permission::create([
            'name' => 'read quota',
            'display_name' => 'Read Quota',
            'group' => 'Test',
            'description' => 'Izin uji baca kuota',
        ]);

        $updateQuota = Permission::create([
            'name' => 'update quota',
            'display_name' => 'Update Quota',
            'group' => 'Test',
            'description' => 'Izin uji ubah kuota',
        ]);

        $role->permissions()->sync([$readQuota->id, $updateQuota->id]);

        $this->user->roles()->attach($role);
        $this->user->update(['is_active' => true]);

        $this->product = Product::create([
            'code' => 'PRD-TEST',
            'name' => 'Produk Uji',
            'sap_model' => 'SAP-TEST',
            'category' => 'Test',
            'pk_capacity' => 1.0,
            'description' => 'Unit pengujian',
            'is_active' => true,
        ]);

        $sharedQuotaAttributes = [
            'government_category' => 'AC 0.5 PK - 2 PK',
            'period_start' => '2025-01-01',
            'period_end' => '2025-12-31',
            'total_allocation' => 1000,
            'forecast_remaining' => 1000,
            'actual_remaining' => 1000,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ];

        $this->quotaPrimary = Quota::create($sharedQuotaAttributes + [
            'quota_number' => 'QT-001',
            'name' => 'Kuota Utama',
        ]);

        $this->quotaSecondary = Quota::create($sharedQuotaAttributes + [
            'quota_number' => 'QT-002',
            'name' => 'Kuota Cadangan',
        ]);
    }

    public function test_index_page_is_accessible(): void
    {
        $this->assertContains(resource_path('views'), config('view.paths'));

        $response = $this->actingAs($this->user)
            ->get(route('admin.product-quotas.index'));

        $response->assertOk();
        $response->assertSee('Mapping Produk');
    }

    public function test_store_creates_primary_mapping_when_none_exists(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('admin.product-quotas.store'), [
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaPrimary->id,
            'priority' => 1,
        ], $this->jsonHeaders());

        $response->assertCreated();

        $this->assertDatabaseHas('product_quota_mappings', [
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaPrimary->id,
            'priority' => 1,
            'is_primary' => true,
        ]);
    }

    public function test_store_with_primary_flag_resets_previous_primary(): void
    {
        ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaPrimary->id,
            'priority' => 1,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.product-quotas.store'), [
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaSecondary->id,
            'priority' => 2,
            'is_primary' => 1,
        ], $this->jsonHeaders());

        $response->assertCreated();

        $this->assertDatabaseHas('product_quota_mappings', [
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaSecondary->id,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('product_quota_mappings', [
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaPrimary->id,
            'is_primary' => false,
        ]);
    }

    public function test_reorder_updates_priorities(): void
    {
        $mappingA = ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaPrimary->id,
            'priority' => 1,
            'is_primary' => true,
        ]);

        $mappingB = ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaSecondary->id,
            'priority' => 2,
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.product-quotas.reorder'), [
            'product_id' => $this->product->id,
            'order' => [$mappingB->id, $mappingA->id],
        ], $this->jsonHeaders());

        $response->assertOk();

        $this->assertDatabaseHas('product_quota_mappings', [
            'id' => $mappingB->id,
            'priority' => 1,
        ]);

        $this->assertDatabaseHas('product_quota_mappings', [
            'id' => $mappingA->id,
            'priority' => 2,
        ]);
    }

    public function test_destroy_promotes_next_mapping_to_primary(): void
    {
        $mappingA = ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaPrimary->id,
            'priority' => 1,
            'is_primary' => true,
        ]);

        $mappingB = ProductQuotaMapping::create([
            'product_id' => $this->product->id,
            'quota_id' => $this->quotaSecondary->id,
            'priority' => 2,
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(
            route('admin.product-quotas.destroy', $mappingA),
            [],
            $this->jsonHeaders()
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('product_quota_mappings', [
            'id' => $mappingA->id,
        ]);

        $this->assertDatabaseHas('product_quota_mappings', [
            'id' => $mappingB->id,
            'is_primary' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'X-CSRF-TOKEN' => Session::token(),
            'Accept' => 'application/json',
        ];
    }
}
