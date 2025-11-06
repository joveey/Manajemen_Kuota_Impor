<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('quotas')) {
            return;
        }
        Schema::table('quotas', function (Blueprint $table) {
            try {
                $table->dropUnique(['quota_number']);
            } catch (\Throwable $e) {
                try { $table->dropUnique('quotas_quota_number_unique'); } catch (\Throwable $e2) {}
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('quotas')) {
            return;
        }
        Schema::table('quotas', function (Blueprint $table) {
            try { $table->unique('quota_number'); } catch (\Throwable $e) {}
        });
    }
};

