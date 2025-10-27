<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('po_lines') && !Schema::hasColumn('po_lines','forecast_allocated_at')) {
            Schema::table('po_lines', function (Blueprint $table) {
                $table->timestamp('forecast_allocated_at')->nullable()->after('qty_received');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('po_lines') && Schema::hasColumn('po_lines','forecast_allocated_at')) {
            Schema::table('po_lines', function (Blueprint $table) {
                $table->dropColumn('forecast_allocated_at');
            });
        }
    }
};

