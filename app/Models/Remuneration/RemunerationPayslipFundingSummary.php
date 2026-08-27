<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingFundingSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipFundingSummary extends RemunerationModel
{
    protected $casts = [
        'taxable_earnings' => 'integer',
        'non_taxable_earnings' => 'integer',
        'gross_earnings' => 'integer',
        'legal_deductions' => 'integer',
        'other_deductions' => 'integer',
        'net_amount' => 'integer',
        'employer_contributions' => 'integer',
        'total_cost' => 'integer',
        'reconciliation_difference' => 'integer',
        'calculation_detail' => 'array',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslip::class, 'payslip_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }
}
