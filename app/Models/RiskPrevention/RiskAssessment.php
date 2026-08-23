<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    protected $table = 'prevent_risk_assessments';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean', 'source_payload' => 'array', 'assessed_at' => 'datetime',
        'instrument_date' => 'date', 'next_measurement_at' => 'date', 'calculated_score' => 'float',
        'exposure_value' => 'float', 'result_value' => 'float',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(RiskEntry::class, 'risk_entry_id');
    }

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(RiskMethodology::class, 'methodology_id');
    }

    public function calculatedLevel(): BelongsTo
    {
        return $this->belongsTo(RiskCatalogItem::class, 'calculated_level_id');
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(RiskCatalogItem::class, 'protocol_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
