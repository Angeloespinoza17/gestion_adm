<?php

namespace App\Models\Remuneration;

use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemunerationPayslipFile extends RemunerationModel
{
    protected $hidden = ['private_path'];

    protected $casts = [
        'size_bytes' => 'integer',
        'page_count' => 'integer',
        'detected_year' => 'integer',
        'detected_month' => 'integer',
        'version' => 'integer',
        'processed_at' => 'datetime:Y-m-d H:i',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipBatch::class, 'batch_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(RemunerationPayslipPage::class, 'file_id')->orderBy('page_number');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(RemunerationPayslip::class, 'file_id');
    }

    public function replacesFile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_file_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
