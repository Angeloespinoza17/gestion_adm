<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RiskAssessment extends SocialWorkModel {
    protected $table = 'social_work_risk_assessments';
    protected $casts = ['assessed_at' => 'datetime', 'period_from' => 'date:Y-m-d', 'period_to' => 'date:Y-m-d', 'indicators' => 'array', 'source_snapshot' => 'array', 'manually_overridden' => 'boolean'];
    public function case(): BelongsTo { return $this->belongsTo(SocialCase::class, 'case_id'); }
}
