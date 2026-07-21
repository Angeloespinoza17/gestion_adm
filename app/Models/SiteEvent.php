<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteEvent extends Model
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
        'summary',
        'body',
        'category',
        'location',
        'starts_at',
        'ends_at',
        'external_url',
        'header_image_url',
        'hero_image_url',
        'hero_image_alt',
        'highlights',
        'schedule_items',
        'gallery_intro',
        'gallery_images',
        'registration_enabled',
        'registration_title',
        'registration_button_label',
        'registration_url',
        'organizer_name',
        'organizer_position',
        'organizer_description',
        'organizer_email',
        'organizer_phone',
        'organizer_image_url',
        'organizer_image_alt',
        'status',
        'featured',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'registration_enabled' => 'boolean',
        'highlights' => 'array',
        'schedule_items' => 'array',
        'gallery_images' => 'array',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $appends = [
        'body_html',
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeOrderedForPublic(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    public function getPublicUrlAttribute(): string
    {
        return route('public.events.show', $this, false);
    }

    public function getBodyHtmlAttribute(): ?string
    {
        return NewsPost::sanitizeHtml($this->body);
    }
}
