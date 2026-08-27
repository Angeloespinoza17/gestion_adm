<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceVisitPlanningBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'period_start',
        'period_end',
        'status',
        'proposed_count',
        'confirmed_count',
        'configuration',
        'created_by_user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'configuration' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function visits(): HasMany
    {
        return $this->hasMany(MaintenanceVisit::class, 'planning_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
