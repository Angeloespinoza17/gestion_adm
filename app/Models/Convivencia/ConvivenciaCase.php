<?php

namespace App\Models\Convivencia;

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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaCase extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_cases';

    public const STATUS_OPTIONS = [
        ['value' => 'abierto', 'label' => 'Abierto'],
        ['value' => 'en_analisis', 'label' => 'En análisis'],
        ['value' => 'en_intervencion', 'label' => 'En intervención'],
        ['value' => 'con_protocolo_activo', 'label' => 'Con protocolo activo'],
        ['value' => 'derivado', 'label' => 'Derivado'],
        ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
        ['value' => 'archivado', 'label' => 'Archivado'],
    ];

    public const ORIGIN_OPTIONS = [
        ['value' => 'denuncia', 'label' => 'Denuncia'],
        ['value' => 'bitacora', 'label' => 'Bitácora'],
        ['value' => 'entrevista', 'label' => 'Entrevista'],
        ['value' => 'derivacion', 'label' => 'Derivación'],
        ['value' => 'observacion', 'label' => 'Observación'],
        ['value' => 'protocolo', 'label' => 'Protocolo'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    public const PERSON_TYPE_OPTIONS = [
        ['value' => 'estudiante', 'label' => 'Estudiante'],
        ['value' => 'apoderado', 'label' => 'Apoderado'],
        ['value' => 'funcionario', 'label' => 'Funcionario'],
        ['value' => 'externo', 'label' => 'Externo'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    public const PERSON_ROLE_OPTIONS = [
        ['value' => 'afectado', 'label' => 'Afectado'],
        ['value' => 'denunciado', 'label' => 'Denunciado'],
        ['value' => 'testigo', 'label' => 'Testigo'],
        ['value' => 'informante', 'label' => 'Informante'],
        ['value' => 'responsable_seguimiento', 'label' => 'Responsable de seguimiento'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'folio',
        'sourceable_type',
        'sourceable_id',
        'academic_year_id',
        'course_section_id',
        'student_profile_id',
        'case_type_item_id',
        'classification_item_id',
        'subclassification_item_id',
        'criticality_item_id',
        'responsible_user_id',
        'responsible_staff_id',
        'opened_at',
        'happened_at',
        'origin',
        'status',
        'case_type_label',
        'classification_label',
        'subclassification_label',
        'criticality_label',
        'place',
        'initial_report',
        'background',
        'immediate_measures',
        'safeguarding_measures',
        'internal_notes',
        'resolution',
        'conclusion',
        'follow_up_due_at',
        'is_sensitive',
        'closed_at',
        'closed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'happened_at' => 'datetime',
        'follow_up_due_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_sensitive' => 'boolean',
    ];

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'case_type_item_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'classification_item_id');
    }

    public function subclassification(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'subclassification_item_id');
    }

    public function criticality(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'criticality_item_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function people(): HasMany
    {
        return $this->hasMany(ConvivenciaCasePerson::class, 'case_id')->orderBy('id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(ConvivenciaCaseFollowUp::class, 'case_id')->orderByDesc('follow_up_at')->orderByDesc('id');
    }

    public function derivations(): HasMany
    {
        return $this->hasMany(ConvivenciaDerivation::class, 'case_id')->latest('derived_at');
    }

    public function measures(): HasMany
    {
        return $this->hasMany(ConvivenciaMeasure::class, 'case_id')->latest('assigned_at');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(ConvivenciaInterview::class, 'case_id')->latest('interview_at');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(ConvivenciaComplaint::class, 'case_id')->latest('received_at');
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(ConvivenciaDailyLog::class, 'case_id')->latest('happened_at');
    }

    public function protocolActivations(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivation::class, 'case_id')->latest('activated_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(ConvivenciaAttachment::class, 'attachable')->latest('id');
    }

    public function statusLogs(): MorphMany
    {
        return $this->morphMany(ConvivenciaStatusLog::class, 'loggable')->latest('changed_at')->latest('id');
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
