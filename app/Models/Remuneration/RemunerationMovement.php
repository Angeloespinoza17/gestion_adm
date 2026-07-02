<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingCostCenter;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Contract;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationMovement extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'amount' => 'integer',
        'quantity' => 'decimal:4',
        'unit_value' => 'integer',
        'affects_days' => 'decimal:2',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'metadata' => 'array',
        'approved_at' => 'datetime:Y-m-d H:i',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class, 'period_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RemunerationConcept::class, 'concept_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
