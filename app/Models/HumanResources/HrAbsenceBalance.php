<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrAbsenceBalance extends HumanResourcesModel
{
    protected $table = 'hr_absence_balances';

    protected $casts = [
        'year' => 'integer',
        'administrative_entitlement' => 'decimal:2',
        'administrative_adjustment' => 'decimal:2',
        'compensatory_entitlement' => 'decimal:2',
        'compensatory_adjustment' => 'decimal:2',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(HrAbsenceBalanceMovement::class, 'balance_id')->orderByDesc('effective_on');
    }
}
