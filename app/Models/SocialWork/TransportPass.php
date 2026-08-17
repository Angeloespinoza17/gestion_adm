<?php
namespace App\Models\SocialWork;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class TransportPass extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'student_transport_passes';
    protected $casts = ['requested_on' => 'date:Y-m-d', 'received_on' => 'date:Y-m-d', 'delivered_on' => 'date:Y-m-d'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
