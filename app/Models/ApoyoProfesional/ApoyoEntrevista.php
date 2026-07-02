<?php

namespace App\Models\ApoyoProfesional;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApoyoEntrevista extends Model
{
    use HasFactory;

    protected $table = 'apoyo_entrevistas';

    public const TYPE_OPTIONS = [
        ['value' => 'entrevista_estudiante', 'label' => 'Entrevista estudiante'],
        ['value' => 'entrevista_apoderado', 'label' => 'Entrevista apoderado'],
        ['value' => 'entrevista_profesor_jefe', 'label' => 'Entrevista profesor jefe'],
        ['value' => 'entrevista_docente', 'label' => 'Entrevista docente'],
        ['value' => 'entrevista_familiar', 'label' => 'Entrevista familiar'],
        ['value' => 'entrevista_equipo_interno', 'label' => 'Entrevista equipo interno'],
        ['value' => 'entrevista_red_externa', 'label' => 'Entrevista red externa'],
    ];

    protected $fillable = [
        'student_profile_id',
        'professional_id',
        'professional_user_id',
        'interview_type',
        'interview_at',
        'participants',
        'motive',
        'topics',
        'agreements',
        'commitments',
        'follow_up_date',
        'status',
        'confidentiality_level',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'interview_at' => 'datetime',
        'participants' => 'array',
        'follow_up_date' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ApoyoProfesionalProfile::class, 'professional_id');
    }

    public function professionalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ApoyoAdjunto::class, 'documentable')->latest('id');
    }
}
