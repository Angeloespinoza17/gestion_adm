<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyCaseAssignment extends Model
{
    protected $table = 'psychology_case_assignments';

    protected $fillable = ['case_id', 'referral_id', 'user_id', 'role', 'reason', 'expected_first_review_at', 'assigned_at', 'ended_at', 'assigned_by'];

    protected $casts = ['expected_first_review_at' => 'datetime', 'assigned_at' => 'datetime', 'ended_at' => 'datetime'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(PsychologyReferral::class, 'referral_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
