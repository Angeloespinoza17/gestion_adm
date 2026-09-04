<?php

namespace App\Models\RiskPrevention;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionJointCommittee extends Model
{
    protected $table = 'prevent_joint_committees';

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'active',
        'notes',
        'web_summary',
        'web_status',
        'web_published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'active' => 'boolean',
        'web_published_at' => 'datetime',
    ];

    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            Staff::class,
            'prevent_joint_committee_staff',
            'committee_id',
            'staff_id',
        )->withPivot([
            'id',
            'representation',
            'member_role',
            'position_name',
            'section',
            'sort_order',
            'joined_on',
            'ended_on',
            'active',
            'public_name_authorized',
            'public_name_authorized_at',
            'public_name_authorized_by',
        ])->withTimestamps()->orderBy('staff.full_name');
    }

    public function publicStaffMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            Staff::class,
            'prevent_joint_committee_staff',
            'committee_id',
            'staff_id',
        )->withPivot([
            'id',
            'representation',
            'member_role',
            'position_name',
            'section',
            'sort_order',
            'active',
            'public_name_authorized',
        ])->withTimestamps()
            ->wherePivot('active', true)
            ->wherePivot('public_name_authorized', true)
            ->orderBy('staff.full_name');
    }

    public function scopePublishedOnWebsite(Builder $query): Builder
    {
        return $query
            ->where('web_status', 'published')
            ->where('active', true)
            ->whereNotNull('web_published_at')
            ->where('web_published_at', '<=', now());
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RiskPreventionJointCommitteeDocument::class, 'committee_id')
            ->orderByDesc('document_date')
            ->orderByDesc('id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(RiskPreventionTraining::class, 'joint_committee_id')
            ->orderByDesc('training_date')
            ->orderByDesc('id');
    }
}
