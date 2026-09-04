<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MaintenanceEvidencePhoto extends Model
{
    use HasFactory;

    protected $appends = [
        'url',
    ];

    protected $fillable = [
        'maintenance_visit_checklist_response_id',
        'maintenance_work_order_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function getUrlAttribute(): ?string
    {
        $path = str_replace('\\', '/', trim((string) $this->path));

        $isManagedPath = str_starts_with($path, 'maintenance/visits/')
            || str_starts_with($path, 'maintenance/work-orders/');

        if (! $isManagedPath || str_contains($path, '../')) {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $url;
    }

    public function checklistResponse(): BelongsTo
    {
        return $this->belongsTo(
            MaintenanceVisitChecklistResponse::class,
            'maintenance_visit_checklist_response_id'
        );
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
