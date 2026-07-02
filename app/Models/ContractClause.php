<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContractClause extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'clause_type',
        'content',
        'active',
        'sort_order',
        'is_required',
        'observations',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(ContractTemplate::class, 'contract_clause_template')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps();
    }
}
