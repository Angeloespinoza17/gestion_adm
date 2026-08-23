<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceManagementSetting extends Model
{
    protected $fillable = ['academic_year_id', 'risk_thresholds', 'risk_weights', 'pattern_settings', 'alert_settings', 'recovery_settings', 'updated_by'];

    protected $casts = [
        'risk_thresholds' => 'array',
        'risk_weights' => 'array',
        'pattern_settings' => 'array',
        'alert_settings' => 'array',
        'recovery_settings' => 'array',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
