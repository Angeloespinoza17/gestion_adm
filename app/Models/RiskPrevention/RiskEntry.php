<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RiskEntry extends Model
{
    protected $table = 'prevent_risk_entries';

    protected $guarded = [];

    protected $casts = ['source_payload' => 'array'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(RiskMatrixTask::class, 'risk_matrix_task_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(RiskCatalogItem::class, 'risk_family_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(RiskCatalogItem::class, 'risk_catalog_item_id');
    }

    public function hazardFactors(): HasMany
    {
        return $this->hasMany(RiskHazardFactor::class, 'risk_entry_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class, 'risk_entry_id')->orderByDesc('assessed_at');
    }

    public function currentAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class, 'risk_entry_id')->where('phase', 'current')->where('active', true)->latestOfMany();
    }

    public function residualAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class, 'risk_entry_id')->where('phase', 'residual')->where('active', true)->latestOfMany();
    }

    public function controls(): HasMany
    {
        return $this->hasMany(RiskControl::class, 'risk_entry_id')->orderBy('id');
    }
}
