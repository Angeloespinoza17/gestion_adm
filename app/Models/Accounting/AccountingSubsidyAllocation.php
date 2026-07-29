<?php

namespace App\Models\Accounting;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSubsidyAllocation extends AccountingModel
{
    protected $casts = [
        'enrollment' => 'decimal:4',
        'attendance_average' => 'decimal:4',
        'use_factor' => 'decimal:4',
        'amount' => 'decimal:2',
        'source_payload' => 'array',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(AccountingSubsidySettlement::class, 'settlement_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(AccountingSubsidySettlementLine::class, 'line_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id');
    }
}
