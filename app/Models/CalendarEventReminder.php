<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'reminder_type',
        'days_before',
        'reminder_date',
        'sent_at',
        'is_active',
    ];

    protected $casts = [
        'reminder_date' => 'date:Y-m-d',
        'sent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }
}
