<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemunerationPayslipDiscount extends RemunerationModel
{
    protected $casts = ['amount' => 'integer', 'metadata' => 'array'];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslip::class, 'payslip_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RemunerationPayslipDiscountAllocation::class, 'discount_id')->orderBy('id');
    }
}
