<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class MedicalService extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'student_medical_services';
    protected $casts = ['is_junaeb' => 'boolean', 'referred_on' => 'date:Y-m-d', 'attended_on' => 'date:Y-m-d', 'next_control_on' => 'date:Y-m-d'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
}
