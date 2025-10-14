<?php

namespace Tests\Feature;

use App\Models\Quota;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAutoMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_product_creation_auto_maps_matching_quotas(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'admin')->firstOrFail();
        $user->roles()->attach($role);

        $quota = Quota::create([
            'quota_number' => 'AUTO-01',
            'name' => 'Quota Auto Mapping',
            'government_category' => 'AC 0.5 PK - 2 PK',
            'total_allocation' => 10000,
            'forecast_remaining' => 8000,
            'actual_remaining' => 9000,
            'status' => Quota::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('admin.master-data.store'), [
                'code' => 'PRD-AUTO',
                'name' => 'Produk Auto Mapping',
                'sap_model' => 'SAP-AUTO',
                'category' => 'Testing',
                'pk_capacity' => 1.2,
                'description' => 'Produk untuk menguji auto mapping',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.master-data.index'));

        $this->assertDatabaseHas('products', ['code' => 'PRD-AUTO']);

        $product = \App\Models\Product::where('code', 'PRD-AUTO')->firstOrFail();
        $this->assertTrue($product->quotaMappings()->where('quota_id', $quota->id)->exists());

        $primaryMapping = $product->quotaMappings()->where('is_primary', true)->first();
        $this->assertNotNull($primaryMapping);
        $this->assertEquals($quota->id, $primaryMapping->quota_id);
    }
}
