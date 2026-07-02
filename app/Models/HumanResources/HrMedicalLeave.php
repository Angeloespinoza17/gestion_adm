<?php

namespace App\Models\HumanResources;

use App\Models\Remuneration\RemunerationMovement;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrMedicalLeave extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_medical_leaves';

    protected $casts = [
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'days' => 'decimal:2',
        'affects_payroll' => 'boolean',
        'metadata' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class);
    }

    public function documentControl(): BelongsTo
    {
        return $this->belongsTo(HrDocumentControl::class, 'document_control_id');
    }

    public function payrollMovement(): BelongsTo
    {
        return $this->belongsTo(RemunerationMovement::class, 'payroll_movement_id');
    }
}
