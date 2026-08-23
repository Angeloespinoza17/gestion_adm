<?php

namespace App\Models\Schedule;

use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\SubjectCatalogProfile;
use App\Models\LibroDigital\SubjectExternalAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function catalogProfile(): HasOne
    {
        return $this->hasOne(SubjectCatalogProfile::class, 'schedule_subject_id');
    }

    public function externalAliases(): HasMany
    {
        return $this->hasMany(SubjectExternalAlias::class, 'schedule_subject_id');
    }

    public function learningObjectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'schedule_subject_id');
    }

    public function curriculumPrograms(): HasMany
    {
        return $this->hasMany(CurriculumProgram::class, 'schedule_subject_id');
    }

    public function resolvedDisplayName(): string
    {
        $configured = $this->relationLoaded('catalogProfile')
            ? trim((string) ($this->catalogProfile?->display_name ?? ''))
            : '';
        if ($configured !== '') {
            return $configured;
        }

        return trim(preg_replace('/[_]+/', ' ', (string) $this->name) ?? (string) $this->name);
    }
}
