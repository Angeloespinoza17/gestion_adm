<?php

namespace App\Models\RiskPrevention;

use App\Models\Cargo;
use App\Models\MaintenanceDependency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskMatrixTask extends Model
{
    protected $table = 'prevent_risk_matrix_tasks';

    protected $guarded = [];

    public function process(): BelongsTo
    {
        return $this->belongsTo(RiskMatrixProcess::class, 'risk_matrix_process_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'job_position_id');
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Cargo::class, 'prevent_risk_task_positions', 'risk_matrix_task_id', 'cargo_id')->withTimestamps();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'location_id');
    }

    public function exposures(): HasMany
    {
        return $this->hasMany(RiskTaskExposure::class, 'risk_matrix_task_id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(RiskEntry::class, 'risk_matrix_task_id')->orderBy('display_order');
    }
}
