<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Intervention extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'social_work_interventions';
    protected $casts = ['activity_date' => 'date:Y-m-d', 'due_at' => 'datetime', 'next_intervention_at' => 'datetime', 'participants' => 'array', 'structured_data' => 'array'];
    protected $hidden = ['highly_confidential_notes'];
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function commitments(): HasMany { return $this->hasMany(Commitment::class, 'intervention_id'); }
}
