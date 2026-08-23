<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceActionPlanAction extends Model
{
    protected $fillable = ['attendance_action_plan_id', 'title', 'description', 'responsible_user_id', 'frequency', 'due_at', 'completed_at', 'status', 'result', 'created_by', 'updated_by'];
    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function plan(): BelongsTo { return $this->belongsTo(AttendanceActionPlan::class, 'attendance_action_plan_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
