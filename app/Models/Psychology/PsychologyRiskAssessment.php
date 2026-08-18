<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyRiskAssessment extends Model
{
    use SoftDeletes;

    protected $table = 'psychology_risk_assessments';

    protected $fillable = ['case_id', 'risk_type', 'level', 'structured_indicators', 'professional_rationale', 'immediate_action', 'response_responsible_user_id', 'response_at', 'protocol_reference', 'acknowledged_at', 'acknowledged_by', 'status', 'created_by'];

    protected $casts = ['professional_rationale' => 'encrypted', 'response_at' => 'datetime', 'acknowledged_at' => 'datetime'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PsychologyProtectiveAction::class, 'risk_assessment_id')->orderBy('action_at');
    }

    public function responseResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'response_responsible_user_id');
    }
}
