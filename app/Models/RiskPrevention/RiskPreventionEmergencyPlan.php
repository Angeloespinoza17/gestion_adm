<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionEmergencyPlan extends Model
{
    protected $table = 'prevent_emergency_plans';

    protected $fillable = [
        'record_type',
        'title',
        'emergency_type',
        'last_updated_at',
        'responsible_name',
        'document_path',
        'document_name',
        'notes',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_updated_at' => 'date',
        'active' => 'boolean',
    ];

    public function drills(): HasMany
    {
        return $this->hasMany(RiskPreventionEmergencyDrill::class, 'emergency_plan_id')->orderByDesc('drill_date');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
