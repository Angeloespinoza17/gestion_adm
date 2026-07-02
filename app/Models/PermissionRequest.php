<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionRequest extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'ingresado', 'label' => 'Ingresado'],
        ['value' => 'pendiente_jefatura', 'label' => 'Pendiente de revisión por jefatura'],
        ['value' => 'pendiente_direccion', 'label' => 'Pendiente de revisión por Dirección'],
        ['value' => 'pendiente_rrhh', 'label' => 'Pendiente de revisión por RRHH / Administración'],
        ['value' => 'aprobado', 'label' => 'Aprobado'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
        ['value' => 'observado', 'label' => 'Observado'],
        ['value' => 'cancelado', 'label' => 'Cancelado'],
        ['value' => 'anulado', 'label' => 'Anulado'],
        ['value' => 'ejecutado', 'label' => 'Ejecutado'],
    ];

    public const ATTENDANCE_STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'ausencia_autorizada', 'label' => 'Ausencia autorizada'],
        ['value' => 'pendiente_regularizacion', 'label' => 'Pendiente de regularización'],
        ['value' => 'ausencia_no_autorizada', 'label' => 'Ausencia no autorizada'],
        ['value' => 'no_aplica', 'label' => 'No aplica'],
    ];

    public const PAYROLL_STATUS_OPTIONS = [
        ['value' => 'no_aplica', 'label' => 'No aplica'],
        ['value' => 'por_informar', 'label' => 'Por informar'],
        ['value' => 'informado', 'label' => 'Informado a remuneraciones'],
        ['value' => 'descuento_pendiente', 'label' => 'Descuento pendiente'],
    ];

    public const STEP_OPTIONS = [
        ['value' => 'manager', 'label' => 'Jefatura directa'],
        ['value' => 'direction', 'label' => 'Dirección'],
        ['value' => 'hr', 'label' => 'RRHH / Administración'],
    ];

    protected $fillable = [
        'staff_id',
        'requested_by_user_id',
        'created_by',
        'updated_by',
        'direct_manager_user_id',
        'permission_type_id',
        'cargo_name',
        'direct_manager_name',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'duration_hours',
        'duration_days',
        'duration_label',
        'is_full_day',
        'is_half_day',
        'with_pay',
        'affects_salary',
        'affects_attendance',
        'requires_replacement',
        'reason',
        'description',
        'employee_observations',
        'visible_observations',
        'internal_observations',
        'status',
        'current_step',
        'urgency',
        'retroactive',
        'attendance_status',
        'payroll_status',
        'salary_discount_hours',
        'salary_discount_days',
        'requires_regularization',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'executed_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'duration_hours' => 'decimal:2',
        'duration_days' => 'decimal:2',
        'is_full_day' => 'boolean',
        'is_half_day' => 'boolean',
        'with_pay' => 'boolean',
        'affects_salary' => 'boolean',
        'affects_attendance' => 'boolean',
        'requires_replacement' => 'boolean',
        'urgency' => 'boolean',
        'retroactive' => 'boolean',
        'requires_regularization' => 'boolean',
        'salary_discount_hours' => 'decimal:2',
        'salary_discount_days' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function directManagerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direct_manager_user_id');
    }

    public function permissionType(): BelongsTo
    {
        return $this->belongsTo(PermissionType::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'permission_request_department')->withTimestamps();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PermissionRequestApproval::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PermissionRequestDocument::class)->orderByDesc('id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(PermissionRequestReplacement::class)->orderBy('start_datetime');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PermissionRequestLog::class)->orderByDesc('id');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(PermissionRequestWatcher::class)->orderBy('id');
    }

    public function isFinalStatus(): bool
    {
        return in_array($this->status, ['aprobado', 'rechazado', 'cancelado', 'anulado', 'ejecutado'], true);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['borrador', 'ingresado', 'observado'], true);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query
            ->whereYear('start_date', $year)
            ->whereMonth('start_date', $month);
    }
}
