<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleValidationIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_event_id',
        'entity_type',
        'entity_id',
        'severity',
        'code',
        'message',
        'metadata',
        'resolved',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved' => 'boolean',
    ];

    public function scheduleEvent(): BelongsTo
    {
        return $this->belongsTo(ScheduleEvent::class);
    }
}
