<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMeetingAgreement extends Model
{
    protected $fillable = ['attendance_case_id', 'meeting_date', 'agreement', 'responsible_user_id', 'commitment_date', 'status', 'created_by'];
    protected $casts = ['meeting_date' => 'date:Y-m-d', 'commitment_date' => 'date:Y-m-d'];

    public function attendanceCase(): BelongsTo { return $this->belongsTo(AttendanceCase::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
