<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sap_purchase_order_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('sap_purchase_order_allocations', 'allocations')) {
                $table->json('allocations')->nullable();
            }
            if (!Schema::hasColumn('sap_purchase_order_allocations', 'period_key')) {
                $table->string('period_key', 7)->nullable()->after('target_qty');
                $table->index('period_key');
            }
            if (!Schema::hasColumn('sap_purchase_order_allocations', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('period_key');
                $table->index('is_active');
            }
            if (!Schema::hasColumn('sap_purchase_order_allocations', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sap_purchase_order_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('sap_purchase_order_allocations', 'allocations')) {
                $table->dropColumn('allocations');
            }
            if (Schema::hasColumn('sap_purchase_order_allocations', 'period_key')) {
                $table->dropIndex(['period_key']);
                $table->dropColumn('period_key');
            }
            if (Schema::hasColumn('sap_purchase_order_allocations', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('sap_purchase_order_allocations', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }
        });
    }
};
