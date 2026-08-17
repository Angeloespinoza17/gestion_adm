<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class SupportDevice extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'student_support_devices';
    protected $casts = ['active' => 'boolean', 'starts_on' => 'date:Y-m-d', 'renewal_on' => 'date:Y-m-d'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
}
