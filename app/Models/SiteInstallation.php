<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteInstallation extends Model
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

    public const ICONS = [
        'buildings' => ['label' => 'Edificio', 'class' => 'bi-buildings'],
        'book' => ['label' => 'Aprendizaje', 'class' => 'bi-book'],
        'flower' => ['label' => 'Capilla y pastoral', 'class' => 'bi-flower1'],
        'trophy' => ['label' => 'Deporte', 'class' => 'bi-trophy'],
        'people' => ['label' => 'Comunidad', 'class' => 'bi-people'],
        'laptop' => ['label' => 'Tecnología', 'class' => 'bi-laptop'],
        'science' => ['label' => 'Ciencias', 'class' => 'bi-beaker'],
        'palette' => ['label' => 'Arte', 'class' => 'bi-palette'],
        'music' => ['label' => 'Música', 'class' => 'bi-music-note-beamed'],
        'heart' => ['label' => 'Bienestar', 'class' => 'bi-heart'],
        'tree' => ['label' => 'Áreas verdes', 'class' => 'bi-tree'],
    ];

    protected $fillable = [
        'title',
        'slug',
        'category',
        'summary',
        'body',
        'location_label',
        'capacity',
        'accessibility_notes',
        'features',
        'icon',
        'cover_image_path',
        'cover_image_alt',
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
        'capacity' => 'integer',
        'features' => 'array',
        'active' => 'boolean',
        'featured' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $hidden = [
        'cover_image_path',
    ];

    protected $appends = [
        'body_html',
        'capacity_label',
        'icon_class',
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
        return $this->hasMany(SiteInstallationImage::class)
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
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function getBodyHtmlAttribute(): ?string
    {
        return NewsPost::sanitizeHtml($this->body);
    }

    public function getCapacityLabelAttribute(): ?string
    {
        if (! $this->capacity) {
            return null;
        }

        $peopleLabel = $this->capacity === 1 ? 'persona' : 'personas';

        return 'Capacidad para '.number_format($this->capacity, 0, ',', '.').' '.$peopleLabel;
    }

    public function getIconClassAttribute(): string
    {
        return self::ICONS[$this->icon ?? 'buildings']['class'] ?? self::ICONS['buildings']['class'];
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return route('public.installations.cover', $this, false);
    }

    public function getPreviewCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return route('api.admin.installations.cover', $this, false);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('public.campus', [], false).'#'.$this->slug;
    }
}
