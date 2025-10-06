<?php
// database/migrations/2024_01_01_000001_create_quota_management_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Produk (Master Data)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // PRD-001
            $table->string('name');
            $table->enum('model_type', ['CBU', 'CKD', 'IKD']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabel Kuota
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->string('quota_number')->unique(); // KTA-2024-001
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('government_quantity'); // Qty dari pemerintah
            $table->integer('forecast_quantity'); // Qty forecast
            $table->integer('actual_quantity')->default(0); // Qty aktual terpakai
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['available', 'low', 'exhausted'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabel Pabrik (Factory)
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

        // Tabel Purchase Order
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique(); // PO-2024-001
            $table->foreignId('quota_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('factory_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->date('po_date');
            $table->enum('status', ['created', 'in_shipment', 'completed', 'cancelled'])->default('created');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabel Shipment (Pengiriman)
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number')->unique(); // SHIP-2024-001
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_shipped');
            $table->integer('quantity_received')->default(0);
            $table->date('shipment_date');
            $table->date('estimated_arrival');
            $table->date('actual_arrival')->nullable();
            $table->enum('status', ['new', 'in_transit', 'arrived', 'completed'])->default('new');
            $table->string('vessel_name')->nullable();
            $table->string('port_origin')->nullable();
            $table->string('port_destination')->nullable();
            $table->text('shipping_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabel History untuk tracking
        Schema::create('quota_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quota_id')->constrained()->onDelete('cascade');
            $table->string('action'); // 'created', 'updated', 'po_created', 'shipment_received'
            $table->integer('quantity_change')->default(0);
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_histories');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('factories');
        Schema::dropIfExists('quotas');
        Schema::dropIfExists('products');
    }
};