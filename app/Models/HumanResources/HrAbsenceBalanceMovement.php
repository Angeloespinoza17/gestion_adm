<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAbsenceBalanceMovement extends HumanResourcesModel
{
    protected $table = 'hr_absence_balance_movements';

    public const UPDATED_AT = null;

    protected $casts = [
        'quantity' => 'decimal:2',
        'effective_on' => 'date:Y-m-d',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(HrAbsenceBalance::class, 'balance_id');
    }

    public function absenceRecord(): BelongsTo
    {
        return $this->belongsTo(HrAbsenceRecord::class, 'absence_record_id');
    }
}
