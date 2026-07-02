<?php

namespace App\Models\HumanResources;

use App\Models\Contract;
use App\Models\Department;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrWorkloadAssignment extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_workload_assignments';

    protected $casts = [
        'contracted_hours' => 'decimal:2',
        'classroom_hours' => 'decimal:2',
        'non_classroom_hours' => 'decimal:2',
        'coordination_hours' => 'decimal:2',
        'pie_hours' => 'decimal:2',
        'sep_hours' => 'decimal:2',
        'replacement_hours' => 'decimal:2',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'metadata' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function replacementStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'replacement_staff_id');
    }
}
