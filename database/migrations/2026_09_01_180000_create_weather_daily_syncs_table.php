<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_daily_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('location_key', 80);
            $table->date('sync_date');
            $table->string('status', 20)->default('processing');
            $table->dateTime('requested_at');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedTinyInteger('records_count')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'location_key', 'sync_date'],
                'weather_daily_sync_provider_location_date_uq'
            );
            $table->index(['sync_date', 'status'], 'weather_daily_sync_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_daily_syncs');
    }
};
