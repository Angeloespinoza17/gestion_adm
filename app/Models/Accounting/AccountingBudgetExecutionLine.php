<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingBudgetExecutionLine extends AccountingModel
{
    public const MONTH_COLUMNS = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
    ];

    protected $casts = [
        'annual_budget' => 'float',
        'january' => 'float',
        'february' => 'float',
        'march' => 'float',
        'april' => 'float',
        'may' => 'float',
        'june' => 'float',
        'july' => 'float',
        'august' => 'float',
        'september' => 'float',
        'october' => 'float',
        'november' => 'float',
        'december' => 'float',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(AccountingBudgetExecutionImport::class, 'import_id');
    }
}
