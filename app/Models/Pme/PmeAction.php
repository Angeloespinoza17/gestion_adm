<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeAction extends Model
{
    use SoftDeletes;

    protected $table = 'pme_acciones';

    protected $fillable = [
        'pme_plan_id',
        'pme_dimension_id',
        'pme_objective_id',
        'pme_strategy_id',
        'name',
        'description',
        'justification',
        'responsible_user_id',
        'responsible_area',
        'start_date',
        'end_date',
        'planned_budget',
        'committed_budget',
        'executed_budget',
        'funding_source',
        'cost_center_reference',
        'external_accounting_reference',
        'document_reference',
        'minimum_evidence_required',
        'progress_percentage',
        'last_progress_at',
        'state',
        'closed_at',
        'closed_by',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'planned_budget' => 'decimal:2',
        'committed_budget' => 'decimal:2',
        'executed_budget' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
        'last_progress_at' => 'datetime',
        'closed_at' => 'datetime',
        'minimum_evidence_required' => 'integer',
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

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(PmeIndicator::class, 'pme_action_indicator', 'pme_action_id', 'pme_indicator_id')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PmeActivity::class, 'pme_action_id')->latest('id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PmeMilestone::class, 'pme_action_id')->latest('planned_date');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PmeEvidence::class, 'pme_action_id')->latest('uploaded_at');
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(PmeReflectiveMonitoring::class, 'pme_action_id')->latest('monitored_at');
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
