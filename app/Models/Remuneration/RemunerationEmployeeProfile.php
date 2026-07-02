<?php

namespace App\Models\Remuneration;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationEmployeeProfile extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'afp_rate' => 'decimal:6',
        'is_pensioned' => 'boolean',
        'health_plan_amount' => 'decimal:6',
        'has_afc' => 'boolean',
        'afc_started_at' => 'date:Y-m-d',
        'apv_amount' => 'decimal:6',
        'family_dependents' => 'array',
        'is_active' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function contractSettings(): HasMany
    {
        return $this->hasMany(RemunerationContractSetting::class, 'employee_profile_id');
    }
}
