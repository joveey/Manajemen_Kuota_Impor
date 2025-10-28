<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hs_code_pk_mappings')) {
            return;
        }

        Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('hs_code_pk_mappings', 'period_key')) {
                $table->string('period_key', 10)->default('')->after('hs_code');
            }
        });

        // Drop old unique on hs_code if it exists, then add composite unique (hs_code, period_key)
        try {
            Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
                $table->dropUnique('hs_code_pk_mappings_hs_code_unique');
            });
        } catch (\Throwable $e) {
            // ignore if index does not exist
        }

        Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
            // Ensure supporting indexes
            if (!Schema::hasColumn('hs_code_pk_mappings', 'period_key')) {
                // Safety: if earlier block failed, bail
                return;
            }
            $table->unique(['hs_code', 'period_key'], 'hs_code_period_unique');
            $table->index('period_key');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('hs_code_pk_mappings')) {
            return;
        }

        // Drop composite unique and period_key index, restore unique on hs_code, then drop column
        try {
            Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
                $table->dropUnique('hs_code_period_unique');
                $table->dropIndex(['period_key']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('hs_code_pk_mappings', 'period_key')) {
                $table->dropColumn('period_key');
            }
            $table->unique('hs_code');
        });
    }
};

