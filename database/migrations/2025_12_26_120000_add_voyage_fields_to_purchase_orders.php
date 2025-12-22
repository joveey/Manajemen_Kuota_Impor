<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'voyage_bl')) {
                $table->string('voyage_bl', 100)->nullable()->after('sap_order_status');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_etd')) {
                $table->date('voyage_etd')->nullable()->after('voyage_bl');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_eta')) {
                $table->date('voyage_eta')->nullable()->after('voyage_etd');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_factory')) {
                $table->string('voyage_factory', 100)->nullable()->after('voyage_eta');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_status')) {
                $table->string('voyage_status', 50)->nullable()->after('voyage_factory');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_issue_date')) {
                $table->date('voyage_issue_date')->nullable()->after('voyage_status');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_expired_date')) {
                $table->date('voyage_expired_date')->nullable()->after('voyage_issue_date');
            }
            if (!Schema::hasColumn('purchase_orders', 'voyage_remark')) {
                $table->text('voyage_remark')->nullable()->after('voyage_expired_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'voyage_bl',
                'voyage_etd',
                'voyage_eta',
                'voyage_factory',
                'voyage_status',
                'voyage_issue_date',
                'voyage_expired_date',
                'voyage_remark',
            ] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
