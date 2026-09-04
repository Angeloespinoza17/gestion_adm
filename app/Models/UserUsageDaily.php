<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUsageDaily extends Model
{
    protected $table = 'user_usage_daily';

    protected $fillable = [
        'user_id',
        'usage_date',
        'login_count',
        'usage_count',
        'first_activity_at',
        'last_activity_at',
        'last_login_at',
    ];

    protected $casts = [
        'usage_date' => 'date:Y-m-d',
        'login_count' => 'integer',
        'usage_count' => 'integer',
        'first_activity_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
