<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteOrganization extends Model
{
    public const TYPE_CGPA = 'cgpa';

    public const TYPE_CDE = 'cde';

    public const TYPES = [self::TYPE_CGPA, self::TYPE_CDE];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    protected $fillable = [
        'type',
        'year',
        'name',
        'summary',
        'status',
        'active',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(SiteOrganizationMember::class)
            ->with('role:id,organization_type,name,section,sort_order,active')
            ->orderBy('sort_order')
            ->orderBy('section')
            ->orderBy('id');
    }

    public function publicMembers(): HasMany
    {
        return $this->members()->where('public_name_authorized', true);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
