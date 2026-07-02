<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeStrategicGoalMeasurement extends Model
{
    use SoftDeletes;

    protected $table = 'pme_medicion_metas_estrategicas';

    protected $fillable = [
        'pme_objective_id',
        'goal_label',
        'baseline_value',
        'expected_result',
        'current_result',
        'compliance_percentage',
        'information_source',
        'measured_at',
        'responsible_user_id',
        'analysis',
        'state',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'baseline_value' => 'decimal:2',
        'expected_result' => 'decimal:2',
        'current_result' => 'decimal:2',
        'compliance_percentage' => 'decimal:2',
        'measured_at' => 'date:Y-m-d',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(PmeObjective::class, 'pme_objective_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PmeEvidence::class, 'pme_goal_measurement_id')->latest('uploaded_at');
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
