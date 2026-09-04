<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Testimonial extends Model
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

    protected $fillable = [
        'quote',
        'author_name',
        'author_role',
        'image_path',
        'external_image_url',
        'image_alt',
        'status',
        'active',
        'featured',
        'sort_order',
        'published_at',
        'consent_confirmed_at',
        'consent_confirmed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
        'consent_confirmed_at' => 'datetime',
    ];

    protected $appends = [
        'image_url',
        'preview_image_url',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function consentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_confirmed_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('active', true)
            ->whereNotNull('consent_confirmed_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrderedForPublic(Builder $query): Builder
    {
        return $query
            ->orderByDesc('featured')
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return route('public.testimonials.image', $this, false);
        }

        return self::resolveExternalAsset($this->external_image_url);
    }

    public function getPreviewImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return route('api.admin.testimonials.image', $this, false);
        }

        return self::resolveExternalAsset($this->external_image_url);
    }

    private static function resolveExternalAsset(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '/'])) {
            return $value;
        }

        return asset($value);
    }
}
