<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherDailySync extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider',
        'location_key',
        'sync_date',
        'status',
        'requested_at',
        'completed_at',
        'records_count',
        'error_message',
    ];

    protected $casts = [
        'sync_date' => 'date:Y-m-d',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'records_count' => 'integer',
    ];
}
