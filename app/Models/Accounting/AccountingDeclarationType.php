<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingDeclarationType extends AccountingModel
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function declarations(): HasMany
    {
        return $this->hasMany(AccountingDeclaration::class, 'declaration_type_id');
    }
}
