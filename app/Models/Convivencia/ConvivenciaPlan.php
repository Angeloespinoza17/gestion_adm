<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\StudentProfile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaPlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_plans';

    public const STATUS_OPTIONS = [
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'vigente', 'label' => 'Vigente'],
        ['value' => 'en_ejecucion', 'label' => 'En ejecución'],
        ['value' => 'finalizado', 'label' => 'Finalizado'],
        ['value' => 'suspendido', 'label' => 'Suspendido'],
    ];

    protected $fillable = [
        'academic_year_id',
        'responsible_user_id',
        'responsible_staff_id',
        'name',
        'general_objective',
        'specific_objectives',
        'resources_required',
        'indicators_summary',
        'verification_means_summary',
        'status',
        'advance_percentage',
        'starts_on',
        'ends_on',
        'observations',
        'final_evaluation',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'specific_objectives' => 'array',
        'advance_percentage' => 'decimal:2',
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'is_sensitive' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ConvivenciaPlanAction::class, 'plan_id')->orderBy('starts_on')->orderBy('id');
    }

    public function idpsResults(): HasMany
    {
        return $this->hasMany(ConvivenciaIdpsResult::class, 'related_plan_id')->latest('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(ConvivenciaAttachment::class, 'attachable')->latest('id');
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
