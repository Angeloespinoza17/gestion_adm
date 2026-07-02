<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmeCycle extends Model
{
    protected $table = 'pme_ciclos';

    protected $fillable = [
        'pme_plan_id',
        'name',
        'sort_order',
        'state',
        'is_current',
        'start_date',
        'end_date',
        'closed_at',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_current' => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PmePlan::class, 'pme_plan_id');
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
