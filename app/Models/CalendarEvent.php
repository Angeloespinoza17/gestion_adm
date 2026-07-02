<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const KIND_SINGLE = 'single';
    public const KIND_SERIES_MASTER = 'series_master';
    public const KIND_OCCURRENCE = 'occurrence';
    public const KIND_PROCESS = 'process';
    public const KIND_STAGE = 'stage';

    public const KIND_OPTIONS = [
        ['value' => self::KIND_SINGLE, 'label' => 'Fecha única'],
        ['value' => self::KIND_SERIES_MASTER, 'label' => 'Serie recurrente'],
        ['value' => self::KIND_OCCURRENCE, 'label' => 'Ocurrencia'],
        ['value' => self::KIND_PROCESS, 'label' => 'Proceso'],
        ['value' => self::KIND_STAGE, 'label' => 'Etapa'],
    ];

    public const PRIORITY_OPTIONS = [
        ['value' => 'baja', 'label' => 'Baja'],
        ['value' => 'media', 'label' => 'Media'],
        ['value' => 'alta', 'label' => 'Alta'],
        ['value' => 'critica', 'label' => 'Crítica'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'en_preparacion', 'label' => 'En preparación'],
        ['value' => 'en_revision', 'label' => 'En revisión'],
        ['value' => 'completado', 'label' => 'Completado'],
        ['value' => 'enviado', 'label' => 'Enviado'],
        ['value' => 'declarado', 'label' => 'Declarado'],
        ['value' => 'vencido', 'label' => 'Vencido'],
        ['value' => 'no_aplica', 'label' => 'No aplica'],
        ['value' => 'archivado', 'label' => 'Archivado'],
    ];

    public const TERMINAL_STATUSES = [
        'completado',
        'enviado',
        'declarado',
        'no_aplica',
        'archivado',
    ];

    public const RECURRENCE_FREQUENCY_OPTIONS = [
        ['value' => 'none', 'label' => 'Evento único'],
        ['value' => 'daily', 'label' => 'Diaria'],
        ['value' => 'weekly', 'label' => 'Semanal'],
        ['value' => 'monthly', 'label' => 'Mensual'],
        ['value' => 'yearly', 'label' => 'Anual'],
        ['value' => 'custom', 'label' => 'Personalizada'],
    ];

    public const WEEKDAY_OPTIONS = [
        ['value' => 'Monday', 'label' => 'Lunes'],
        ['value' => 'Tuesday', 'label' => 'Martes'],
        ['value' => 'Wednesday', 'label' => 'Miércoles'],
        ['value' => 'Thursday', 'label' => 'Jueves'],
        ['value' => 'Friday', 'label' => 'Viernes'],
        ['value' => 'Saturday', 'label' => 'Sábado'],
        ['value' => 'Sunday', 'label' => 'Domingo'],
    ];

    public const REMINDER_TYPE_OPTIONS = [
        ['value' => 'before', 'label' => 'Antes del vencimiento'],
        ['value' => 'same_day', 'label' => 'El mismo día'],
        ['value' => 'after_overdue', 'label' => 'Después del vencimiento'],
        ['value' => 'fixed_date', 'label' => 'Fecha específica'],
    ];

    public const PARTICIPANT_ROLE_OPTIONS = [
        ['value' => 'participant', 'label' => 'Participante'],
        ['value' => 'informed', 'label' => 'Informado'],
    ];

    protected $fillable = [
        'title',
        'description',
        'process_type_id',
        'institution_id',
        'department_id',
        'responsible_user_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'priority',
        'status',
        'requires_submission',
        'requires_payment',
        'requires_signature',
        'requires_review',
        'requires_approval',
        'is_recurring',
        'auto_generate_occurrences',
        'recurrence_rule',
        'recurrence_group_id',
        'parent_event_id',
        'event_kind',
        'stage_key',
        'stage_order',
        'is_exception',
        'external_url',
        'internal_observations',
        'created_by',
        'updated_by',
        'completed_by',
        'completed_at',
        'archived_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'requires_submission' => 'boolean',
        'requires_payment' => 'boolean',
        'requires_signature' => 'boolean',
        'requires_review' => 'boolean',
        'requires_approval' => 'boolean',
        'is_recurring' => 'boolean',
        'auto_generate_occurrences' => 'boolean',
        'recurrence_rule' => 'array',
        'is_exception' => 'boolean',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'effective_status',
        'is_overdue',
        'due_date',
        'calendar_color',
        'is_terminal',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function processType(): BelongsTo
    {
        return $this->belongsTo(CalendarProcessType::class, 'process_type_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(CalendarInstitution::class, 'institution_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_event_id')
            ->orderBy('stage_order')
            ->orderBy('start_date')
            ->orderBy('start_time');
    }

    public function eventUsers(): HasMany
    {
        return $this->hasMany(CalendarEventUser::class)->orderBy('id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_event_users')
            ->withPivot(['id', 'role_in_event'])
            ->withTimestamps();
    }

    public function participants(): BelongsToMany
    {
        return $this->users()->wherePivot('role_in_event', 'participant');
    }

    public function informedUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('role_in_event', 'informed');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarEventReminder::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CalendarEventAttachment::class)->latest('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CalendarEventLog::class)->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getIsTerminalAttribute(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true)
            || $this->archived_at !== null;
    }

    public function getDueDateAttribute(): ?string
    {
        $date = $this->end_date ?: $this->start_date;

        return $date instanceof Carbon ? $date->format('Y-m-d') : null;
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_terminal || !$this->due_date) {
            return false;
        }

        return $this->due_date < now(config('app.timezone'))->toDateString();
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'archivado' || $this->archived_at !== null) {
            return 'archivado';
        }

        if ($this->is_overdue && !in_array($this->status, ['completado', 'enviado', 'declarado', 'no_aplica'], true)) {
            return 'vencido';
        }

        return $this->status;
    }

    public function getCalendarColorAttribute(): string
    {
        if ($this->effective_status === 'vencido') {
            return '#dc3545';
        }

        if ($this->priority === 'critica') {
            return '#d63384';
        }

        if ($this->priority === 'alta') {
            return '#fd7e14';
        }

        if ($this->processType?->color) {
            return $this->processType->color;
        }

        return '#0d6efd';
    }
}
