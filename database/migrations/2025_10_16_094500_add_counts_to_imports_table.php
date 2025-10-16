<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            if (!Schema::hasColumn('imports', 'total_rows')) {
                $table->unsignedInteger('total_rows')->default(0)->after('notes');
            }
            if (!Schema::hasColumn('imports', 'valid_rows')) {
                $table->unsignedInteger('valid_rows')->default(0)->after('total_rows');
            }
            if (!Schema::hasColumn('imports', 'error_rows')) {
                $table->unsignedInteger('error_rows')->default(0)->after('valid_rows');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            if (Schema::hasColumn('imports', 'total_rows')) {
                $table->dropColumn('total_rows');
            }
            if (Schema::hasColumn('imports', 'valid_rows')) {
                $table->dropColumn('valid_rows');
            }
            if (Schema::hasColumn('imports', 'error_rows')) {
                $table->dropColumn('error_rows');
            }
        });
    }
};

