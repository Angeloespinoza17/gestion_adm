<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingManualVersion extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'publication_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        'is_current' => 'boolean',
        'year' => 'integer',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(AccountingManualAccount::class, 'manual_version_id')->orderBy('code');
    }
}
