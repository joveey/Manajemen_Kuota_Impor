<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('po_lines', 'line_no')) {
                $table->string('line_no')->nullable()->after('po_header_id');
                $table->index('line_no');
            }
            if (!Schema::hasColumn('po_lines', 'item_desc')) {
                $table->string('item_desc')->nullable()->after('model_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('po_lines', function (Blueprint $table) {
            if (Schema::hasColumn('po_lines', 'line_no')) {
                $table->dropIndex(['line_no']);
                $table->dropColumn('line_no');
            }
            if (Schema::hasColumn('po_lines', 'item_desc')) {
                $table->dropColumn('item_desc');
            }
        });
    }
};

