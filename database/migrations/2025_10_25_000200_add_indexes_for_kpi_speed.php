<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_quota')) {
            Schema::table('purchase_order_quota', function (Blueprint $table) {
                $table->index('quota_id', 'po_quota_quota_id_idx');
            });
        }

        if (Schema::hasTable('quota_histories')) {
            Schema::table('quota_histories', function (Blueprint $table) {
                $table->index(['quota_id', 'change_type'], 'quota_histories_quota_change_idx');
                $table->index('occurred_on', 'quota_histories_occurred_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_order_quota')) {
            Schema::table('purchase_order_quota', function (Blueprint $table) {
                $table->dropIndex('po_quota_quota_id_idx');
            });
        }
        if (Schema::hasTable('quota_histories')) {
            Schema::table('quota_histories', function (Blueprint $table) {
                $table->dropIndex('quota_histories_quota_change_idx');
                $table->dropIndex('quota_histories_occurred_idx');
            });
        }
    }
};

