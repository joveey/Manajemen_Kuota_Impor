<?php
// database/migrations/2024_01_01_000001_create_quota_management_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya buat tabel yang tidak memiliki migrasi khusus di batch berikutnya.
        // Tabel lain (products, quotas, purchase_orders, shipments, quota_histories)
        // dibuat oleh migrasi terpisah ber-timestamp 2025_10_09_*. Menjaga ini
        // mencegah duplikasi tabel saat menjalankan ulang migrasi.

        if (!Schema::hasTable('factories')) {
            Schema::create('factories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('address');
                $table->string('country');
                $table->string('contact_person')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('factories');
    }
};
