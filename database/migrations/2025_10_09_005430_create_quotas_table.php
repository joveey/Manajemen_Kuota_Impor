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
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->string('quota_number')->unique();
            $table->string('name');
            $table->string('government_category');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('total_allocation');
            $table->unsignedInteger('forecast_remaining');
            $table->unsignedInteger('actual_remaining');
            $table->enum('status', ['available', 'limited', 'depleted'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->string('source_document')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotas');
    }
};
