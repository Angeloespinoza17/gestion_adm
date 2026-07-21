<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAlertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id', 'code', 'name', 'description', 'metric', 'operator', 'threshold',
        'evaluation_period', 'severity', 'cooldown_days', 'response_due_days', 'auto_create_case',
        'recipient_roles', 'notification_channels', 'scope', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'threshold' => 'decimal:2', 'cooldown_days' => 'integer', 'response_due_days' => 'integer',
        'auto_create_case' => 'boolean', 'recipient_roles' => 'array', 'notification_channels' => 'array',
        'scope' => 'array', 'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
