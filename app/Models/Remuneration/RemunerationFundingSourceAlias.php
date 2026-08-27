<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingFundingSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationFundingSourceAlias extends RemunerationModel
{
    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }
}
