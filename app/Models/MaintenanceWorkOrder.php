<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MaintenanceWorkOrder extends Model
{
    use HasFactory;

    protected $appends = [
        'photo_url',
    ];

    protected $fillable = [
        'source_key',
        'maintenance_dependency_id',
        'technical_area_id',
        'inventory_item_id',
        'dependency_component',
        'location_code',
        'location_distribution',
        'location_sector',
        'location_name',
        'location_usage',
        'reported_at',
        'requested_by',
        'assigned_to',
        'priority',
        'status',
        'due_date',
        'description',
        'resolution_notes',
        'photo_reference',
    ];

    protected $casts = [
        'reported_at' => 'date',
        'due_date' => 'date',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_reference) {
            return null;
        }

        $url = Storage::disk('public')->url($this->photo_reference);

        // Evita problemas cuando APP_URL no coincide con el host/protocolo real.
        // Si el Storage devuelve URL absoluta, la convertimos a ruta relativa.
        $parts = parse_url((string) $url);
        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }

    public function dependency()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function technicalArea()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'technical_area_id');
    }
}
