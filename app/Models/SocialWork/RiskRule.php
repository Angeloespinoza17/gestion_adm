<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\SoftDeletes;
class RiskRule extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'social_work_risk_rules';
    protected $casts = ['threshold' => 'decimal:2', 'conditions' => 'array', 'active' => 'boolean'];
}
