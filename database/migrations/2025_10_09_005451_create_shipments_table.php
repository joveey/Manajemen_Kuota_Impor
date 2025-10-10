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
        if (!Schema::hasTable('shipments')) {
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
        } else {
            Schema::table('shipments', function (Blueprint $table) {
                if (!Schema::hasColumn('shipments', 'parent_shipment_id')) {
                    $table->foreignId('parent_shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                }
                if (!Schema::hasColumn('shipments', 'quantity_planned')) {
                    $table->unsignedInteger('quantity_planned')->default(0);
                }
                if (!Schema::hasColumn('shipments', 'quantity_received')) {
                    $table->unsignedInteger('quantity_received')->default(0);
                }
                if (Schema::hasColumn('shipments', 'shipment_date') && !Schema::hasColumn('shipments', 'ship_date')) {
                    $table->date('ship_date')->nullable();
                } elseif (!Schema::hasColumn('shipments', 'ship_date')) {
                    $table->date('ship_date')->nullable();
                }
                if (Schema::hasColumn('shipments', 'estimated_arrival') && !Schema::hasColumn('shipments', 'eta_date')) {
                    $table->date('eta_date')->nullable();
                } elseif (!Schema::hasColumn('shipments', 'eta_date')) {
                    $table->date('eta_date')->nullable();
                }
                if (Schema::hasColumn('shipments', 'actual_arrival') && !Schema::hasColumn('shipments', 'receipt_date')) {
                    $table->date('receipt_date')->nullable();
                } elseif (!Schema::hasColumn('shipments', 'receipt_date')) {
                    $table->date('receipt_date')->nullable();
                }
                if (Schema::hasColumn('shipments', 'status')) {
                    $table->dropColumn('status');
                }
                if (!Schema::hasColumn('shipments', 'status')) {
                    $table->enum('status', ['pending', 'in_transit', 'partial', 'delivered', 'cancelled'])->default('pending');
                }
                if (!Schema::hasColumn('shipments', 'detail')) {
                    $table->text('detail')->nullable();
                }
                if (!Schema::hasColumn('shipments', 'auto_generated')) {
                    $table->boolean('auto_generated')->default(false);
                }
                if (!Schema::hasColumn('shipments', 'deleted_at')) {
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
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                foreach (['parent_shipment_id','quantity_planned','quantity_received','ship_date','eta_date','receipt_date','detail','auto_generated'] as $col) {
                    if (Schema::hasColumn('shipments', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('shipments', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
