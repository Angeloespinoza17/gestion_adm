<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingDeclaration extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'year' => 'integer',
        'total_amount' => 'decimal:2',
        'payload' => 'array',
        'filed_at' => 'date',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AccountingDeclarationType::class, 'declaration_type_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(AccountingParty::class, 'party_id');
    }
}
