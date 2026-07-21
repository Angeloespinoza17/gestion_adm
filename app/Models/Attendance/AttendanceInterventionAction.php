<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceInterventionAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_intervention_id', 'action_type', 'title', 'description', 'scheduled_at',
        'completed_at', 'status', 'responsible_user_id', 'evidence', 'created_by', 'updated_by',
    ];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'evidence' => 'array'];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(AttendanceIntervention::class, 'attendance_intervention_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
