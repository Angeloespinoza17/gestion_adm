<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
        'responsible',
        'visit_date',
        'visit_time',
        'visit_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    public function dependency()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function checklistResponses()
    {
        return $this->hasMany(MaintenanceVisitChecklistResponse::class, 'maintenance_visit_id');
    }
}
