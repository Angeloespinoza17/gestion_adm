<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsPost extends Model
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
        'excerpt',
        'body',
        'category',
        'author_name',
        'author_role',
        'image_path',
        'external_image_url',
        'image_alt',
        'header_image_url',
        'author_image_url',
        'author_image_alt',
        'reading_minutes',
        'comments_label',
        'detail_categories',
        'toc_items',
        'quote_text',
        'quote_author',
        'secondary_section_title',
        'secondary_image_url',
        'secondary_image_alt',
        'secondary_image_caption',
        'secondary_image_position',
        'feature_points',
        'comparison_cards',
        'key_principles',
        'info_box_icon',
        'info_box_title',
        'info_box_text',
        'future_trends',
        'tags',
        'share_enabled',
        'status',
        'featured',
        'sort_order',
        'views_count',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'share_enabled' => 'boolean',
        'sort_order' => 'integer',
        'views_count' => 'integer',
        'reading_minutes' => 'integer',
        'detail_categories' => 'array',
        'toc_items' => 'array',
        'feature_points' => 'array',
        'comparison_cards' => 'array',
        'key_principles' => 'array',
        'future_trends' => 'array',
        'tags' => 'array',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'body_html',
        'image_url',
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
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrderedForPublic(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return route('public.news.image', $this, false);
        }

        if (!$this->external_image_url) {
            return null;
        }

        if (Str::startsWith($this->external_image_url, ['http://', 'https://', '/'])) {
            return $this->external_image_url;
        }

        return asset($this->external_image_url);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('public.news.show', $this, false);
    }

    public function getBodyHtmlAttribute(): ?string
    {
        return self::sanitizeHtml($this->body);
    }

    public static function sanitizeHtml(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|svg|math)[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><h4><a>');
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+(style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        $html = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches) {
            if (! preg_match('/href\s*=\s*("|\')([^"\']+)\1/i', $matches[1], $hrefMatch)) {
                return '<a>';
            }

            $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES, 'UTF-8'));

            if (! preg_match('/^(https?:\/\/|mailto:|\/)/i', $href)) {
                return '<a>';
            }

            return '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer">';
        }, $html);

        return trim($html) ?: null;
    }
}
