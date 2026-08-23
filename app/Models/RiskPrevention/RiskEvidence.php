<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;

class RiskEvidence extends Model
{
    protected $table = 'prevent_risk_evidences';

    protected $guarded = [];

    protected $hidden = ['file_path'];

    protected $casts = ['evidence_date' => 'date', 'file_size' => 'integer'];

    public function evidenceable()
    {
        return $this->morphTo();
    }
}
