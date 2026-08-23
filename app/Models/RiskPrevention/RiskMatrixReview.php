<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Model;

class RiskMatrixReview extends Model
{
    protected $table = 'prevent_risk_matrix_reviews';

    protected $guarded = [];

    protected $casts = ['review_date' => 'date', 'requires_new_version' => 'boolean'];
}
