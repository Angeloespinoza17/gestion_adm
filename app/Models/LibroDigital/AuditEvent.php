<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditEvent extends LibroDigitalModel
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $table = 'lcd_audit_events';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Libro Digital audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Libro Digital audit events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_staff_id');
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
