<?php

namespace App\Models\ApoyoProfesional;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApoyoSeguimiento extends Model
{
    use HasFactory;

    protected $table = 'apoyo_seguimientos';

    public const STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'realizado', 'label' => 'Realizado'],
        ['value' => 'reprogramado', 'label' => 'Reprogramado'],
        ['value' => 'cancelado', 'label' => 'Cancelado'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
    ];

    protected $fillable = [
        'attention_id',
        'student_profile_id',
        'responsible_professional_id',
        'responsible_user_id',
        'scheduled_at',
        'completed_at',
        'comment',
        'status',
        'next_action',
        'evidence_summary',
        'result',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function attention(): BelongsTo
    {
        return $this->belongsTo(ApoyoAtencion::class, 'attention_id');
    }

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

    public function documents(): MorphMany
    {
        return $this->morphMany(ApoyoAdjunto::class, 'documentable')->latest('id');
    }
}
