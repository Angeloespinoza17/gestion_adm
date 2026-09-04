<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteInstallationImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_path',
        'alt_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
        'preview_url',
    ];

    protected $hidden = [
        'image_path',
    ];

    public function siteInstallation(): BelongsTo
    {
        return $this->belongsTo(SiteInstallation::class);
    }

    public function getUrlAttribute(): string
    {
        return route('public.installations.gallery', [
            'siteInstallation' => $this->site_installation_id,
            'siteInstallationImage' => $this->id,
        ], false);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('api.admin.installations.gallery', [
            'siteInstallation' => $this->site_installation_id,
            'siteInstallationImage' => $this->id,
        ], false);
    }
}
