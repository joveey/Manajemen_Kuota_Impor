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
        Schema::create('import_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->json('raw_json')->nullable();
            $table->json('normalized_json')->nullable();
            $table->json('errors_json')->nullable();
            $table->enum('status', ['raw','normalized','error'])->default('raw');
            $table->timestamps();

            $table->index(['import_id', 'row_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};

