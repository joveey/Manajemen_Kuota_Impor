<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gr_receipts')) {
            Schema::table('gr_receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('gr_receipts', 'gr_unique')) {
                    $table->string('gr_unique', 64)->nullable()->after('qty');
                }
                if (!Schema::hasColumn('gr_receipts', 'cat_po')) {
                    $table->string('cat_po', 20)->nullable()->after('gr_unique');
                }
                foreach ([
                    ['item_name', 'string', 255],
                    ['vendor_code', 'string', 50],
                    ['vendor_name', 'string', 255],
                    ['wh_code', 'string', 50],
                    ['wh_name', 'string', 255],
                    ['sloc_code', 'string', 50],
                    ['sloc_name', 'string', 255],
                    ['currency', 'string', 20],
                ] as $col) {
                    if (!Schema::hasColumn('gr_receipts', $col[0])) {
                        $table->string($col[0], $col[2])->nullable();
                    }
                }
                if (!Schema::hasColumn('gr_receipts', 'amount')) {
                    $table->decimal('amount', 18, 2)->nullable();
                }
                if (!Schema::hasColumn('gr_receipts', 'deliv_amount')) {
                    $table->decimal('deliv_amount', 18, 2)->nullable();
                }
            });
            // Add unique index after column exists (safe if already exists)
            Schema::table('gr_receipts', function (Blueprint $table) {
                try {
                    $table->unique('gr_unique', 'gr_receipts_gr_unique_unique');
                } catch (\Throwable $e) {
                    // ignore if index already exists or connection doesn't support this check
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gr_receipts')) {
            Schema::table('gr_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('gr_receipts', 'gr_unique')) {
                    try { $table->dropUnique('gr_receipts_gr_unique_unique'); } catch (\Throwable $e) {}
                    $table->dropColumn('gr_unique');
                }
                foreach (['cat_po','item_name','vendor_code','vendor_name','wh_code','wh_name','sloc_code','sloc_name','currency','amount','deliv_amount'] as $col) {
                    if (Schema::hasColumn('gr_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
