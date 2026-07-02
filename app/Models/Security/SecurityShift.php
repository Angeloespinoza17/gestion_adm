<?php

namespace App\Models\Security;

use App\Models\MaintenanceDependency;
use App\Models\Staff;
use App\Models\User;
use App\Services\Security\SecurityShiftScheduleService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityShift extends Model
{
    use HasFactory;

    public const STATUS_PROGRAMADO = 'programado';
    public const STATUS_EN_CURSO = 'en_curso';
    public const STATUS_FINALIZADO = 'finalizado';
    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_OPTIONS = [
        ['value' => self::STATUS_PROGRAMADO, 'label' => 'Programado'],
        ['value' => self::STATUS_EN_CURSO, 'label' => 'En curso'],
        ['value' => self::STATUS_FINALIZADO, 'label' => 'Finalizado'],
        ['value' => self::STATUS_CANCELADO, 'label' => 'Cancelado'],
    ];

    public const SCHEDULE_SINGLE = 'single';
    public const SCHEDULE_WEEKLY = 'weekly';

    public const SCHEDULE_OPTIONS = [
        ['value' => self::SCHEDULE_SINGLE, 'label' => 'Fecha específica'],
        ['value' => self::SCHEDULE_WEEKLY, 'label' => 'Días de la semana'],
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

    protected $fillable = [
        'staff_id',
        'schedule_type',
        'maintenance_dependency_id',
        'created_by',
        'updated_by',
        'started_by_user_id',
        'closed_by_user_id',
        'parent_shift_id',
        'generated_for_date',
        'scheduled_start_at',
        'scheduled_end_at',
        'weekdays',
        'template_start_time',
        'template_end_time',
        'recurrence_starts_on',
        'recurrence_ends_on',
        'started_at',
        'ended_at',
        'status',
        'coverage_label',
        'general_observations',
        'closing_observations',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'generated_for_date' => 'date',
        'weekdays' => 'array',
        'recurrence_starts_on' => 'date',
        'recurrence_ends_on' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $appends = [
        'is_weekly_template',
        'is_generated_shift',
        'weekday_labels',
        'next_occurrence_at',
        'next_occurrence_end_at',
        'schedule_summary',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function parentShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_shift_id');
    }

    public function generatedShifts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_shift_id')->latest('generated_for_date');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(SecurityRound::class)->orderBy('round_number');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class)->latest('id');
    }

    public function getIsWeeklyTemplateAttribute(): bool
    {
        return $this->schedule_type === self::SCHEDULE_WEEKLY && !$this->parent_shift_id;
    }

    public function getIsGeneratedShiftAttribute(): bool
    {
        return (int) $this->parent_shift_id > 0;
    }

    public function getWeekdayLabelsAttribute(): array
    {
        $labels = collect(self::WEEKDAY_OPTIONS)->pluck('label', 'value');

        return collect($this->weekdays ?: [])
            ->map(fn ($value) => $labels[$value] ?? $value)
            ->values()
            ->all();
    }

    public function getNextOccurrenceAtAttribute(): ?string
    {
        $next = app(SecurityShiftScheduleService::class)->nextOccurrence($this);

        return $next?->format('Y-m-d H:i');
    }

    public function getNextOccurrenceEndAtAttribute(): ?string
    {
        $next = app(SecurityShiftScheduleService::class)->nextOccurrenceEnd($this);

        return $next?->format('Y-m-d H:i');
    }

    public function getScheduleSummaryAttribute(): string
    {
        if ($this->is_weekly_template) {
            $days = implode(', ', $this->weekday_labels);
            $from = $this->template_start_time ? substr((string) $this->template_start_time, 0, 5) : '--:--';
            $to = $this->template_end_time ? substr((string) $this->template_end_time, 0, 5) : '--:--';

            return trim("Semanal: {$days} · {$from} a {$to}");
        }

        if ($this->is_generated_shift) {
            return 'Instancia generada desde turno semanal';
        }

        $start = $this->scheduled_start_at instanceof Carbon ? $this->scheduled_start_at->format('d/m/Y H:i') : '-';
        $end = $this->scheduled_end_at instanceof Carbon ? $this->scheduled_end_at->format('d/m/Y H:i') : '-';

        return "Turno puntual: {$start} a {$end}";
    }
}
