<?php

namespace App\Models\Remuneration;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\LibroDigital\School;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemunerationPayslip extends RemunerationModel
{
    use HasPublicUlid;

    protected $hidden = ['rut_encrypted', 'employee_name_encrypted', 'business_key_hash'];

    protected $casts = [
        'rut_encrypted' => 'encrypted',
        'employee_name_encrypted' => 'encrypted',
        'year' => 'integer',
        'month' => 'integer',
        'version' => 'integer',
        'is_current' => 'boolean',
        'confidence' => 'decimal:5',
        'gross_taxable_amount' => 'integer',
        'gross_non_taxable_amount' => 'integer',
        'gross_total' => 'integer',
        'legal_deductions' => 'integer',
        'other_deductions' => 'integer',
        'total_deductions' => 'integer',
        'net_amount' => 'integer',
        'employer_contributions' => 'integer',
        'total_cost' => 'integer',
        'employment_snapshot' => 'array',
        'source_totals' => 'array',
        'metadata' => 'array',
        'confirmed_at' => 'datetime:Y-m-d H:i',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipBatch::class, 'batch_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipFile::class, 'file_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipPage::class, 'page_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RemunerationPeriod::class, 'period_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RemunerationPayslipEarning::class, 'payslip_id')->orderBy('line_number')->orderBy('id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(RemunerationPayslipDiscount::class, 'payslip_id')->orderBy('line_number');
    }

    public function employerContributions(): HasMany
    {
        return $this->hasMany(RemunerationPayslipEmployerContribution::class, 'payslip_id')->orderBy('line_number')->orderBy('id');
    }

    public function fundingSummaries(): HasMany
    {
        return $this->hasMany(RemunerationPayslipFundingSummary::class, 'payslip_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(RemunerationPayslipControl::class, 'payslip_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(RemunerationPayslipIssue::class, 'payslip_id');
    }

    public function maskedRut(): string
    {
        $rut = preg_replace('/[^0-9Kk]/', '', (string) $this->rut_encrypted) ?: '';

        return strlen($rut) > 4 ? str_repeat('*', strlen($rut) - 4).substr($rut, -4) : '****';
    }
}
