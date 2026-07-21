<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceScheduledReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_year_id', 'owner_user_id', 'name', 'report_type', 'format', 'frequency',
        'run_at', 'filters', 'recipients', 'active', 'last_run_at', 'next_run_at', 'last_error',
    ];

    protected $casts = [
        'filters' => 'array', 'recipients' => 'array', 'active' => 'boolean',
        'last_run_at' => 'datetime', 'next_run_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
