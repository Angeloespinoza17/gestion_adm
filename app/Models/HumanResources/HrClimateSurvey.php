<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrClimateSurvey extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_climate_surveys';

    protected $casts = [
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'response_count' => 'integer',
        'satisfaction_score' => 'decimal:2',
        'questions' => 'array',
        'alerts' => 'array',
        'report_payload' => 'array',
    ];

    public function actionPlans(): HasMany
    {
        return $this->hasMany(HrClimateActionPlan::class, 'survey_id');
    }
}
