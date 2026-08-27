<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipControl extends RemunerationModel
{
    protected $casts = [
        'expected_amount' => 'integer',
        'actual_amount' => 'integer',
        'difference' => 'integer',
        'details' => 'array',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslip::class, 'payslip_id');
    }
}
