<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceAnnualPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_dependency_id',
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
        'notes',
    ];

    protected $casts = [
        'planned_year' => 'integer',
        'planned_month' => 'integer',
        'scheduled_date' => 'date',
        'completed_date' => 'date',
    ];

    public function dependency()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }
}

