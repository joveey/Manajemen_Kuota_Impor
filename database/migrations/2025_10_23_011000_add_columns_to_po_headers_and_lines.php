<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // po_headers: add vendor_number
        if (Schema::hasTable('po_headers')) {
            Schema::table('po_headers', function (Blueprint $table) {
                if (!Schema::hasColumn('po_headers', 'vendor_number')) {
                    $table->string('vendor_number', 50)->nullable()->after('po_date');
                }
            });
        }

        // po_lines: add operational columns
        if (Schema::hasTable('po_lines')) {
            Schema::table('po_lines', function (Blueprint $table) {
                foreach ([
                    ['warehouse_code', 'string', 50],
                    ['warehouse_name', 'string', 255],
                    ['warehouse_source', 'string', 50],
                    ['subinventory_code', 'string', 50],
                    ['subinventory_name', 'string', 255],
                    ['subinventory_source', 'string', 50],
                ] as $col) {
                    if (!Schema::hasColumn('po_lines', $col[0])) {
                        $table->string($col[0], $col[2] ?? 255)->nullable();
                    }
                }

                if (!Schema::hasColumn('po_lines', 'amount')) {
                    $table->decimal('amount', 18, 2)->nullable();
                }
                if (!Schema::hasColumn('po_lines', 'category_code')) {
                    $table->string('category_code', 50)->nullable();
                }
                if (!Schema::hasColumn('po_lines', 'category')) {
                    $table->string('category')->nullable();
                }
                if (!Schema::hasColumn('po_lines', 'material_group')) {
                    $table->string('material_group', 100)->nullable();
                }
                if (!Schema::hasColumn('po_lines', 'sap_order_status')) {
                    $table->string('sap_order_status', 100)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('po_headers')) {
            Schema::table('po_headers', function (Blueprint $table) {
                if (Schema::hasColumn('po_headers', 'vendor_number')) {
                    $table->dropColumn('vendor_number');
                }
            });
        }

        if (Schema::hasTable('po_lines')) {
            Schema::table('po_lines', function (Blueprint $table) {
                foreach ([
                    'warehouse_code','warehouse_name','warehouse_source',
                    'subinventory_code','subinventory_name','subinventory_source',
                    'amount','category_code','category','material_group','sap_order_status'
                ] as $col) {
                    if (Schema::hasColumn('po_lines', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

