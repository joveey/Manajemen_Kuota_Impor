<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_headers', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->date('po_date');
            $table->string('supplier');
            $table->string('currency')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_headers');
    }
};

