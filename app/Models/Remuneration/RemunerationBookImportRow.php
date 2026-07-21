<?php

namespace App\Models\Remuneration;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationBookImportRow extends RemunerationModel
{
    protected $casts = [
        'row_number' => 'integer',
        'worked_days' => 'decimal:2',
        'weekly_hours' => 'decimal:2',
        'gross_taxable_amount' => 'integer',
        'gross_non_taxable_amount' => 'integer',
        'gross_total' => 'integer',
        'taxable_amount' => 'integer',
        'legal_deductions' => 'integer',
        'other_deductions' => 'integer',
        'total_deductions' => 'integer',
        'employer_contributions' => 'integer',
        'net_amount' => 'integer',
        'raw_totals' => 'array',
        'raw_earnings_columns' => 'array',
        'raw_deductions_columns' => 'array',
        'raw_earnings' => 'array',
        'raw_deductions' => 'array',
        'raw_employer_contributions' => 'array',
        'errors' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(RemunerationBookImport::class, 'book_import_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
