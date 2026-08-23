<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCaseNote extends Model
{
    protected $fillable = ['attendance_case_id', 'note', 'is_sensitive', 'created_by'];
    protected $casts = ['is_sensitive' => 'boolean'];

    public function attendanceCase(): BelongsTo { return $this->belongsTo(AttendanceCase::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
