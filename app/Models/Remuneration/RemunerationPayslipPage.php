<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipPage extends RemunerationModel
{
    protected $hidden = ['normalized_text_encrypted'];

    protected $casts = [
        'page_number' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'confidence' => 'decimal:5',
        'normalized_text_encrypted' => 'encrypted',
        'normalized_structure' => 'array',
        'extracted_payload' => 'array',
        'warnings' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipFile::class, 'file_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslip::class, 'payslip_id');
    }
}
