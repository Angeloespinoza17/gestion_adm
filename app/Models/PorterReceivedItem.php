<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class PorterReceivedItem extends Model
{
    use HasFactory;

    public const RECIPIENT_TYPE_OPTIONS = [
        ['value' => 'student', 'label' => 'Estudiante'],
        ['value' => 'staff', 'label' => 'Funcionario'],
        ['value' => 'department', 'label' => 'Departamento'],
        ['value' => 'other', 'label' => 'Otro'],
    ];

    public const ITEM_TYPE_OPTIONS = [
        ['value' => 'objeto', 'label' => 'Objeto'],
        ['value' => 'documento', 'label' => 'Documento'],
        ['value' => 'material_escolar', 'label' => 'Material escolar'],
        ['value' => 'medicamento', 'label' => 'Medicamento'],
        ['value' => 'encomienda', 'label' => 'Encomienda'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'recibido_en_porteria', 'label' => 'Recibido en portería'],
        ['value' => 'derivado', 'label' => 'Derivado'],
        ['value' => 'entregado_al_destinatario', 'label' => 'Entregado al destinatario'],
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
    ];

    protected $fillable = [
        'recipient_type',
        'recipient_label',
        'student_profile_id',
        'staff_id',
        'department_id',
        'academic_year_id',
        'course_section_id',
        'registered_by',
        'delivered_by',
        'status',
        'received_at',
        'delivered_at',
        'received_from_name',
        'received_from_rut',
        'received_from_phone',
        'item_type',
        'description',
        'observations',
        'delivered_to_name',
        'delivered_to_rut',
        'delivery_observations',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'metadata',
    ];

    protected $casts = [
        'received_at' => 'datetime:Y-m-d H:i:s',
        'delivered_at' => 'datetime:Y-m-d H:i:s',
        'metadata' => 'array',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
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
