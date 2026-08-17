<?php

namespace App\Models\HumanResources;

use App\Models\PermissionRequest;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrAbsenceRecord extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_absence_records';

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'quantity' => 'decimal:2',
        'affects_attendance' => 'boolean',
        'affects_payroll' => 'boolean',
        'metadata' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function medicalLeave(): BelongsTo
    {
        return $this->belongsTo(HrMedicalLeave::class);
    }

    public function balanceMovements(): HasMany
    {
        return $this->hasMany(HrAbsenceBalanceMovement::class, 'absence_record_id');
    }
}
