<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCaseStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'attendance_case_status_history';
    protected $fillable = ['attendance_case_id', 'from_status', 'to_status', 'reason', 'changed_by', 'changed_at'];
    protected $casts = ['changed_at' => 'datetime'];

    public function attendanceCase(): BelongsTo { return $this->belongsTo(AttendanceCase::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
