<?php

namespace App\Models\Remuneration;

use App\Models\Contract;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationEmployeeConcept extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'is_recurring' => 'boolean',
        'amount' => 'integer',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RemunerationConcept::class, 'concept_id');
    }
}
