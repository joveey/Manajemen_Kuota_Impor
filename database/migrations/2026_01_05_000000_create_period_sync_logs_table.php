<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('period_sync_logs')) {
            Schema::create('period_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->string('module');
                $table->string('period_key');
                $table->dateTime('period_start');
                $table->dateTime('period_end');
                $table->timestamp('last_synced_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['module', 'period_key'], 'period_sync_logs_period_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('period_sync_logs');
    }
};
