<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_READ,
        self::STATUS_RESPONDED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'source_page',
        'ip_address',
        'user_agent',
        'read_at',
        'responded_at',
        'internal_notes',
        'handled_by',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
    ];

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '<>', self::STATUS_ARCHIVED);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'Nuevo',
            self::STATUS_READ => 'Leído',
            self::STATUS_RESPONDED => 'Respondido',
            self::STATUS_ARCHIVED => 'Archivado',
            default => $this->status ?: '-',
        };
    }
}
