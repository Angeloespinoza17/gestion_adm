<?php

namespace App\Models\HumanResources;

use App\Models\Cargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrRecruitmentVacancy extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_recruitment_vacancies';

    protected $casts = [
        'vacancy_count' => 'integer',
        'weekly_hours' => 'decimal:2',
        'opened_on' => 'date:Y-m-d',
        'target_start_on' => 'date:Y-m-d',
        'closes_on' => 'date:Y-m-d',
    ];

    public function jobProfile(): BelongsTo
    {
        return $this->belongsTo(HrJobProfile::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(HrRecruitmentApplication::class, 'vacancy_id');
    }
}
