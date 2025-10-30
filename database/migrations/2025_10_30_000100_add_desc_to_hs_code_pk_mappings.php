<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hs_code_pk_mappings')) { return; }
        Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('hs_code_pk_mappings', 'desc')) {
                $table->string('desc', 100)->default('')->after('pk_capacity');
                $table->index('desc');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('hs_code_pk_mappings')) { return; }
        Schema::table('hs_code_pk_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('hs_code_pk_mappings', 'desc')) {
                $table->dropIndex(['desc']);
                $table->dropColumn('desc');
            }
        });
    }
};

