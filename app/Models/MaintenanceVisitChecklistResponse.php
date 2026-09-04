<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MaintenanceVisitChecklistResponse extends Model
{
    use HasFactory;

    protected $appends = [
        'photo_url',
        'photo_urls',
    ];

    protected $fillable = [
        'maintenance_visit_id',
        'maintenance_checklist_item_id',
        'review_status',
        'observations',
        'finding_description',
        'photo_reference',
        'work_order_id',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_reference) {
            return null;
        }

        $url = Storage::disk('public')->url($this->photo_reference);
        $parts = parse_url((string) $url);
        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $url;
    }

    /** @return array<int, string> */
    public function getPhotoUrlsAttribute(): array
    {
        $urls = collect([$this->photo_url]);

        if ($this->relationLoaded('photos')) {
            $urls = $urls->concat($this->photos->pluck('url'));
        }

        return $urls
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function visit()
    {
        return $this->belongsTo(MaintenanceVisit::class, 'maintenance_visit_id');
    }

    public function item()
    {
        return $this->belongsTo(MaintenanceChecklistItem::class, 'maintenance_checklist_item_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'work_order_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(
            MaintenanceEvidencePhoto::class,
            'maintenance_visit_checklist_response_id'
        )->oldest('id');
    }
}
