<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskMatrixProcess extends Model
{
    protected $table = 'prevent_risk_matrix_processes';

    protected $guarded = [];

    public function version(): BelongsTo
    {
        return $this->belongsTo(RiskMatrixVersion::class, 'risk_matrix_version_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RiskMatrixTask::class, 'risk_matrix_process_id')->orderBy('display_order');
    }
}
