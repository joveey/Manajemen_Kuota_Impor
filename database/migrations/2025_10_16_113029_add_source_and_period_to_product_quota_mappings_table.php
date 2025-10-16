<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_quota_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('product_quota_mappings', 'source')) {
                $table->string('source', 20)->default('auto')->after('is_primary');
            }
            if (!Schema::hasColumn('product_quota_mappings', 'period_key')) {
                $table->string('period_key')->nullable()->after('source');
            }

            // Drop old unique if exists: (product_id, quota_id)
            try {
                $table->dropUnique('product_quota_mappings_product_id_quota_id_unique');
            } catch (\Throwable $e) {
                // ignore if not exists
            }

            // New indexes
            $table->index(['product_id', 'period_key', 'priority'], 'pqm_product_period_priority_idx');
            $table->unique(['product_id', 'quota_id', 'period_key'], 'pqm_product_quota_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_quota_mappings', function (Blueprint $table) {
            // Drop new indexes
            try { $table->dropUnique('pqm_product_quota_period_unique'); } catch (\Throwable $e) {}
            try { $table->dropIndex('pqm_product_period_priority_idx'); } catch (\Throwable $e) {}

            // Restore old unique
            try { $table->unique(['product_id', 'quota_id']); } catch (\Throwable $e) {}

            if (Schema::hasColumn('product_quota_mappings', 'period_key')) {
                $table->dropColumn('period_key');
            }
            if (Schema::hasColumn('product_quota_mappings', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
