<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeIndicator extends Model
{
    use SoftDeletes;

    protected $table = 'pme_indicadores';

    protected $fillable = [
        'pme_objective_id',
        'pme_strategy_id',
        'name',
        'description',
        'indicator_type',
        'baseline_value',
        'target_value',
        'current_value',
        'measurement_unit',
        'verification_source',
        'measurement_frequency',
        'responsible_user_id',
        'state',
        'compliance_percentage',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'baseline_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'compliance_percentage' => 'decimal:2',
    ];

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

    public function measurements(): HasMany
    {
        return $this->hasMany(PmeIndicatorMeasurement::class, 'pme_indicator_id')->latest('measured_at');
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(PmeAction::class, 'pme_action_indicator', 'pme_indicator_id', 'pme_action_id')->withTimestamps();
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
