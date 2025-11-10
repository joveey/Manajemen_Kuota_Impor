<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('po_line_voyage_splits')) {
            Schema::create('po_line_voyage_splits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('po_line_id')->constrained('po_lines')->cascadeOnDelete();
                $table->unsignedInteger('seq_no')->default(1);
                $table->decimal('qty', 18, 2)->default(0);
                $table->string('voyage_bl', 100)->nullable();
                $table->date('voyage_etd')->nullable();
                $table->date('voyage_eta')->nullable();
                $table->string('voyage_factory', 100)->nullable();
                $table->string('voyage_status', 50)->nullable();
                $table->text('voyage_remark')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['po_line_id','seq_no']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('po_line_voyage_splits');
    }
};

