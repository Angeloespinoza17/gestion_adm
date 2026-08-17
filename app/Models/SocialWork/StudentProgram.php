<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class StudentProgram extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'student_social_programs';
    protected $casts = ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function programType(): BelongsTo { return $this->belongsTo(ProgramType::class, 'program_type_id'); }
}
