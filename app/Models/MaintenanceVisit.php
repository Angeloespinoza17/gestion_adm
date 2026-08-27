<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
        'responsible',
        'responsible_staff_id',
        'planning_batch_id',
        'visit_date',
        'visit_time',
        'visit_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    public function planningBatch(): BelongsTo
    {
        return $this->belongsTo(MaintenanceVisitPlanningBatch::class, 'planning_batch_id');
    }

    public function checklistResponses()
    {
        return $this->hasMany(MaintenanceVisitChecklistResponse::class, 'maintenance_visit_id');
    }
}
