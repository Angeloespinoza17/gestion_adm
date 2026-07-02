<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaIdpsResult extends Model
{
    use HasFactory;

    protected $table = 'convivencia_idps_results';

    public const SCOPE_OPTIONS = [
        ['value' => 'curso', 'label' => 'Curso'],
        ['value' => 'nivel', 'label' => 'Nivel'],
        ['value' => 'establecimiento', 'label' => 'Establecimiento'],
    ];

    protected $fillable = [
        'period_id',
        'dimension_id',
        'instrument_id',
        'academic_year_id',
        'course_section_id',
        'education_level_id',
        'related_plan_id',
        'result_scope',
        'reference_label',
        'score',
        'percentage',
        'sample_size',
        'qualitative_observations',
        'improvement_actions',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'sample_size' => 'integer',
        'is_sensitive' => 'boolean',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaIdpsPeriod::class, 'period_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaIdpsDimension::class, 'dimension_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaIdpsInstrument::class, 'instrument_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function relatedPlan(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaPlan::class, 'related_plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
