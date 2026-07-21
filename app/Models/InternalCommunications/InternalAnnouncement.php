<?php

namespace App\Models\InternalCommunications;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalAnnouncement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_IMPORTANT = 'important';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_NORMAL,
        self::PRIORITY_IMPORTANT,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'title',
        'body',
        'category',
        'priority',
        'status',
        'pinned',
        'audience_all',
        'requires_ack',
        'published_at',
        'expires_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'audience_all' => 'boolean',
        'requires_ack' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'internal_announcement_role')->withTimestamps();
    }

    public function reads(): HasMany
    {
        return $this->hasMany(InternalAnnouncementRead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->where(function (Builder $builder) use ($now) {
                $builder
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            });
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query->published();
        }

        $roleIds = $user->roles()->pluck('roles.id')->all();

        return $query
            ->published()
            ->where(function (Builder $builder) use ($roleIds) {
                $builder->where('audience_all', true);

                if ($roleIds !== []) {
                    $builder->orWhereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.id', $roleIds));
                }
            });
    }

    public function priorityLabel(): string
    {
        return [
            self::PRIORITY_URGENT => 'Urgente',
            self::PRIORITY_IMPORTANT => 'Importante',
            self::PRIORITY_NORMAL => 'Normal',
        ][$this->priority] ?? 'Normal';
    }
}
