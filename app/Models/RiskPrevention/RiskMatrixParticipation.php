<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;

class RiskMatrixParticipation extends Model
{
    protected $table = 'prevent_risk_matrix_participations';

    protected $guarded = [];

    protected $casts = ['participation_date' => 'date', 'acknowledged_at' => 'datetime'];
}
