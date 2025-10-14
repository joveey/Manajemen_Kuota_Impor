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
        Schema::create('shipment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity_planned_snapshot')->nullable();
            $table->unsignedInteger('quantity_received_snapshot')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'recorded_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_status_logs');
    }
};
