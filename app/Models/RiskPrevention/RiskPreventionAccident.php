<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionAccident extends Model
{
    protected $table = 'prevent_accidents';

    protected $fillable = [
        'occurred_at',
        'accident_type',
        'involved_person_name',
        'involved_person_identifier',
        'location',
        'description',
        'injuries',
        'measures_taken',
        'referrals',
        'case_status',
        'responsible_name',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function followUps(): HasMany
    {
        return $this->hasMany(RiskPreventionAccidentFollowUp::class, 'accident_id')->orderByDesc('followed_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
