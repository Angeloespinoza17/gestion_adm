<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingJournalEntry extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingJournalEntryLine::class, 'journal_entry_id');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
