<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceFamilyContact extends Model
{
    protected $fillable = ['attendance_case_id', 'contacted_at', 'channel', 'contacted_person', 'result', 'observation', 'next_contact_at', 'responsible_user_id', 'created_by'];
    protected $casts = ['contacted_at' => 'datetime', 'next_contact_at' => 'datetime'];

    public function attendanceCase(): BelongsTo { return $this->belongsTo(AttendanceCase::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
