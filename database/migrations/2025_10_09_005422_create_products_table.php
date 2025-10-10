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
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('sap_model')->nullable();
                $table->string('category')->nullable();
                $table->decimal('pk_capacity', 5, 2)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'sap_model')) {
                    $table->string('sap_model')->nullable();
                }
                if (!Schema::hasColumn('products', 'category')) {
                    $table->string('category')->nullable();
                }
                if (!Schema::hasColumn('products', 'pk_capacity')) {
                    $table->decimal('pk_capacity', 5, 2)->nullable();
                }
                if (!Schema::hasColumn('products', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (Schema::hasColumn('products', 'model_type')) {
                    $table->dropColumn('model_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'sap_model')) {
                    $table->dropColumn('sap_model');
                }
                if (Schema::hasColumn('products', 'category')) {
                    $table->dropColumn('category');
                }
                if (Schema::hasColumn('products', 'pk_capacity')) {
                    $table->dropColumn('pk_capacity');
                }
            });
        }
    }
};
