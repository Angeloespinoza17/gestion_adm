<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingJournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationAccountingExport extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'exported_at' => 'datetime:Y-m-d H:i',
        'reversed_at' => 'datetime:Y-m-d H:i',
        'total_debit' => 'integer',
        'total_credit' => 'integer',
        'payload' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class, 'period_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayroll::class, 'payroll_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'journal_entry_id');
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
