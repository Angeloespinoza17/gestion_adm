<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionAccidentFollowUp extends Model
{
    protected $table = 'prevent_accident_follow_ups';

    protected $fillable = [
        'accident_id',
        'followed_at',
        'status',
        'notes',
        'next_actions',
        'created_by',
    ];

    protected $casts = [
        'followed_at' => 'datetime',
    ];

    public function accident(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionAccident::class, 'accident_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
