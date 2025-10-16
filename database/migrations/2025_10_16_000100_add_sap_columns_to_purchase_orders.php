<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'vendor_number')) {
                $table->string('vendor_number', 50)->nullable()->after('sap_reference');
            }

            if (!Schema::hasColumn('purchase_orders', 'vendor_name')) {
                $table->string('vendor_name')->nullable()->after('vendor_number');
            }

            if (!Schema::hasColumn('purchase_orders', 'line_number')) {
                $table->string('line_number', 30)->nullable()->after('vendor_name');
            }

            if (!Schema::hasColumn('purchase_orders', 'item_code')) {
                $table->string('item_code')->nullable()->after('line_number');
            }

            if (!Schema::hasColumn('purchase_orders', 'item_description')) {
                $table->text('item_description')->nullable()->after('item_code');
            }

            if (!Schema::hasColumn('purchase_orders', 'warehouse_code')) {
                $table->string('warehouse_code', 50)->nullable()->after('item_description');
            }

            if (!Schema::hasColumn('purchase_orders', 'warehouse_name')) {
                $table->string('warehouse_name')->nullable()->after('warehouse_code');
            }

            if (!Schema::hasColumn('purchase_orders', 'subinventory_code')) {
                $table->string('subinventory_code', 50)->nullable()->after('warehouse_name');
            }

            if (!Schema::hasColumn('purchase_orders', 'subinventory_name')) {
                $table->string('subinventory_name')->nullable()->after('subinventory_code');
            }

            if (!Schema::hasColumn('purchase_orders', 'warehouse_source')) {
                $table->string('warehouse_source')->nullable()->after('subinventory_name');
            }

            if (!Schema::hasColumn('purchase_orders', 'subinventory_source')) {
                $table->string('subinventory_source')->nullable()->after('warehouse_source');
            }

            if (!Schema::hasColumn('purchase_orders', 'amount')) {
                $table->decimal('amount', 18, 2)->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('purchase_orders', 'category_code')) {
                $table->string('category_code', 50)->nullable()->after('category');
            }

            if (!Schema::hasColumn('purchase_orders', 'material_group')) {
                $table->string('material_group', 100)->nullable()->after('category_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'vendor_number',
                'vendor_name',
                'line_number',
                'item_code',
                'item_description',
                'warehouse_code',
                'warehouse_name',
                'subinventory_code',
                'subinventory_name',
                'warehouse_source',
                'subinventory_source',
                'amount',
                'category_code',
                'material_group',
            ] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
