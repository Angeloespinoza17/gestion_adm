<?php

namespace App\Models\ApoyoProfesional;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApoyoPlan extends Model
{
    use HasFactory;

    protected $table = 'apoyo_planes';

    public const STATUS_OPTIONS = [
        ['value' => 'disenado', 'label' => 'Diseñado'],
        ['value' => 'en_ejecucion', 'label' => 'En ejecución'],
        ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
        ['value' => 'finalizado', 'label' => 'Finalizado'],
        ['value' => 'suspendido', 'label' => 'Suspendido'],
    ];

    protected $fillable = [
        'student_profile_id',
        'responsible_professional_id',
        'responsible_user_id',
        'area_slug',
        'area_name',
        'motive',
        'general_objective',
        'specific_objectives',
        'actions_summary',
        'responsibles_summary',
        'start_date',
        'end_date',
        'indicators',
        'status',
        'evidences',
        'observations',
        'confidentiality_level',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'specific_objectives' => 'array',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function responsibleProfessional(): BelongsTo
    {
        return $this->belongsTo(ApoyoProfesionalProfile::class, 'responsible_professional_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApoyoPlanAccion::class, 'plan_id')->orderBy('due_date')->orderBy('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ApoyoAdjunto::class, 'documentable')->latest('id');
    }
}
