<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingCostCenter;
use App\Models\Accounting\AccountingFundingSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayrollDistribution extends RemunerationModel
{
    protected $casts = [
        'percentage' => 'decimal:6',
        'gross_amount' => 'integer',
        'employer_contribution_amount' => 'integer',
        'deduction_amount' => 'integer',
        'net_amount' => 'integer',
        'total_cost_amount' => 'integer',
        'snapshot' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayroll::class, 'payroll_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }
}
