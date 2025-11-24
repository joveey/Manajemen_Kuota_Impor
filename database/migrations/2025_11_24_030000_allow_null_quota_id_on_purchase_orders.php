<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'quota_id')) {
            if (DB::getDriverName() === 'sqlsrv') {
                // SQL Server requires an explicit ALTER COLUMN to drop NOT NULL
                try {
                    DB::statement('ALTER TABLE purchase_orders ALTER COLUMN quota_id bigint NULL');
                } catch (\Throwable $e) {
                    // swallow to avoid breaking existing deployments
                }
            }
        }
    }

    public function down(): void
    {
        // No down migration: restoring NOT NULL could break existing data.
    }
};
