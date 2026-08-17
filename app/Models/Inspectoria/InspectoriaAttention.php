<?php

namespace App\Models\Inspectoria;

use App\Models\CourseSection;
use App\Models\SocialWork\Referral;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InspectoriaAttention extends Model
{
    public const REQUEST_TYPES = [
        'atraso' => 'Atraso / justificación',
        'malestar' => 'Malestar físico',
        'permiso_bano' => 'Permiso al baño',
        'utiles' => 'Útiles o materiales',
        'uniforme' => 'Uniforme / presentación',
        'convivencia' => 'Situación de convivencia',
        'contacto_apoderado' => 'Contacto con apoderado',
        'retiro' => 'Solicitud de retiro',
        'orientacion' => 'Orientación breve',
        'otro' => 'Otra solicitud',
    ];

    public const ACTIONS = [
        'resuelta' => 'Resuelta en el momento',
        'pase_emitido' => 'Pase emitido',
        'derivada_enfermeria' => 'Derivada a Enfermería',
        'derivada_convivencia' => 'Derivada a Convivencia',
        'derivacion_psicosocial' => 'Derivación psicosocial',
        'derivada_direccion' => 'Derivada a Dirección',
        'apoderado_contactado' => 'Apoderado contactado',
        'seguimiento' => 'Requiere seguimiento',
    ];

    protected $table = 'inspectoria_attentions';

    protected $fillable = [
        'attention_code', 'student_profile_id', 'course_section_id', 'inspector_staff_id',
        'attended_by_user_id', 'attended_at', 'request_types', 'actions_taken', 'priority',
        'status', 'student_name_snapshot', 'course_name_snapshot', 'brief_note',
        'guardian_notified', 'requires_follow_up', 'created_by', 'updated_by',
        'psychosocial_referral_user_id', 'psychosocial_referral_name_snapshot',
        'psychosocial_referral_role_snapshot', 'psychosocial_referred_at',
    ];

    protected $casts = [
        'attended_at' => 'datetime:Y-m-d H:i:s',
        'request_types' => 'array',
        'actions_taken' => 'array',
        'guardian_notified' => 'boolean',
        'requires_follow_up' => 'boolean',
        'psychosocial_referred_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'inspector_staff_id');
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by_user_id');
    }

    public function psychosocialReferralUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'psychosocial_referral_user_id');
    }

    public function socialWorkReferral(): HasOne
    {
        return $this->hasOne(Referral::class, 'inspectoria_attention_id');
    }
}
