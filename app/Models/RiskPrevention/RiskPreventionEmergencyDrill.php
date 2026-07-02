<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionEmergencyDrill extends Model
{
    protected $table = 'prevent_emergency_drills';

    protected $fillable = [
        'emergency_plan_id',
        'title',
        'emergency_type',
        'drill_date',
        'responsible_name',
        'participants_count',
        'findings',
        'improvements',
        'document_path',
        'document_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'drill_date' => 'date',
        'participants_count' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionEmergencyPlan::class, 'emergency_plan_id');
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
