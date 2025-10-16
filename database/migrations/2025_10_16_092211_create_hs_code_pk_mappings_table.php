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
        Schema::create('hs_code_pk_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hs_code')->unique();
            $table->decimal('pk_capacity', 4, 2);
            $table->timestamps();

            $table->index('hs_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hs_code_pk_mappings');
    }
};
