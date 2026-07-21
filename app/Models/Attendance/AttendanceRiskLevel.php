<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRiskLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id', 'slug', 'name', 'minimum_rate', 'maximum_rate', 'color', 'icon',
        'priority', 'suggested_actions', 'default_responsible_id', 'intervention_due_days',
        'notification_channels', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'minimum_rate' => 'decimal:2', 'maximum_rate' => 'decimal:2', 'priority' => 'integer',
        'intervention_due_days' => 'integer', 'notification_channels' => 'array', 'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function defaultResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_responsible_id');
    }
}
