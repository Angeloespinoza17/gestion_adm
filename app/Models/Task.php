<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const PRIORITY_URGENT = 'urgente';
    public const PRIORITY_HIGH = 'alta';
    public const PRIORITY_MEDIUM = 'media';
    public const PRIORITY_LOW = 'baja';

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_IN_PROGRESS = 'en_progreso';
    public const STATUS_BLOCKED = 'bloqueada';
    public const STATUS_IN_REVIEW = 'en_revision';
    public const STATUS_COMPLETED = 'completada';
    public const STATUS_CANCELLED = 'cancelada';

    public const PRIORITY_OPTIONS = [
        ['value' => self::PRIORITY_URGENT, 'label' => 'Urgente'],
        ['value' => self::PRIORITY_HIGH, 'label' => 'Alta'],
        ['value' => self::PRIORITY_MEDIUM, 'label' => 'Media'],
        ['value' => self::PRIORITY_LOW, 'label' => 'Baja'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => self::STATUS_PENDING, 'label' => 'Pendiente'],
        ['value' => self::STATUS_IN_PROGRESS, 'label' => 'En progreso'],
        ['value' => self::STATUS_BLOCKED, 'label' => 'Bloqueada'],
        ['value' => self::STATUS_IN_REVIEW, 'label' => 'En revisión'],
        ['value' => self::STATUS_COMPLETED, 'label' => 'Completada'],
        ['value' => self::STATUS_CANCELLED, 'label' => 'Cancelada'],
    ];

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'stakeholder',
        'due_date',
        'owner_user_id',
        'created_by_user_id',
        'parent_task_id',
        'auto_complete_parent_on_subtasks_done',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'auto_complete_parent_on_subtasks_done' => 'boolean',
        'completed_at' => 'datetime:Y-m-d H:i',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'priority_label',
        'status_label',
        'subtasks_progress',
        'is_overdue',
        'is_due_soon',
    ];

    public static function priorityValues(): array
    {
        return array_column(self::PRIORITY_OPTIONS, 'value');
    }

    public static function statusValues(): array
    {
        return array_column(self::STATUS_OPTIONS, 'value');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id')->orderBy('sort_order')->orderBy('id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class)->latest();
    }

    public function getPriorityLabelAttribute(): string
    {
        return collect(self::PRIORITY_OPTIONS)->firstWhere('value', $this->priority)['label'] ?? (string) ($this->priority ?? '');
    }

    public function getStatusLabelAttribute(): string
    {
        return collect(self::STATUS_OPTIONS)->firstWhere('value', $this->status)['label'] ?? (string) ($this->status ?? '');
    }

    public function getSubtasksProgressAttribute(): array
    {
        if (!$this->relationLoaded('subtasks')) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0];
        }

        $total = $this->subtasks->count();
        $completed = $this->subtasks->where('status', self::STATUS_COMPLETED)->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isBefore(today())
            && !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    public function getIsDueSoonAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->betweenIncluded(today(), today()->addDays(7))
            && !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }
}
