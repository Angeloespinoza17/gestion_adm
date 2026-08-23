<?php

namespace App\Models\Psychology;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologyCoordinationRequest extends Model
{
    protected $fillable = [
        'case_id',
        'activity_id',
        'requester_user_id',
        'recipient_user_id',
        'coordination_type',
        'subject',
        'request_message',
        'requested_for',
        'status',
        'response_message',
        'responded_at',
        'responded_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requested_for' => 'date:Y-m-d',
        'responded_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PsychologyActivity::class, 'activity_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
