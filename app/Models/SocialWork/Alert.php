<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Alert extends SocialWorkModel {
    use HasFactory;
    use SoftDeletes;
    protected $table = 'social_work_alerts';
    protected $casts = ['alerted_at' => 'datetime', 'due_at' => 'datetime', 'resolved_at' => 'datetime', 'evidence' => 'array'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
