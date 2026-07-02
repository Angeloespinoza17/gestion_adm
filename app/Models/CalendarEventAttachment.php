<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
