<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('purchase_order_quota')) {
            Schema::create('purchase_order_quota', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quota_id');
                $table->foreign('quota_id')->references('id')->on('quotas');
                $table->unsignedInteger('allocated_qty');
                $table->timestamps();
                $table->unique(['purchase_order_id','quota_id'], 'po_quota_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_quota');
    }
};
