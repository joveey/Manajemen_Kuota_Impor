<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'quota_id')) {
            try {
                DB::statement('ALTER TABLE purchase_orders ALTER COLUMN quota_id DROP NOT NULL');
            } catch (\Throwable $e) {
                // ignore if DB driver does not support this form; alternative paths can be added if needed
            }
        }
    }

    public function down(): void
    {
        // no-op: making it NOT NULL again could fail if data already contains nulls
    }
};

