<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyInterventionPlan extends Model
{
    use SoftDeletes;

    protected $table = 'psychology_intervention_plans';

    protected $fillable = ['case_id', 'responsible_user_id', 'status', 'current_version', 'review_on', 'created_by'];

    protected $casts = ['review_on' => 'date:Y-m-d'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PsychologyInterventionPlanVersion::class, 'plan_id')->orderByDesc('version');
    }
}
