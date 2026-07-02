<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrCvBankEntry extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_cv_bank_entries';

    protected $casts = [
        'experience_years' => 'decimal:2',
        'rating' => 'integer',
        'metadata' => 'array',
    ];

    public function replacementPoolEntries(): HasMany
    {
        return $this->hasMany(HrReplacementPoolEntry::class, 'cv_bank_entry_id');
    }
}
