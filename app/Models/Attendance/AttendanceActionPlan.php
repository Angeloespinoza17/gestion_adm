<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceActionPlan extends Model
{
    protected $fillable = [
        'folio', 'attendance_case_id', 'initial_situation', 'initial_attendance_rate', 'initial_lost_days',
        'initial_patterns', 'identified_causes', 'objective', 'goal_type', 'goal_value', 'goal_window_days',
        'starts_on', 'review_on', 'status', 'responsible_user_id', 'review_attendance_rate', 'result_variation',
        'evaluation_result', 'evaluated_at', 'evaluated_by', 'created_by', 'updated_by',
    ];
    protected $casts = [
        'initial_attendance_rate' => 'float', 'review_attendance_rate' => 'float', 'result_variation' => 'float',
        'goal_value' => 'float', 'initial_patterns' => 'array', 'identified_causes' => 'array',
        'starts_on' => 'date:Y-m-d', 'review_on' => 'date:Y-m-d', 'evaluated_at' => 'datetime',
    ];

    public function attendanceCase(): BelongsTo { return $this->belongsTo(AttendanceCase::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function actions(): HasMany { return $this->hasMany(AttendanceActionPlanAction::class)->orderBy('due_at'); }
}
