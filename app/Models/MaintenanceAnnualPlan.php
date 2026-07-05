<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceAnnualPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
        'item_type',
        'inventory_item_id',
        'technical_area_id',
        'component_name',
        'planned_year',
        'planned_month',
        'category',
        'responsible',
        'frequency',
        'status',
        'title',
        'description',
        'scheduled_date',
        'completed_date',
        'last_maintenance_date',
        'alert_days',
        'alert_enabled',
        'notes',
    ];

    protected $casts = [
        'planned_year' => 'integer',
        'planned_month' => 'integer',
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'last_maintenance_date' => 'date',
        'alert_days' => 'integer',
        'alert_enabled' => 'boolean',
    ];

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
