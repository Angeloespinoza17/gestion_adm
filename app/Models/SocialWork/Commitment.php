<?php
namespace App\Models\SocialWork;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Commitment extends SocialWorkModel {
    protected $table = 'social_work_commitments';
    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function intervention(): BelongsTo { return $this->belongsTo(Intervention::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
