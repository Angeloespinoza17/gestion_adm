<?php

namespace App\Models\Pme;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmeAlert extends Model
{
    protected $table = 'pme_alertas';

    protected $fillable = [
        'pme_plan_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'related_type',
        'related_id',
        'due_date',
        'state',
        'resolved_at',
        'payload',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'resolved_at' => 'datetime',
        'payload' => 'array',
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
