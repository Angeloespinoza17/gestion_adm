<?php

namespace App\Models\RiskPrevention;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RiskPreventionJointCommittee extends Model
{
    protected $table = 'prevent_joint_committees';

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'active' => 'boolean',
    ];

    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            Staff::class,
            'prevent_joint_committee_staff',
            'committee_id',
            'staff_id',
        )->withPivot([
            'representation',
            'member_role',
            'position_name',
            'joined_on',
            'ended_on',
            'active',
        ])->withTimestamps()->orderBy('staff.full_name');
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
