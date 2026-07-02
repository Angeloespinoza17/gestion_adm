<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingManualAccount;
use App\Models\Contract;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationContractSetting extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'teacher_career' => 'boolean',
        'priority_percent' => 'decimal:4',
        'base_salary' => 'integer',
        'weekly_hours' => 'decimal:2',
        'basic_hours' => 'decimal:2',
        'middle_hours' => 'decimal:2',
        'pie_hours' => 'decimal:2',
        'sep_hours' => 'decimal:2',
        'pro_retention_hours' => 'decimal:2',
        'funding_distribution' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(RemunerationEmployeeProfile::class, 'employee_profile_id');
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
