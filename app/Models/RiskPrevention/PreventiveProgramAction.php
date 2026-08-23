<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventiveProgramAction extends Model
{
    protected $table = 'prevent_preventive_program_actions';

    protected $guarded = [];

    protected $casts = ['planned_start_date' => 'date', 'due_date' => 'date', 'actual_completion_date' => 'date', 'progress_percentage' => 'integer', 'evidence_count' => 'integer'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(PreventiveProgram::class, 'preventive_program_id');
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(RiskControl::class, 'risk_control_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
