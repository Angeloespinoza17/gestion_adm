<?php

namespace App\Models\RiskPrevention;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionTrainingParticipant extends Model
{
    protected $table = 'prevent_training_participants';

    protected $fillable = [
        'training_id',
        'staff_id',
        'employee_name',
        'compliance_status',
        'issued_on',
        'expires_on',
        'notes',
    ];

    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'expires_on' => 'date:Y-m-d',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionTraining::class, 'training_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
