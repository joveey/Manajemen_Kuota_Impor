<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            // QuotaSystemSeeder::class, // aktifkan jika ini master sistem (bukan dummy)
            // SampleQuotaDataSeeder::class,   // DISABLED (dummy)
            // PurchaseOrderSapSeeder::class,  // DISABLED (dummy)
        ]);
    }
}
