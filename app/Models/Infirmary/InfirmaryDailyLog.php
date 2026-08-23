<?php

namespace App\Models\Infirmary;

use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfirmaryDailyLog extends Model
{
    public const CATEGORIES = [
        'atencion_relevante' => 'Atención relevante',
        'contacto_apoderado' => 'Contacto con apoderado',
        'derivacion_traslado' => 'Derivación o traslado',
        'medicamento' => 'Medicamento o tratamiento',
        'insumos_equipamiento' => 'Insumos o equipamiento',
        'bioseguridad' => 'Bioseguridad y aseo clínico',
        'coordinacion' => 'Coordinación interna',
        'observacion_general' => 'Observación de jornada',
        'otro' => 'Otro hecho de Enfermería',
    ];

    public const PRIORITIES = [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    public const STATUSES = [
        'registrado' => 'Registrado',
        'en_seguimiento' => 'En seguimiento',
        'cerrado' => 'Cerrado',
    ];

    protected $table = 'infirmary_daily_logs';

    protected $fillable = [
        'student_profile_id', 'course_section_id', 'registered_by_user_id',
        'happened_at', 'category', 'priority', 'status', 'title', 'detail',
        'action_taken', 'requires_follow_up', 'follow_up_note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'happened_at' => 'datetime:Y-m-d H:i:s',
        'requires_follow_up' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
