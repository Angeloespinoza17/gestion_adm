<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_daily_records', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('location_key', 80);
            $table->string('location_name', 120);
            $table->string('administrative_region', 120);
            $table->string('provider_region', 120)->nullable();
            $table->string('country', 80);
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->string('timezone', 80);
            $table->date('forecast_date');
            $table->string('record_type', 24)->default('forecast');
            $table->decimal('min_temp_c', 5, 2)->nullable();
            $table->decimal('max_temp_c', 5, 2)->nullable();
            $table->decimal('avg_temp_c', 5, 2)->nullable();
            $table->decimal('max_wind_kph', 6, 2)->nullable();
            $table->decimal('total_precip_mm', 7, 2)->nullable();
            $table->unsignedTinyInteger('avg_humidity')->nullable();
            $table->unsignedTinyInteger('chance_of_rain')->nullable();
            $table->string('condition_text', 160)->nullable();
            $table->unsignedSmallInteger('condition_code')->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('sunrise', 20)->nullable();
            $table->string('sunset', 20)->nullable();
            $table->json('source_daily_payload')->nullable();
            $table->dateTime('provider_localtime')->nullable();
            $table->dateTime('fetched_at');
            $table->timestamps();

            $table->unique(['provider', 'location_key', 'forecast_date'], 'weather_provider_location_date_uq');
            $table->index(['location_key', 'forecast_date'], 'weather_location_date_idx');
            $table->index(['forecast_date', 'chance_of_rain'], 'weather_date_rain_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_daily_records');
    }
};
