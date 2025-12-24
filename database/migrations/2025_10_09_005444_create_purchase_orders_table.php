<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop legacy remnants if the table already exists (self-healing when migrate:fresh fails midway)
        if (Schema::hasTable('purchase_orders')) {
            try {
                DB::statement("
                    IF EXISTS (
                        SELECT 1 FROM sys.indexes
                        WHERE name = 'purchase_orders_po_doc_unique'
                          AND object_id = OBJECT_ID('dbo.purchase_orders')
                    )
                    DROP INDEX [purchase_orders_po_doc_unique] ON [dbo].[purchase_orders]
                ");
            } catch (\Throwable $e) {
                // ignore to keep migration idempotent
            }

            Schema::drop('purchase_orders');
        }

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence_number')->nullable();
            $table->string('period')->nullable();
            $table->string('po_doc')->nullable(false);
            $table->string('line_no')->nullable(false);
            $table->unique(['po_doc', 'line_no'], 'purchase_orders_po_doc_line_unique');
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
