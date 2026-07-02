<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DependencyReservation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_APPROVED = 'aprobada';
    public const STATUS_REJECTED = 'rechazada';
    public const STATUS_CANCELLED = 'cancelada';
    public const STATUS_FINISHED = 'finalizada';

    public const REPETITION_TYPES = ['none', 'daily', 'weekly', 'monthly'];

    protected $fillable = [
        'maintenance_dependency_id',
        'staff_id',
        'department_id',
        'title',
        'activity',
        'starts_at',
        'ends_at',
        'repetition_type',
        'repetition_until',
        'series_uuid',
        'status',
        'observations',
        'estimated_attendees',
        'special_requirements',
        'created_by',
        'approved_by',
        'cancelled_by',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $appends = [
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'event_color',
    ];

    protected $casts = [
        'starts_at' => 'datetime:Y-m-d H:i:s',
        'ends_at' => 'datetime:Y-m-d H:i:s',
        'repetition_until' => 'date:Y-m-d',
        'estimated_attendees' => 'integer',
        'approved_at' => 'datetime:Y-m-d H:i:s',
        'rejected_at' => 'datetime:Y-m-d H:i:s',
        'cancelled_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(DependencyReservationCollaborator::class)->orderBy('id');
    }

    public function getStartDateAttribute(): ?string
    {
        return $this->starts_at?->format('Y-m-d');
    }

    public function getStartTimeAttribute(): ?string
    {
        return $this->starts_at?->format('H:i');
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->ends_at?->format('Y-m-d');
    }

    public function getEndTimeAttribute(): ?string
    {
        return $this->ends_at?->format('H:i');
    }

    public function getEventColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '#f1b44c',
            self::STATUS_REJECTED => '#f46a6a',
            self::STATUS_CANCELLED => '#6c757d',
            self::STATUS_FINISHED => '#50a5f1',
            default => $this->dependency?->calendar_color ?: '#34c38f',
        };
    }

    public function scopeActiveWindow(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_CANCELLED]);
    }

    public function scopeOverlapping(
        Builder $query,
        int $dependencyId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreId = null
    ): Builder {
        return $query
            ->where('maintenance_dependency_id', $dependencyId)
            ->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_CANCELLED])
            ->when($ignoreId, fn (Builder $builder) => $builder->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }
}
