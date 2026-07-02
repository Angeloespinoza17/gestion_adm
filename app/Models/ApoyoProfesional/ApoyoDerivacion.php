<?php

namespace App\Models\ApoyoProfesional;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApoyoDerivacion extends Model
{
    use HasFactory;

    protected $table = 'apoyo_derivaciones';

    public const STATUS_OPTIONS = [
        ['value' => 'enviada', 'label' => 'Enviada'],
        ['value' => 'recibida', 'label' => 'Recibida'],
        ['value' => 'en_revision', 'label' => 'En revisión'],
        ['value' => 'aceptada', 'label' => 'Aceptada'],
        ['value' => 'rechazada', 'label' => 'Rechazada'],
        ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
        ['value' => 'cerrada', 'label' => 'Cerrada'],
    ];

    public const URGENCY_OPTIONS = [
        ['value' => 'baja', 'label' => 'Baja'],
        ['value' => 'media', 'label' => 'Media'],
        ['value' => 'alta', 'label' => 'Alta'],
        ['value' => 'urgente', 'label' => 'Urgente'],
    ];

    protected $fillable = [
        'attention_id',
        'student_profile_id',
        'destination_professional_id',
        'destination_user_id',
        'origin_area_slug',
        'origin_area_name',
        'destination_area_slug',
        'destination_area_name',
        'urgency_level',
        'confidentiality_level',
        'status',
        'reason',
        'description',
        'destination_response',
        'derived_at',
        'response_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'derived_at' => 'datetime',
        'response_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function attention(): BelongsTo
    {
        return $this->belongsTo(ApoyoAtencion::class, 'attention_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function destinationProfessional(): BelongsTo
    {
        return $this->belongsTo(ApoyoProfesionalProfile::class, 'destination_professional_id');
    }

    public function destinationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ApoyoAdjunto::class, 'documentable')->latest('id');
    }
}
