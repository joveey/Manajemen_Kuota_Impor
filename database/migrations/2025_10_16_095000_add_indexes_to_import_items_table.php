<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->index(['import_id', 'status', 'row_index'], 'import_items_import_status_row_index');
        });
    }

    public function down(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->dropIndex('import_items_import_status_row_index');
        });
    }
};

