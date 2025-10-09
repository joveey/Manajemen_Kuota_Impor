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
        Schema::create('quota_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quota_id')->constrained()->cascadeOnDelete();
            $table->enum('change_type', [
                'forecast_decrease',
                'forecast_increase',
                'actual_decrease',
                'actual_increase',
                'switch_over',
                'manual_adjustment'
            ]);
            $table->integer('quantity_change');
            $table->date('occurred_on');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_histories');
    }
};
