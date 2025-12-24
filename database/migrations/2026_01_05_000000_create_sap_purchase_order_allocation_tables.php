<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sap_purchase_order_allocations')) {
            Schema::create('sap_purchase_order_allocations', function (Blueprint $table) {
                $table->id();
                $table->string('po_doc', 50);
                $table->string('po_line_no', 30);
                $table->string('po_line_no_raw')->nullable();
                $table->string('item_code', 100)->nullable();
                $table->string('vendor_no', 50)->nullable();
                $table->string('vendor_name', 255)->nullable();
                $table->date('order_date')->nullable();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('target_qty', 18, 2)->default(0);
                $table->json('allocations')->nullable();
                $table->string('period_key', 7)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['po_doc', 'po_line_no'], 'sap_po_allocations_po_line_unique');
                $table->index('period_key');
                $table->index('is_active');
            });
        } else {
            Schema::table('sap_purchase_order_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('sap_purchase_order_allocations', 'allocations')) {
                    $table->json('allocations')->nullable();
                }
                if (!Schema::hasColumn('sap_purchase_order_allocations', 'period_key')) {
                    $table->string('period_key', 7)->nullable()->after('target_qty');
                    $table->index('period_key');
                }
                if (!Schema::hasColumn('sap_purchase_order_allocations', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('period_key');
                    $table->index('is_active');
                }
                if (!Schema::hasColumn('sap_purchase_order_allocations', 'last_seen_at')) {
                    $table->timestamp('last_seen_at')->nullable()->after('is_active');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_purchase_order_allocations');
    }
};
