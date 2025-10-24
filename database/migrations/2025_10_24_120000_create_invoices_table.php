<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('po_no');
            $table->string('line_no');
            $table->string('invoice_no');
            $table->date('invoice_date')->nullable();
            $table->decimal('qty', 18, 2);
            $table->timestamps();

            $table->unique(['po_no','line_no','invoice_no','qty'], 'invoices_unique_key');
            $table->index(['po_no','line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

