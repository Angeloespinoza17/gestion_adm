<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionTrainingParticipant extends Model
{
    protected $table = 'prevent_training_participants';

    protected $fillable = [
        'training_id',
        'employee_name',
        'compliance_status',
        'notes',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionTraining::class, 'training_id');
    }
}
