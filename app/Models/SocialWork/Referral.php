<?php

namespace App\Models\SocialWork;

use App\Models\CourseSection;
use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends SocialWorkModel
{
    use SoftDeletes;

    protected $table = 'social_work_referrals';

    protected $casts = ['referral_date' => 'date:Y-m-d', 'received_at' => 'datetime', 'immediate_risk' => 'boolean'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SocialCase::class, 'case_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inspectoriaAttention(): BelongsTo
    {
        return $this->belongsTo(InspectoriaAttention::class, 'inspectoria_attention_id');
    }
}
