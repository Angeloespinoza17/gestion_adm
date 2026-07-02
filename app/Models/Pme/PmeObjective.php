<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeObjective extends Model
{
    use SoftDeletes;

    protected $table = 'pme_objetivos';

    protected $fillable = [
        'pme_plan_id',
        'pme_dimension_id',
        'name',
        'description',
        'strategic_goal',
        'global_indicator',
        'responsible_user_id',
        'start_date',
        'end_date',
        'state',
        'progress_percentage',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'progress_percentage' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PmePlan::class, 'pme_plan_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(PmeDimension::class, 'pme_dimension_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function strategies(): HasMany
    {
        return $this->hasMany(PmeStrategy::class, 'pme_objective_id')->latest('id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(PmeIndicator::class, 'pme_objective_id')->latest('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PmeAction::class, 'pme_objective_id')->latest('id');
    }

    public function strategicGoalMeasurements(): HasMany
    {
        return $this->hasMany(PmeStrategicGoalMeasurement::class, 'pme_objective_id')->latest('measured_at');
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(PmeReflectiveMonitoring::class, 'pme_objective_id')->latest('monitored_at');
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
