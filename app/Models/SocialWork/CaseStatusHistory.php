<?php
namespace App\Models\SocialWork;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CaseStatusHistory extends SocialWorkModel {
    protected $table = 'social_work_case_status_history';
    protected $casts = ['changed_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
