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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence_number')->nullable();
            $table->string('period')->nullable();
            $table->string('po_number')->unique();
            $table->string('sap_reference')->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quota_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_shipped')->default(0);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->date('order_date');
            $table->string('pgi_branch')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('status_po_display')->nullable();
            $table->string('truck')->nullable();
            $table->string('moq')->nullable();
            $table->string('category')->nullable();
            $table->string('plant_name');
            $table->text('plant_detail');
            $table->enum('status', ['draft', 'ordered', 'in_transit', 'partial', 'completed', 'cancelled'])->default('ordered');
            $table->timestamp('forecast_deducted_at')->nullable();
            $table->timestamp('actual_completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
