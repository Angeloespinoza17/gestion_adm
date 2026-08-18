<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyProtectiveAction extends Model
{
    protected $table = 'psychology_protective_actions';

    protected $fillable = ['risk_assessment_id', 'action', 'responsible_user_id', 'action_at', 'result', 'created_by'];

    protected $casts = ['action_at' => 'datetime'];

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
