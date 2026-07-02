<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationPayment extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
        'amount' => 'integer',
        'paid_at' => 'datetime:Y-m-d H:i',
        'metadata' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayroll::class, 'payroll_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingBankAccount::class, 'bank_account_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
