<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contract_type',
        'slug',
        'description',
        'active',
        'body',
        'available_variables',
        'internal_notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'available_variables' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function clauses(): BelongsToMany
    {
        return $this->belongsToMany(ContractClause::class, 'contract_clause_template')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps()
            ->orderBy('contract_clause_template.sort_order');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'contract_template_id');
    }
}
