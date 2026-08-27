<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\EvaluationPurpose;
use App\Enums\PedagogicalManagement\InstrumentStatus;
use App\Enums\PedagogicalManagement\InstrumentWorkflowStatus;
use App\Enums\PedagogicalManagement\InstrumentType;
use App\Enums\PedagogicalManagement\WorkModality;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedagogicalInstrument extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'instrument_type' => InstrumentType::class,
            'evaluation_purpose' => EvaluationPurpose::class,
            'work_modality' => WorkModality::class,
            'status' => InstrumentStatus::class,
            'workflow_status' => InstrumentWorkflowStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'application_date' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'declared_total_points' => 'decimal:2',
            'passing_percentage' => 'decimal:2',
            'weighting_percentage' => 'decimal:2',
            'minimum_grade' => 'decimal:2',
            'maximum_grade' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'subject_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'unit_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(CourseSection::class, 'pedagogical_instrument_courses', 'instrument_id', 'course_id')->withTimestamps();
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentObjective::class, 'instrument_id');
    }

    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'pedagogical_instrument_objectives', 'instrument_id', 'learning_objective_id')
            ->withPivot(['origin', 'detected_code', 'confirmation_status', 'confirmed_by', 'confirmed_at'])
            ->withTimestamps();
    }

    public function files(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentFile::class, 'instrument_id')->orderByDesc('version');
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentAnalysisRun::class, 'instrument_id')->latest('id');
    }

    public function latestFile(): HasOne
    {
        return $this->hasOne(PedagogicalInstrumentFile::class, 'instrument_id')->ofMany('version', 'max');
    }

    public function latestAnalysisRun(): HasOne
    {
        return $this->hasOne(PedagogicalInstrumentAnalysisRun::class, 'instrument_id')->latestOfMany();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentReview::class, 'instrument_id')->latest('reviewed_at');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(PedagogicalInstrumentReview::class, 'instrument_id')->latestOfMany('reviewed_at');
    }

    public function aiReports(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentAiReport::class, 'instrument_id')->latest('id');
    }

    public function latestAiReport(): HasOne
    {
        return $this->hasOne(PedagogicalInstrumentAiReport::class, 'instrument_id')->latestOfMany();
    }

    public function printRequests(): HasMany
    {
        return $this->hasMany(PedagogicalInstrumentPrintRequest::class, 'instrument_id')->latest('id');
    }
}
