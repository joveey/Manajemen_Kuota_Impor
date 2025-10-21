<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'hs_code')) {
                $table->string('hs_code')->nullable()->after('category');
                $table->index('hs_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'hs_code')) {
                $table->dropIndex(['hs_code']);
                $table->dropColumn('hs_code');
            }
        });
    }
};

