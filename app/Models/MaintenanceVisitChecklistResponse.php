<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MaintenanceVisitChecklistResponse extends Model
{
    use HasFactory;

    protected $appends = [
        'photo_url',
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
        if (!$this->photo_reference) {
            return null;
        }

        $url = Storage::disk('public')->url($this->photo_reference);
        $parts = parse_url((string) $url);
        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
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
}

