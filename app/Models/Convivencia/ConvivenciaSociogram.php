<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaSociogram extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_sociograms';

    public const STATUS_OPTIONS = [
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'aplicado', 'label' => 'Aplicado'],
        ['value' => 'interpretado', 'label' => 'Interpretado'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
    ];

    protected $fillable = [
        'academic_year_id',
        'course_section_id',
        'title',
        'applied_on',
        'status',
        'confidentiality_level',
        'matrix_summary',
        'result_summary',
        'interpretation',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'applied_on' => 'date:Y-m-d',
        'matrix_summary' => 'array',
        'result_summary' => 'array',
        'is_sensitive' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ConvivenciaSociogramQuestion::class, 'sociogram_id')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ConvivenciaSociogramAnswer::class, 'sociogram_id')->latest('id');
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
