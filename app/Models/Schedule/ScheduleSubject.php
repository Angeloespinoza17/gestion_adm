<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'area',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function studyPlanSubjects(): HasMany
    {
        return $this->hasMany(StudyPlanSubject::class);
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class);
    }
}
