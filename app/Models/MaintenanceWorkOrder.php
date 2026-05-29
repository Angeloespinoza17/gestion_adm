<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceWorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
        'reported_at',
        'requested_by',
        'assigned_to',
        'priority',
        'status',
        'due_date',
        'description',
        'resolution_notes',
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
