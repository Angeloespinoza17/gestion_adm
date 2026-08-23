<?php

namespace App\Models\RiskPrevention;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RiskControl extends Model
{
    protected $table = 'prevent_risk_controls';

    protected $guarded = [];

    protected $casts = [
        'creates_program_action' => 'boolean', 'progress_percentage' => 'integer',
        'planned_start_date' => 'date', 'due_date' => 'date', 'next_due_date' => 'date',
        'completed_at' => 'datetime', 'verified_at' => 'datetime',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(RiskEntry::class, 'risk_entry_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_employee_id');
    }

    public function programAction(): HasOne
    {
        return $this->hasOne(PreventiveProgramAction::class, 'risk_control_id');
    }
}
