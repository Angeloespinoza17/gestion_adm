<?php

namespace App\Models\ApoyoProfesional;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApoyoAtencion extends Model
{
    use HasFactory;

    protected $table = 'apoyo_atenciones';

    public const PRIORITY_OPTIONS = [
        ['value' => 'baja', 'label' => 'Baja'],
        ['value' => 'media', 'label' => 'Media'],
        ['value' => 'alta', 'label' => 'Alta'],
        ['value' => 'urgente', 'label' => 'Urgente'],
    ];

    public const CONFIDENTIALITY_OPTIONS = [
        ['value' => 'general', 'label' => 'General'],
        ['value' => 'reservada', 'label' => 'Reservada'],
        ['value' => 'confidencial', 'label' => 'Confidencial'],
        ['value' => 'alta_confidencialidad', 'label' => 'Alta confidencialidad'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'abierta', 'label' => 'Abierta'],
        ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
        ['value' => 'derivada', 'label' => 'Derivada'],
        ['value' => 'escalada', 'label' => 'Escalada'],
        ['value' => 'cerrada', 'label' => 'Cerrada'],
        ['value' => 'anulada', 'label' => 'Anulada'],
    ];

    public const MODALITY_OPTIONS = [
        ['value' => 'presencial', 'label' => 'Presencial'],
        ['value' => 'telefonica', 'label' => 'Telefónica'],
        ['value' => 'online', 'label' => 'Online'],
        ['value' => 'correo', 'label' => 'Correo'],
        ['value' => 'reunion_interna', 'label' => 'Reunión interna'],
        ['value' => 'otra', 'label' => 'Otra'],
    ];

    public const ORIGIN_OPTIONS = [
        ['value' => 'solicitud_profesor_jefe', 'label' => 'Solicitud de profesor jefe'],
        ['value' => 'derivacion_convivencia', 'label' => 'Derivación de convivencia'],
        ['value' => 'derivacion_utp', 'label' => 'Derivación de UTP'],
        ['value' => 'derivacion_pie', 'label' => 'Derivación PIE'],
        ['value' => 'derivacion_direccion', 'label' => 'Derivación de dirección'],
        ['value' => 'solicitud_apoderado', 'label' => 'Solicitud de apoderado'],
        ['value' => 'solicitud_estudiante', 'label' => 'Solicitud de estudiante'],
        ['value' => 'observacion_profesional', 'label' => 'Observación profesional'],
        ['value' => 'seguimiento_previo', 'label' => 'Seguimiento previo'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'course_section_id',
        'teacher_staff_id',
        'apoyo_profesional_id',
        'attended_by_user_id',
        'attention_type_id',
        'motive_id',
        'attended_at',
        'professional_role_name',
        'professional_area_slug',
        'professional_area_name',
        'student_full_name_snapshot',
        'student_rut_snapshot',
        'course_name_snapshot',
        'teacher_name_snapshot',
        'age_snapshot',
        'motive_label',
        'attention_type_label',
        'attention_type_other',
        'modality',
        'modality_other',
        'origin',
        'origin_other',
        'priority_level',
        'confidentiality_level',
        'reason_summary',
        'description',
        'professional_observations',
        'agreements',
        'recommendations',
        'next_action',
        'status',
        'case_closed_at',
        'case_closed_by',
        'case_closed_notes',
        'escalated_to_direction_at',
        'derived_to_convivencia_at',
        'derived_to_pie_at',
        'is_confidential_case',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'case_closed_at' => 'datetime',
        'escalated_to_direction_at' => 'datetime',
        'derived_to_convivencia_at' => 'datetime',
        'derived_to_pie_at' => 'datetime',
        'is_confidential_case' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_staff_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ApoyoProfesionalProfile::class, 'apoyo_profesional_id');
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by_user_id');
    }

    public function attentionType(): BelongsTo
    {
        return $this->belongsTo(ApoyoConfigAttentionType::class, 'attention_type_id');
    }

    public function motive(): BelongsTo
    {
        return $this->belongsTo(ApoyoConfigMotivo::class, 'motive_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'case_closed_by');
    }

    public function derivations(): HasMany
    {
        return $this->hasMany(ApoyoDerivacion::class, 'attention_id')->latest('derived_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(ApoyoSeguimiento::class, 'attention_id')->latest('scheduled_at');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ApoyoAdjunto::class, 'documentable')->latest('id');
    }
}
