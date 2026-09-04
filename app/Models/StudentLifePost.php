<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudentLifePost extends Model
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
        'title',
        'slug',
        'category',
        'summary',
        'body',
        'cover_image_path',
        'external_cover_image_url',
        'cover_image_alt',
        'event_date',
        'meta_title',
        'meta_description',
        'status',
        'active',
        'featured',
        'sort_order',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'body_html',
        'cover_image_url',
        'preview_cover_image_url',
        'public_url',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(StudentLifePostImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrderedForPublic(Builder $query): Builder
    {
        return $query
            ->orderByDesc('featured')
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('event_date')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function getBodyHtmlAttribute(): ?string
    {
        return NewsPost::sanitizeHtml($this->body);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if ($this->cover_image_path) {
            return route('public.student-life.cover', $this, false);
        }

        return self::resolveExternalAsset($this->external_cover_image_url);
    }

    public function getPreviewCoverImageUrlAttribute(): ?string
    {
        if ($this->cover_image_path) {
            return route('api.admin.student-life.cover', $this, false);
        }

        return self::resolveExternalAsset($this->external_cover_image_url);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('public.student-life.show', ['slug' => $this->slug], false);
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
