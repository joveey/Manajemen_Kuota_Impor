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
                try {
                    $table->dropUnique('gr_receipts_unique_key');
                } catch (\Throwable $e) {
                    // ignore if the unique index does not exist
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gr_receipts')) {
            Schema::table('gr_receipts', function (Blueprint $table) {
                try {
                    $table->unique(['po_no','line_no','receive_date','qty'], 'gr_receipts_unique_key');
                } catch (\Throwable $e) {
                    // ignore if it already exists
                }
            });
        }
    }
};

