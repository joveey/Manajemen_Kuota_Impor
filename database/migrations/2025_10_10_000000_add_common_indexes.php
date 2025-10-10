<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active', 'users_is_active_index');
            $table->index('last_login_at', 'users_last_login_at_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('period', 'po_period_index');
            $table->index('status', 'po_status_index');
            $table->index('product_id', 'po_product_id_index');
            $table->index('quota_id', 'po_quota_id_index');
            $table->index('order_date', 'po_order_date_index');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index('status', 'shipments_status_index');
            $table->index('ship_date', 'shipments_ship_date_index');
            $table->index('purchase_order_id', 'shipments_po_id_index');
        });

        Schema::table('quotas', function (Blueprint $table) {
            $table->index('status', 'quotas_status_index');
            $table->index('is_active', 'quotas_is_active_index');
            $table->index('period_start', 'quotas_period_start_index');
            $table->index('period_end', 'quotas_period_end_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_active_index');
            $table->dropIndex('users_last_login_at_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('po_period_index');
            $table->dropIndex('po_status_index');
            $table->dropIndex('po_product_id_index');
            $table->dropIndex('po_quota_id_index');
            $table->dropIndex('po_order_date_index');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_status_index');
            $table->dropIndex('shipments_ship_date_index');
            $table->dropIndex('shipments_po_id_index');
        });

        Schema::table('quotas', function (Blueprint $table) {
            $table->dropIndex('quotas_status_index');
            $table->dropIndex('quotas_is_active_index');
            $table->dropIndex('quotas_period_start_index');
            $table->dropIndex('quotas_period_end_index');
        });
    }
};

