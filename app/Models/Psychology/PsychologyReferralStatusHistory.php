<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyReferralStatusHistory extends Model
{
    protected $table = 'psychology_referral_status_history';

    protected $fillable = ['referral_id', 'from_status', 'to_status', 'reason', 'shared_note', 'internal_note', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime', 'internal_note' => 'encrypted'];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(PsychologyReferral::class, 'referral_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
