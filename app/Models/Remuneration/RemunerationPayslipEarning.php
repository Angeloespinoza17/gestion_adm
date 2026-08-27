<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingFundingSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipEarning extends RemunerationModel
{
    protected $casts = ['is_imponible' => 'boolean', 'amount' => 'integer', 'metadata' => 'array'];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslip::class, 'payslip_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }
}
