<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrReplacementPoolEntry extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_replacement_pool_entries';

    protected $casts = [
        'available_from' => 'date:Y-m-d',
        'available_until' => 'date:Y-m-d',
        'preferred_hours' => 'decimal:2',
        'rating' => 'integer',
        'last_replacement_at' => 'date:Y-m-d',
        'metadata' => 'array',
    ];

    public function cvBankEntry(): BelongsTo
    {
        return $this->belongsTo(HrCvBankEntry::class, 'cv_bank_entry_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
