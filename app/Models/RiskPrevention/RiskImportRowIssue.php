<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskImportRowIssue extends Model
{
    protected $table = 'prevent_risk_import_row_issues';

    protected $guarded = [];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RiskImportBatch::class, 'risk_import_batch_id');
    }
}
