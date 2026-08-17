<?php
namespace App\Models\SocialWork;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class JunaebBenefit extends SocialWorkModel {
    use SoftDeletes; protected $table = 'student_junaeb_benefits';
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function benefitType(): BelongsTo { return $this->belongsTo(JunaebBenefitType::class, 'benefit_type_id'); }
    public function courseSection(): BelongsTo { return $this->belongsTo(CourseSection::class, 'course_section_id'); }
    public function deliveries(): HasMany { return $this->hasMany(JunaebDelivery::class, 'student_junaeb_benefit_id'); }
}
