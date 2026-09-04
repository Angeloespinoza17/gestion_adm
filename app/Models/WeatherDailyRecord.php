<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherDailyRecord extends Model
{
    protected $fillable = [
        'provider',
        'location_key',
        'location_name',
        'administrative_region',
        'provider_region',
        'country',
        'latitude',
        'longitude',
        'timezone',
        'forecast_date',
        'record_type',
        'min_temp_c',
        'max_temp_c',
        'avg_temp_c',
        'max_wind_kph',
        'total_precip_mm',
        'avg_humidity',
        'chance_of_rain',
        'condition_text',
        'condition_code',
        'icon_url',
        'sunrise',
        'sunset',
        'source_daily_payload',
        'provider_localtime',
        'fetched_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'forecast_date' => 'date:Y-m-d',
        'min_temp_c' => 'decimal:2',
        'max_temp_c' => 'decimal:2',
        'avg_temp_c' => 'decimal:2',
        'max_wind_kph' => 'decimal:2',
        'total_precip_mm' => 'decimal:2',
        'avg_humidity' => 'integer',
        'chance_of_rain' => 'integer',
        'condition_code' => 'integer',
        'source_daily_payload' => 'array',
        'provider_localtime' => 'datetime',
        'fetched_at' => 'datetime',
    ];
}
