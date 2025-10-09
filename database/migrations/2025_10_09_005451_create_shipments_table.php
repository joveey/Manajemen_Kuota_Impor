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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->unsignedInteger('quantity_planned');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->date('ship_date')->nullable();
            $table->date('eta_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->enum('status', ['pending', 'in_transit', 'partial', 'delivered', 'cancelled'])->default('pending');
            $table->text('detail')->nullable();
            $table->boolean('auto_generated')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
