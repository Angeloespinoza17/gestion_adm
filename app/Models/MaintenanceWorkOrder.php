<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceWorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_key',
        'maintenance_dependency_id',
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

    public function dependency()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }
}
