<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrOnboardingProcess extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_onboarding_processes';

    protected $casts = [
        'starts_at' => 'date:Y-m-d',
        'target_completion_at' => 'date:Y-m-d',
        'completed_at' => 'date:Y-m-d',
        'documents_checklist' => 'array',
        'trainings_checklist' => 'array',
        'accesses_checklist' => 'array',
        'materials_checklist' => 'array',
        'completion_percent' => 'decimal:2',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function jobProfile(): BelongsTo
    {
        return $this->belongsTo(HrJobProfile::class, 'job_profile_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
