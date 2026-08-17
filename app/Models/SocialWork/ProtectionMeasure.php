<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProtectionMeasure extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'student_protection_measures';
    protected $casts = ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
