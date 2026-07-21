<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceFollowup extends Model
{
    protected $fillable = [
        'attendance_alert_id', 'action_type', 'action_date', 'status', 'notes',
        'next_action_date', 'created_by',
    ];

    protected $casts = [
        'action_date' => 'date:Y-m-d',
        'next_action_date' => 'date:Y-m-d',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(AttendanceAlert::class, 'attendance_alert_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
