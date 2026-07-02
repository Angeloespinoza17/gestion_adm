<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class PorterStudentWithdrawal extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'registrado', 'label' => 'Registrado'],
        ['value' => 'autorizado', 'label' => 'Autorizado'],
        ['value' => 'observado', 'label' => 'Observado'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
        ['value' => 'anulado', 'label' => 'Anulado'],
    ];

    public const RELATIONSHIP_OPTIONS = [
        ['value' => 'madre', 'label' => 'Madre'],
        ['value' => 'padre', 'label' => 'Padre'],
        ['value' => 'apoderado', 'label' => 'Apoderado'],
        ['value' => 'familiar', 'label' => 'Familiar'],
        ['value' => 'transporte', 'label' => 'Transporte'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    public const REASON_OPTIONS = [
        ['value' => 'medico', 'label' => 'Médico'],
        ['value' => 'familiar', 'label' => 'Familiar'],
        ['value' => 'emergencia', 'label' => 'Emergencia'],
        ['value' => 'tramite', 'label' => 'Trámite'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'course_section_id',
        'registered_by',
        'authorized_by',
        'cancelled_by',
        'status',
        'withdrawn_at',
        'student_full_name_snapshot',
        'student_rut_snapshot',
        'academic_year_name_snapshot',
        'course_name_snapshot',
        'person_name',
        'person_rut',
        'person_relationship',
        'person_phone',
        'reason',
        'observations',
        'person_authorized',
        'authorization_source',
        'requires_special_authorization',
        'authorization_notes',
        'cancellation_reason',
        'cancelled_at',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime:Y-m-d H:i:s',
        'cancelled_at' => 'datetime:Y-m-d H:i:s',
        'person_authorized' => 'boolean',
        'requires_special_authorization' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function authorizationRequests(): MorphMany
    {
        return $this->morphMany(PorterAuthorizationRequest::class, 'authorizable')->latest('id');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(PorterMovementLog::class, 'loggable')->latest('performed_at')->latest('id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->attachment_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }
}
