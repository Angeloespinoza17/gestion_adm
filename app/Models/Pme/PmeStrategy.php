<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeStrategy extends Model
{
    use SoftDeletes;

    protected $table = 'pme_estrategias';

    protected $fillable = [
        'pme_objective_id',
        'name',
        'description',
        'responsible_user_id',
        'execution_period',
        'state',
        'progress_percentage',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(PmeObjective::class, 'pme_objective_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(PmeIndicator::class, 'pme_strategy_id')->latest('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PmeAction::class, 'pme_strategy_id')->latest('id');
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(PmeReflectiveMonitoring::class, 'pme_strategy_id')->latest('monitored_at');
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
