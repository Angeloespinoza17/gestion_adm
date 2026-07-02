<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingManualAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationConcept extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_imponible' => 'boolean',
        'affects_tax_base' => 'boolean',
        'affects_net' => 'boolean',
        'is_legal' => 'boolean',
        'is_system' => 'boolean',
        'amount' => 'integer',
        'is_active' => 'boolean',
    ];

    public function employeeConcepts(): HasMany
    {
        return $this->hasMany(RemunerationEmployeeConcept::class, 'concept_id');
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingManualAccount::class, 'accounting_debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingManualAccount::class, 'accounting_credit_account_id');
    }
}
