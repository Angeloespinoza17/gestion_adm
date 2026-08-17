<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PedagogicalReport extends SocialWorkModel { protected $table = 'social_work_pedagogical_reports'; protected $casts = ['requested_at' => 'datetime', 'due_at' => 'datetime', 'responded_at' => 'datetime', 'response_data' => 'array']; public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); } }
