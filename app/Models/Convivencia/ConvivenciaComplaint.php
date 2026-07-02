<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaComplaint extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_complaints';

    public const STATUS_OPTIONS = [
        ['value' => 'recibida', 'label' => 'Recibida'],
        ['value' => 'en_revision', 'label' => 'En revisión'],
        ['value' => 'requiere_antecedentes', 'label' => 'Requiere antecedentes'],
        ['value' => 'derivada_a_caso', 'label' => 'Derivada a caso'],
        ['value' => 'protocolo_activado', 'label' => 'Protocolo activado'],
        ['value' => 'cerrada', 'label' => 'Cerrada'],
        ['value' => 'descartada_fundadamente', 'label' => 'Descartada fundadamente'],
    ];

    public const COMPLAINANT_TYPE_OPTIONS = [
        ['value' => 'estudiante', 'label' => 'Estudiante'],
        ['value' => 'apoderado', 'label' => 'Apoderado'],
        ['value' => 'funcionario', 'label' => 'Funcionario'],
        ['value' => 'anonimo', 'label' => 'Anónimo'],
        ['value' => 'externo', 'label' => 'Externo'],
    ];

    protected $fillable = [
        'folio',
        'academic_year_id',
        'course_section_id',
        'affected_student_id',
        'situation_type_item_id',
        'responsible_user_id',
        'case_id',
        'complainant_name',
        'complainant_type',
        'contact_email',
        'contact_phone',
        'situation_type_label',
        'place',
        'received_at',
        'happened_at',
        'report_text',
        'involved_snapshot',
        'truth_declaration_accepted',
        'is_anonymous',
        'is_sensitive',
        'status',
        'admissibility_result',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'happened_at' => 'datetime',
        'involved_snapshot' => 'array',
        'truth_declaration_accepted' => 'boolean',
        'is_anonymous' => 'boolean',
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

    public function affectedStudent(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'affected_student_id');
    }

    public function situationType(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCatalogItem::class, 'situation_type_item_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function protocolActivations(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivation::class, 'complaint_id')->latest('activated_at');
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
