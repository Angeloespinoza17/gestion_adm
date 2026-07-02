<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeReflectiveMonitoring extends Model
{
    use SoftDeletes;

    protected $table = 'pme_monitoreos_reflexivos';

    protected $fillable = [
        'pme_plan_id',
        'pme_dimension_id',
        'pme_objective_id',
        'pme_strategy_id',
        'pme_action_id',
        'monitored_at',
        'responsible_user_id',
        'guiding_questions',
        'observed_progress',
        'difficulties',
        'reviewed_evidences',
        'decisions_taken',
        'required_adjustments',
        'next_steps',
        'state',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'monitored_at' => 'date:Y-m-d',
        'guiding_questions' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PmePlan::class, 'pme_plan_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(PmeDimension::class, 'pme_dimension_id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(PmeObjective::class, 'pme_objective_id');
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(PmeStrategy::class, 'pme_strategy_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(PmeAction::class, 'pme_action_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PmeEvidence::class, 'pme_reflective_monitoring_id')->latest('uploaded_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
