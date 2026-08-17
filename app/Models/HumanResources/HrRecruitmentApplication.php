<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrRecruitmentApplication extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_recruitment_applications';

    protected $casts = [
        'applied_on' => 'date:Y-m-d',
        'score' => 'decimal:2',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(HrRecruitmentVacancy::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(HrCvBankEntry::class, 'cv_bank_entry_id');
    }

    public function hiredStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'hired_staff_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(HrPsycholaborInterview::class, 'application_id');
    }
}
