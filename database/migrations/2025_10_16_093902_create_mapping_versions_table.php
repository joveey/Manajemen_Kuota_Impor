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
        Schema::create('mapping_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->string('period_key');
            $table->unsignedInteger('version');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['type', 'period_key', 'version'], 'mapping_versions_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mapping_versions');
    }
};

