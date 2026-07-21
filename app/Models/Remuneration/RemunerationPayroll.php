<?php

namespace App\Models\Remuneration;

use App\Models\Contract;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationPayroll extends RemunerationModel
{
    use SoftDeletes;

    public const LOCKED_STATUSES = ['aprobada', 'pagada', 'anulada'];

    protected $casts = [
        'calculated_at' => 'datetime:Y-m-d H:i',
        'approved_at' => 'datetime:Y-m-d H:i',
        'paid_at' => 'datetime:Y-m-d H:i',
        'annulled_at' => 'datetime:Y-m-d H:i',
        'source_row_number' => 'integer',
        'gross_taxable_amount' => 'integer',
        'gross_non_taxable_amount' => 'integer',
        'gross_total' => 'integer',
        'taxable_amount' => 'integer',
        'legal_deductions' => 'integer',
        'other_deductions' => 'integer',
        'total_deductions' => 'integer',
        'employer_contributions' => 'integer',
        'net_amount' => 'integer',
        'total_cost' => 'integer',
        'snapshot' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class, 'period_id');
    }

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

    public function lines(): HasMany
    {
        return $this->hasMany(RemunerationPayrollLine::class, 'payroll_id')->orderBy('sort_order')->orderBy('id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(RemunerationPayrollDistribution::class, 'payroll_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RemunerationPayment::class, 'payroll_id');
    }

    public function accountingExports(): HasMany
    {
        return $this->hasMany(RemunerationAccountingExport::class, 'payroll_id');
    }

    public function bookImport(): BelongsTo
    {
        return $this->belongsTo(RemunerationBookImport::class, 'book_import_id');
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES, true);
    }
}
