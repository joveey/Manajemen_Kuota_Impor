<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            if (!Schema::hasColumn('quotas', 'min_pk')) {
                $table->decimal('min_pk', 4, 2)->nullable()->after('government_category');
            }
            if (!Schema::hasColumn('quotas', 'max_pk')) {
                $table->decimal('max_pk', 4, 2)->nullable()->after('min_pk');
            }
            if (!Schema::hasColumn('quotas', 'is_min_inclusive')) {
                $table->boolean('is_min_inclusive')->default(true)->after('max_pk');
            }
            if (!Schema::hasColumn('quotas', 'is_max_inclusive')) {
                $table->boolean('is_max_inclusive')->default(true)->after('is_min_inclusive');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            if (Schema::hasColumn('quotas', 'min_pk')) {
                $table->dropColumn('min_pk');
            }
            if (Schema::hasColumn('quotas', 'max_pk')) {
                $table->dropColumn('max_pk');
            }
            if (Schema::hasColumn('quotas', 'is_min_inclusive')) {
                $table->dropColumn('is_min_inclusive');
            }
            if (Schema::hasColumn('quotas', 'is_max_inclusive')) {
                $table->dropColumn('is_max_inclusive');
            }
        });
    }
};
