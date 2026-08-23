<?php

namespace App\Models\RiskPrevention;

use App\Models\MaintenanceDependency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RiskMatrix extends Model
{
    use HasFactory;

    protected $table = 'prevent_risk_matrices';

    protected $guarded = [];

    public function versions(): HasMany
    {
        return $this->hasMany(RiskMatrixVersion::class, 'risk_matrix_id')->orderByDesc('version_number');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(RiskMatrixVersion::class, 'active_version_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(RiskMatrixVersion::class, 'risk_matrix_id')
            ->ofMany('version_number', 'max');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'work_center_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
