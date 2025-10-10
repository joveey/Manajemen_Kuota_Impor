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
        if (!Schema::hasTable('purchase_orders')) {
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
        } else {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'sequence_number')) {
                    $table->unsignedBigInteger('sequence_number')->nullable();
                }
                if (!Schema::hasColumn('purchase_orders', 'period')) {
                    $table->string('period')->nullable();
                }
                if (!Schema::hasColumn('purchase_orders', 'sap_reference')) {
                    $table->string('sap_reference')->nullable();
                }
                if (!Schema::hasColumn('purchase_orders', 'quantity_shipped')) {
                    $table->unsignedInteger('quantity_shipped')->default(0);
                }
                if (!Schema::hasColumn('purchase_orders', 'quantity_received')) {
                    $table->unsignedInteger('quantity_received')->default(0);
                }
                if (Schema::hasColumn('purchase_orders', 'po_date') && !Schema::hasColumn('purchase_orders', 'order_date')) {
                    // Jika skema lama memakai 'po_date', biarkan tetap; tambahkan 'order_date' bila belum ada
                    $table->date('order_date')->nullable();
                } elseif (!Schema::hasColumn('purchase_orders', 'order_date')) {
                    $table->date('order_date');
                }
                foreach (['pgi_branch','customer_name','pic_name','status_po_display','truck','moq','category'] as $col) {
                    if (!Schema::hasColumn('purchase_orders', $col)) {
                        $table->string($col)->nullable();
                    }
                }
                if (!Schema::hasColumn('purchase_orders', 'plant_name')) {
                    $table->string('plant_name')->nullable();
                }
                if (!Schema::hasColumn('purchase_orders', 'plant_detail')) {
                    $table->text('plant_detail')->nullable();
                }
                if (Schema::hasColumn('purchase_orders', 'status')) {
                    $table->dropColumn('status');
                }
                if (!Schema::hasColumn('purchase_orders', 'status')) {
                    $table->enum('status', ['draft', 'ordered', 'in_transit', 'partial', 'completed', 'cancelled'])->default('ordered');
                }
                if (!Schema::hasColumn('purchase_orders', 'forecast_deducted_at')) {
                    $table->timestamp('forecast_deducted_at')->nullable();
                }
                if (!Schema::hasColumn('purchase_orders', 'actual_completed_at')) {
                    $table->timestamp('actual_completed_at')->nullable();
                }
                if (!Schema::hasColumn('purchase_orders', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('purchase_orders', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach (['sequence_number','period','sap_reference','quantity_shipped','quantity_received','order_date','pgi_branch','customer_name','pic_name','status_po_display','truck','moq','category','plant_name','plant_detail','forecast_deducted_at','actual_completed_at','created_by'] as $col) {
                    if (Schema::hasColumn('purchase_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('purchase_orders', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
