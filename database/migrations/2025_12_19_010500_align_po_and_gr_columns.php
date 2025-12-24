<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Align purchase_orders columns to the new naming convention
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach ([
                    ['po_doc', 'string', 50],
                    ['created_date', 'date', null],
                    ['vendor_no', 'string', 50],
                    ['vendor_name', 'string', 255],
                    ['line_no', 'string', 30],
                    ['item_code', 'string', 100],
                    ['item_desc', 'text', null],
                    ['wh_code', 'string', 50],
                    ['wh_name', 'string', 255],
                    ['subinv_code', 'string', 50],
                    ['subinv_name', 'string', 255],
                    ['wh_source', 'string', 100],
                    ['subinv_source', 'string', 100],
                    ['qty', 'decimal', '18,2'],
                    ['cat_po', 'string', 50],
                    ['cat_desc', 'string', 255],
                    ['mat_grp', 'string', 100],
                ] as [$col, $type, $len]) {
                    if (!Schema::hasColumn('purchase_orders', $col)) {
                        switch ($type) {
                            case 'decimal':
                                [$precision, $scale] = array_map('intval', explode(',', $len));
                                $table->decimal($col, $precision, $scale)->nullable();
                                break;
                            case 'date':
                                $table->date($col)->nullable();
                                break;
                            case 'text':
                                $table->text($col)->nullable();
                                break;
                            default:
                                $table->string($col, $len ?? 255)->nullable();
                        }
                    }
                }
            });

            // Backfill new columns from legacy ones when available
            try {
                if (Schema::hasColumn('purchase_orders', 'po_number')) {
                    DB::statement("UPDATE purchase_orders SET po_doc = COALESCE(po_doc, po_number)");
                }
                if (Schema::hasColumn('purchase_orders', 'order_date')) {
                    DB::statement("UPDATE purchase_orders SET created_date = COALESCE(created_date, order_date)");
                } else {
                    DB::statement("UPDATE purchase_orders SET created_date = COALESCE(created_date, created_at)");
                }
                if (Schema::hasColumn('purchase_orders', 'vendor_number')) {
                    DB::statement("UPDATE purchase_orders SET vendor_no = COALESCE(vendor_no, vendor_number)");
                }
                if (Schema::hasColumn('purchase_orders', 'line_number')) {
                    DB::statement("UPDATE purchase_orders SET line_no = COALESCE(line_no, line_number)");
                }
                if (Schema::hasColumn('purchase_orders', 'item_description')) {
                    DB::statement("UPDATE purchase_orders SET item_desc = COALESCE(item_desc, item_description)");
                }
                if (Schema::hasColumn('purchase_orders', 'warehouse_code')) {
                    DB::statement("UPDATE purchase_orders SET wh_code = COALESCE(wh_code, warehouse_code)");
                }
                if (Schema::hasColumn('purchase_orders', 'warehouse_name')) {
                    DB::statement("UPDATE purchase_orders SET wh_name = COALESCE(wh_name, warehouse_name)");
                }
                if (Schema::hasColumn('purchase_orders', 'subinventory_code')) {
                    DB::statement("UPDATE purchase_orders SET subinv_code = COALESCE(subinv_code, subinventory_code)");
                }
                if (Schema::hasColumn('purchase_orders', 'subinventory_name')) {
                    DB::statement("UPDATE purchase_orders SET subinv_name = COALESCE(subinv_name, subinventory_name)");
                }
                if (Schema::hasColumn('purchase_orders', 'warehouse_source')) {
                    DB::statement("UPDATE purchase_orders SET wh_source = COALESCE(wh_source, warehouse_source)");
                }
                if (Schema::hasColumn('purchase_orders', 'subinventory_source')) {
                    DB::statement("UPDATE purchase_orders SET subinv_source = COALESCE(subinv_source, subinventory_source)");
                }
                if (Schema::hasColumn('purchase_orders', 'quantity')) {
                    DB::statement("UPDATE purchase_orders SET qty = COALESCE(qty, CAST(quantity AS decimal(18,2)))");
                }
                if (Schema::hasColumn('purchase_orders', 'category_code')) {
                    DB::statement("UPDATE purchase_orders SET cat_po = COALESCE(cat_po, category_code)");
                }
                if (Schema::hasColumn('purchase_orders', 'category')) {
                    DB::statement("UPDATE purchase_orders SET cat_desc = COALESCE(cat_desc, category)");
                }
                if (Schema::hasColumn('purchase_orders', 'material_group')) {
                    DB::statement("UPDATE purchase_orders SET mat_grp = COALESCE(mat_grp, material_group)");
                }
            } catch (\Throwable $e) {
                // Ignore backfill errors; continues with schema changes
            }

            // Drop legacy unique index before removing the old column
            $driver = DB::connection()->getDriverName();
            $candidates = [
                'purchase_orders_po_number_unique',
                'po_number_unique',
                'purchase_orders_po_number_key',
                'purchase_orders_po_doc_unique',
            ];
            foreach ($candidates as $idx) {
                try {
                    if ($driver === 'sqlsrv') {
                        DB::statement("
                            IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = '$idx' AND object_id = OBJECT_ID('purchase_orders'))
                            DROP INDEX [$idx] ON [purchase_orders];
                        ");
                    } else {
                        DB::statement("DROP INDEX IF EXISTS $idx ON purchase_orders");
                    }
                } catch (\Throwable $e) {
                    // ignore if it truly does not exist
                }
            }

            // Drop indexes that depend on legacy columns before dropping them
            foreach (['po_order_date_index'] as $idx) {
                try {
                    if ($driver === 'sqlsrv') {
                        DB::statement("
                            IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = '$idx' AND object_id = OBJECT_ID('purchase_orders'))
                            DROP INDEX [$idx] ON [purchase_orders];
                        ");
                    } else {
                        DB::statement("DROP INDEX IF EXISTS $idx ON purchase_orders");
                    }
                } catch (\Throwable $e) {}
            }

            // Remove legacy columns to avoid double naming
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach ([
                    'po_number',
                    'order_date',
                    'vendor_number',
                    'line_number',
                    'item_description',
                    'warehouse_code',
                    'warehouse_name',
                    'warehouse_source',
                    'subinventory_code',
                    'subinventory_name',
                    'subinventory_source',
                    'quantity',
                    'category',
                    'category_code',
                    'material_group',
                ] as $col) {
                    if (Schema::hasColumn('purchase_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Align gr_receipts columns
        if (Schema::hasTable('gr_receipts')) {
            Schema::table('gr_receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('gr_receipts', 'cat_po_desc')) {
                    $table->string('cat_po_desc', 255)->nullable();
                }
                if (!Schema::hasColumn('gr_receipts', 'mat_doc')) {
                    $table->string('mat_doc', 50)->nullable();
                }
                if (!Schema::hasColumn('gr_receipts', 'cat')) {
                    $table->string('cat', 50)->nullable();
                }
                if (!Schema::hasColumn('gr_receipts', 'cat_desc')) {
                    $table->string('cat_desc', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            // Restore legacy columns as nullable to allow rollback
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach ([
                    ['po_number', 'string', 50],
                    ['order_date', 'date', null],
                    ['vendor_number', 'string', 50],
                    ['line_number', 'string', 30],
                    ['item_description', 'text', null],
                    ['warehouse_code', 'string', 50],
                    ['warehouse_name', 'string', 255],
                    ['warehouse_source', 'string', 100],
                    ['subinventory_code', 'string', 50],
                    ['subinventory_name', 'string', 255],
                    ['subinventory_source', 'string', 100],
                    ['quantity', 'integer', null],
                    ['category', 'string', 255],
                    ['category_code', 'string', 50],
                    ['material_group', 'string', 100],
                ] as [$col, $type, $len]) {
                    if (!Schema::hasColumn('purchase_orders', $col)) {
                        switch ($type) {
                            case 'integer':
                                $table->integer($col)->nullable();
                                break;
                            case 'date':
                                $table->date($col)->nullable();
                                break;
                            case 'text':
                                $table->text($col)->nullable();
                                break;
                            default:
                                $table->string($col, $len ?? 255)->nullable();
                        }
                    }
                }
            });

            // Backfill legacy columns from the new ones
            try {
                DB::statement("UPDATE purchase_orders SET po_number = COALESCE(po_number, po_doc)");
                DB::statement("UPDATE purchase_orders SET order_date = COALESCE(order_date, created_date)");
                DB::statement("UPDATE purchase_orders SET vendor_number = COALESCE(vendor_number, vendor_no)");
                DB::statement("UPDATE purchase_orders SET line_number = COALESCE(line_number, line_no)");
                DB::statement("UPDATE purchase_orders SET item_description = COALESCE(item_description, item_desc)");
                DB::statement("UPDATE purchase_orders SET warehouse_code = COALESCE(warehouse_code, wh_code)");
                DB::statement("UPDATE purchase_orders SET warehouse_name = COALESCE(warehouse_name, wh_name)");
                DB::statement("UPDATE purchase_orders SET warehouse_source = COALESCE(warehouse_source, wh_source)");
                DB::statement("UPDATE purchase_orders SET subinventory_code = COALESCE(subinventory_code, subinv_code)");
                DB::statement("UPDATE purchase_orders SET subinventory_name = COALESCE(subinventory_name, subinv_name)");
                DB::statement("UPDATE purchase_orders SET subinventory_source = COALESCE(subinventory_source, subinv_source)");
                DB::statement("UPDATE purchase_orders SET quantity = COALESCE(quantity, qty)");
                DB::statement("UPDATE purchase_orders SET category = COALESCE(category, cat_desc)");
                DB::statement("UPDATE purchase_orders SET category_code = COALESCE(category_code, cat_po)");
                DB::statement("UPDATE purchase_orders SET material_group = COALESCE(material_group, mat_grp)");
            } catch (\Throwable $e) {
            }

            // Drop the new columns
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach (['po_doc','created_date','vendor_no','line_no','item_desc','wh_code','wh_name','subinv_code','subinv_name','wh_source','subinv_source','qty','cat_po','cat_desc','mat_grp'] as $col) {
                    if (Schema::hasColumn('purchase_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });

            // Drop unique on po_doc with driver-safe guards
            $driver = DB::connection()->getDriverName();
            $idx = 'purchase_orders_po_doc_unique';
            try {
                if ($driver === 'sqlsrv') {
                    DB::statement("
                        IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = '$idx' AND object_id = OBJECT_ID('purchase_orders'))
                        DROP INDEX [$idx] ON [purchase_orders];
                    ");
                } else {
                    DB::statement("DROP INDEX IF EXISTS $idx ON purchase_orders");
                }
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('gr_receipts')) {
            Schema::table('gr_receipts', function (Blueprint $table) {
                foreach (['cat_po_desc','mat_doc','cat','cat_desc'] as $col) {
                    if (Schema::hasColumn('gr_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
