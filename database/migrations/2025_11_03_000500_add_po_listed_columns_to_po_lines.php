<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('po_lines')) {
            Schema::table('po_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('po_lines', 'qty_to_invoice')) {
                    $table->decimal('qty_to_invoice', 18, 2)->nullable()->after('qty_received');
                }
                if (!Schema::hasColumn('po_lines', 'qty_to_deliver')) {
                    $table->decimal('qty_to_deliver', 18, 2)->nullable()->after('qty_to_invoice');
                }
                if (!Schema::hasColumn('po_lines', 'storage_location')) {
                    $table->string('storage_location', 100)->nullable()->after('uom');
                }
                if (!Schema::hasColumn('po_lines', 'needs_reallocation')) {
                    $table->boolean('needs_reallocation')->default(false)->after('validation_notes');
                }
                // Composite index for idempotent line upsert per header + line number
                try {
                    $table->index(['po_header_id','line_no'], 'po_lines_header_line_idx');
                } catch (\Throwable $e) {
                    // ignore if index already exists
                }
            });
        }

        // Note: unique index on purchase_orders.po_number is created by earlier migration
        // (2025_10_09_005444_create_purchase_orders_table.php). We intentionally do not
        // duplicate that here to keep migrations tidy and idempotent.
    }

    public function down(): void
    {
        if (Schema::hasTable('po_lines')) {
            Schema::table('po_lines', function (Blueprint $table) {
                foreach (['qty_to_invoice','qty_to_deliver','storage_location'] as $col) {
                    if (Schema::hasColumn('po_lines', $col)) {
                        $table->dropColumn($col);
                    }
                }
                try { $table->dropIndex('po_lines_header_line_idx'); } catch (\Throwable $e) {}
            });
        }
    }
};
