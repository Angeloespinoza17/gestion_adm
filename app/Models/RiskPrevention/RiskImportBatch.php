<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskImportBatch extends Model
{
    protected $table = 'prevent_risk_import_batches';

    protected $guarded = [];

    protected $hidden = ['stored_path'];

    protected $casts = ['mapping_configuration' => 'array', 'preview_payload' => 'array', 'summary' => 'array'];

    public function issues(): HasMany
    {
        return $this->hasMany(RiskImportRowIssue::class, 'risk_import_batch_id')->orderBy('row_number');
    }
}
