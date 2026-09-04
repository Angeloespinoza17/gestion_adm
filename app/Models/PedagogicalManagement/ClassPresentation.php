<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaPublicationStatus;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
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

class ClassPresentation extends Model
{
    use HasFactory, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected static function booted(): void
    {
        static::creating(function (self $presentation): void {
            $presentation->series_uuid ??= $presentation->uuid;
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => ClassPresentationStatus::class,
            'progress' => 'integer',
            'configuration' => 'array',
            'curricular_snapshot' => 'array',
            'deck_json' => 'array',
            'canva_status' => CanvaPublicationStatus::class,
            'canva_submitted_at' => 'datetime',
            'canva_completed_at' => 'datetime',
            'canva_thumbnail_expires_at' => 'datetime',
            'canva_urls_refreshed_at' => 'datetime',
            'generated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canvaConnection(): BelongsTo
    {
        return $this->belongsTo(CanvaConnection::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'subject_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'unit_id');
    }

    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'class_presentation_learning_objective')
            ->withTimestamps();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClassPresentationFile::class)->orderBy('type')->orderBy('id');
    }

    public function referenceFiles(): HasMany
    {
        return $this->hasMany(ClassPresentationReferenceFile::class)->orderBy('id');
    }

    public function generationRuns(): HasMany
    {
        return $this->hasMany(ClassPresentationGenerationRun::class)->latest('id');
    }
}
