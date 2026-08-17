<?php

namespace App\Models\HumanResources;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPsycholaborInterview extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_psycholabor_interviews';

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'induction_required' => 'boolean',
        'report_file_size' => 'integer',
    ];

    protected $hidden = ['confidential_notes', 'report_path'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(HrRecruitmentApplication::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }
}
