<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyPlanSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_plan_id',
        'schedule_subject_id',
        'weekly_pedagogical_hours',
        'required',
        'notes',
    ];

    protected $casts = [
        'weekly_pedagogical_hours' => 'decimal:2',
        'required' => 'boolean',
    ];

    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function scheduleSubject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class);
    }
}
