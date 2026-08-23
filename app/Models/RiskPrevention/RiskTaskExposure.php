<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskTaskExposure extends Model
{
    protected $table = 'prevent_risk_task_exposures';

    protected $guarded = [];

    protected $casts = ['count' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(RiskMatrixTask::class, 'risk_matrix_task_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RiskCatalogItem::class, 'exposure_category_id');
    }
}
