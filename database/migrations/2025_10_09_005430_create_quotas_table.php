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
        if (!Schema::hasTable('quotas')) {
            Schema::create('quotas', function (Blueprint $table) {
                $table->id();
                $table->string('quota_number')->unique();
                $table->string('name');
                $table->string('government_category');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->unsignedInteger('total_allocation');
                $table->unsignedInteger('forecast_remaining');
                $table->unsignedInteger('actual_remaining');
                $table->enum('status', ['available', 'limited', 'depleted'])->default('available');
                $table->boolean('is_active')->default(true);
                $table->string('source_document')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('quotas', function (Blueprint $table) {
                if (!Schema::hasColumn('quotas', 'name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('quotas', 'government_category')) {
                    $table->string('government_category')->nullable();
                }
                if (!Schema::hasColumn('quotas', 'total_allocation')) {
                    $table->unsignedInteger('total_allocation')->default(0);
                }
                if (!Schema::hasColumn('quotas', 'forecast_remaining')) {
                    $table->unsignedInteger('forecast_remaining')->default(0);
                }
                if (!Schema::hasColumn('quotas', 'actual_remaining')) {
                    $table->unsignedInteger('actual_remaining')->default(0);
                }
                if (Schema::hasColumn('quotas', 'status')) {
                    // Buang kolom status lama (mis. 'low', 'exhausted') dan ganti dengan enumerasi baru
                    $table->dropColumn('status');
                }
                if (!Schema::hasColumn('quotas', 'status')) {
                    $table->enum('status', ['available', 'limited', 'depleted'])->default('available');
                }
                if (!Schema::hasColumn('quotas', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('quotas', 'source_document')) {
                    $table->string('source_document')->nullable();
                }
                if (!Schema::hasColumn('quotas', 'deleted_at')) {
                    $table->softDeletes();
                }
                // Hapus kolom lama yang tidak lagi dipakai
                if (Schema::hasColumn('quotas', 'product_id')) {
                    $table->dropConstrainedForeignId('product_id');
                }
                if (Schema::hasColumn('quotas', 'government_quantity')) {
                    $table->dropColumn('government_quantity');
                }
                if (Schema::hasColumn('quotas', 'forecast_quantity')) {
                    $table->dropColumn('forecast_quantity');
                }
                if (Schema::hasColumn('quotas', 'actual_quantity')) {
                    $table->dropColumn('actual_quantity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('quotas')) {
            Schema::table('quotas', function (Blueprint $table) {
                if (Schema::hasColumn('quotas', 'name')) {
                    $table->dropColumn('name');
                }
                if (Schema::hasColumn('quotas', 'government_category')) {
                    $table->dropColumn('government_category');
                }
                if (Schema::hasColumn('quotas', 'total_allocation')) {
                    $table->dropColumn('total_allocation');
                }
                if (Schema::hasColumn('quotas', 'forecast_remaining')) {
                    $table->dropColumn('forecast_remaining');
                }
                if (Schema::hasColumn('quotas', 'actual_remaining')) {
                    $table->dropColumn('actual_remaining');
                }
                if (Schema::hasColumn('quotas', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('quotas', 'is_active')) {
                    $table->dropColumn('is_active');
                }
                if (Schema::hasColumn('quotas', 'source_document')) {
                    $table->dropColumn('source_document');
                }
            });
        }
    }
};
