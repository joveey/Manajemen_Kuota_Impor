<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_header_id')->constrained('po_headers')->cascadeOnDelete();
            $table->string('model_code')->index();
            // Reference to HS master: adapt to table available
            if (Schema::hasTable('hs_code_pk_mappings')) {
                $table->foreignId('hs_code_id')->constrained('hs_code_pk_mappings');
            } else {
                // fallback: allow nullable until hs master exists
                $table->unsignedBigInteger('hs_code_id')->nullable();
            }
            $table->decimal('qty_ordered', 18, 2);
            $table->decimal('qty_received', 18, 2)->default(0);
            $table->string('uom')->nullable();
            $table->date('eta_date')->nullable();
            $table->enum('validation_status', ['ok','warn','error'])->default('ok');
            $table->text('validation_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_lines');
    }
};

