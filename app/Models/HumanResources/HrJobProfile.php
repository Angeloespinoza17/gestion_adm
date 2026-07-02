<?php

namespace App\Models\HumanResources;

use App\Models\Cargo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrJobProfile extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_job_profiles';

    protected $casts = [
        'responsibilities' => 'array',
        'requirements' => 'array',
        'competencies' => 'array',
        'workload_profile' => 'array',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }
}
