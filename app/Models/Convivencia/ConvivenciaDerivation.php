<?php

namespace App\Models\Convivencia;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaDerivation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'convivencia_derivations';

    public const STATUS_OPTIONS = [
        ['value' => 'ingresada', 'label' => 'Ingresada'],
        ['value' => 'recibida', 'label' => 'Recibida'],
        ['value' => 'en_revision', 'label' => 'En revisión'],
        ['value' => 'en_intervencion', 'label' => 'En intervención'],
        ['value' => 'respondida', 'label' => 'Respondida'],
        ['value' => 'cerrada', 'label' => 'Cerrada'],
        ['value' => 'rechazada', 'label' => 'Rechazada'],
    ];

    public const PRIORITY_OPTIONS = [
        ['value' => 'baja', 'label' => 'Baja'],
        ['value' => 'media', 'label' => 'Media'],
        ['value' => 'alta', 'label' => 'Alta'],
        ['value' => 'urgente', 'label' => 'Urgente'],
    ];

    public const SCOPE_OPTIONS = [
        ['value' => 'internal', 'label' => 'Interna'],
        ['value' => 'external', 'label' => 'Externa'],
    ];

    protected $fillable = [
        'case_id',
        'academic_year_id',
        'course_section_id',
        'student_profile_id',
        'destination_department_id',
        'destination_staff_id',
        'destination_user_id',
        'external_institution_id',
        'responsible_user_id',
        'scope',
        'status',
        'priority_level',
        'confidentiality_level',
        'destination_label',
        'external_contact_name',
        'external_contact_email',
        'external_contact_phone',
        'derived_at',
        'sent_at',
        'response_due_at',
        'responded_at',
        'closed_at',
        'motive',
        'narrative',
        'response_text',
        'suggested_actions',
        'follow_up_notes',
        'is_sensitive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'derived_at' => 'datetime',
        'sent_at' => 'datetime',
        'response_due_at' => 'datetime',
        'responded_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_sensitive' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
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

    public function destinationDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'destination_department_id');
    }

    public function destinationStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'destination_staff_id');
    }

    public function destinationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function externalInstitution(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaExternalInstitution::class, 'external_institution_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
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
