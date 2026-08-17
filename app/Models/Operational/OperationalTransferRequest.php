<?php

namespace App\Models\Operational;

use App\Models\Staff;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OperationalTransferRequest extends Model
{
    use HasFactory;

    public const APPROVAL_STATUS_OPTIONS = [
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'pendiente_visacion', 'label' => 'Pendiente de visación'],
        ['value' => 'observado', 'label' => 'Observado'],
        ['value' => 'pendiente_administracion', 'label' => 'Pendiente de Administración'],
        ['value' => 'aprobado', 'label' => 'Aprobado'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
        ['value' => 'cancelado', 'label' => 'Cancelado'],
        ['value' => 'importado_historico', 'label' => 'Importado histórico'],
    ];

    public const SERVICE_STATUS_OPTIONS = [
        ['value' => 'sin_gestion', 'label' => 'Sin gestión'],
        ['value' => 'cotizando', 'label' => 'Cotizando'],
        ['value' => 'cotizado', 'label' => 'Cotizado'],
        ['value' => 'confirmado', 'label' => 'Confirmado'],
        ['value' => 'ejecutado', 'label' => 'Ejecutado'],
        ['value' => 'cancelado', 'label' => 'Cancelado'],
    ];

    public const DTE_STATUS_OPTIONS = [
        ['value' => 'no_aplica', 'label' => 'No aplica'],
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'recibido', 'label' => 'Recibido'],
    ];

    public const PAYMENT_STATUS_OPTIONS = [
        ['value' => 'no_iniciado', 'label' => 'No iniciado'],
        ['value' => 'solicitado', 'label' => 'Solicitado'],
        ['value' => 'programado', 'label' => 'Programado'],
        ['value' => 'pagado', 'label' => 'Pagado'],
    ];

    public const ACTIVITY_TYPE_OPTIONS = [
        ['value' => 'salida_pedagogica', 'label' => 'Salida pedagógica'],
        ['value' => 'actividad_deportiva', 'label' => 'Actividad deportiva'],
        ['value' => 'actividad_institucional', 'label' => 'Actividad institucional'],
        ['value' => 'gestion_administrativa', 'label' => 'Gestión administrativa'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    public const TRANSPORT_MODE_OPTIONS = [
        ['value' => 'ida_vuelta', 'label' => 'Ida y vuelta'],
        ['value' => 'solo_ida', 'label' => 'Solo ida'],
        ['value' => 'solo_vuelta', 'label' => 'Solo vuelta'],
    ];

    protected $fillable = [
        'folio', 'requester_staff_id', 'requested_by_user_id', 'visor_user_id',
        'administration_user_id', 'created_by', 'updated_by', 'requester_name_snapshot',
        'requester_role_snapshot', 'requester_unit_snapshot', 'visor_name_snapshot',
        'activity_type', 'activity_name', 'course_subject', 'purpose', 'transport_date',
        'departure_time', 'return_time', 'origin', 'destination', 'transport_mode',
        'student_count', 'adult_count', 'passenger_count', 'reduced_mobility',
        'mobility_requirements', 'visible_observations', 'internal_observations',
        'approval_status', 'service_status', 'dte_status', 'payment_status', 'urgent',
        'legacy_imported', 'submitted_at', 'visor_reviewed_at', 'approved_at',
        'rejected_at', 'confirmed_at', 'executed_at', 'cancelled_at',
    ];

    protected $hidden = ['internal_observations'];

    protected $casts = [
        'transport_date' => 'date:Y-m-d',
        'student_count' => 'integer',
        'adult_count' => 'integer',
        'passenger_count' => 'integer',
        'reduced_mobility' => 'boolean',
        'urgent' => 'boolean',
        'legacy_imported' => 'boolean',
        'submitted_at' => 'datetime',
        'visor_reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'executed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function requesterStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requester_staff_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function visorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visor_user_id');
    }

    public function administrationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administration_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(OperationalTransferApproval::class)->orderBy('id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(OperationalTransferQuote::class)->orderByDesc('selected')->orderBy('amount');
    }

    public function operation(): HasOne
    {
        return $this->hasOne(OperationalTransferOperation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OperationalTransferDocument::class)->orderByDesc('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OperationalTransferLog::class)->orderByDesc('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->approval_status, ['borrador', 'observado'], true);
    }
}
