<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskMethodology extends Model
{
    use HasFactory;

    protected $table = 'prevent_risk_methodologies';

    protected $guarded = [];

    protected $casts = ['configuration' => 'array', 'active' => 'boolean', 'valid_from' => 'date', 'valid_until' => 'date'];

    public function catalogItems(): HasMany
    {
        return $this->hasMany(RiskCatalogItem::class, 'methodology_id');
    }
}
