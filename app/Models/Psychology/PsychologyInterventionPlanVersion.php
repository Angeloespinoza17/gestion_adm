<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyInterventionPlanVersion extends Model
{
    protected $table = 'psychology_intervention_plan_versions';

    protected $guarded = ['id'];

    protected $casts = ['estimated_start_on' => 'date:Y-m-d', 'estimated_end_on' => 'date:Y-m-d'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PsychologyInterventionPlan::class, 'plan_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
