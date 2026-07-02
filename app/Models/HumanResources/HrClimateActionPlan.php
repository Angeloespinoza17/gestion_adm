<?php

namespace App\Models\HumanResources;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrClimateActionPlan extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_climate_action_plans';

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'date:Y-m-d',
        'evidence' => 'array',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(HrClimateSurvey::class, 'survey_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
