<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCaseCause extends Model
{
    protected $fillable = ['attendance_case_id', 'absence_reason_id', 'is_primary', 'information_source', 'identified_on', 'observations', 'is_sensitive', 'registered_by'];
    protected $casts = ['is_primary' => 'boolean', 'is_sensitive' => 'boolean', 'identified_on' => 'date:Y-m-d'];

    public function attendanceCase(): BelongsTo { return $this->belongsTo(AttendanceCase::class); }
    public function reason(): BelongsTo { return $this->belongsTo(AttendanceAbsenceReason::class, 'absence_reason_id'); }
    public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by'); }
}
