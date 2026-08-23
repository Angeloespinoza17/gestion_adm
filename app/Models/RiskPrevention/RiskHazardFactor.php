<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskHazardFactor extends Model
{
    protected $table = 'prevent_risk_hazard_factors';

    protected $guarded = [];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(RiskEntry::class, 'risk_entry_id');
    }
}
