<?php

namespace App\Models\HumanResources;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLaborCertificate extends HumanResourcesModel
{
    use SoftDeletes;

    protected $table = 'hr_labor_certificates';

    protected $casts = [
        'requested_at' => 'date:Y-m-d',
        'issued_at' => 'date:Y-m-d',
        'payload' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function signedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
