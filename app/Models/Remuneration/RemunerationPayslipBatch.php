<?php

namespace App\Models\Remuneration;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemunerationPayslipBatch extends RemunerationModel
{
    use HasPublicUlid;

    protected $casts = [
        'file_count' => 'integer',
        'page_count' => 'integer',
        'processed_pages' => 'integer',
        'payslip_count' => 'integer',
        'warning_count' => 'integer',
        'error_count' => 'integer',
        'gross_total' => 'integer',
        'taxable_total' => 'integer',
        'non_taxable_total' => 'integer',
        'deduction_total' => 'integer',
        'net_total' => 'integer',
        'employer_contribution_total' => 'integer',
        'started_at' => 'datetime:Y-m-d H:i',
        'completed_at' => 'datetime:Y-m-d H:i',
        'confirmed_at' => 'datetime:Y-m-d H:i',
        'options' => 'array',
        'summary' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(RemunerationPayslipFile::class, 'batch_id')->orderBy('id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(RemunerationPayslip::class, 'batch_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(RemunerationPayslipIssue::class, 'batch_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(RemunerationPayslipControl::class, 'batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
