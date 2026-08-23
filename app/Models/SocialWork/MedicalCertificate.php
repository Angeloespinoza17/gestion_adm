<?php

namespace App\Models\SocialWork;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalCertificate extends SocialWorkModel
{
    use SoftDeletes;

    protected $table = 'student_medical_certificates';

    protected $hidden = ['private_path'];

    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'covers_from' => 'date:Y-m-d',
        'covers_to' => 'date:Y-m-d',
        'expires_on' => 'date:Y-m-d',
        'is_permanent' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
