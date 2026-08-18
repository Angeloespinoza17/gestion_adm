<?php

namespace App\Policies;

use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;

class PsychologyReferralPolicy
{
    public function __construct(private readonly PsychologyAccessService $access) {}

    public function view(User $user, PsychologyReferral $referral): bool
    {
        return $this->access->canViewReferral($user, $referral);
    }

    public function update(User $user, PsychologyReferral $referral): bool
    {
        return $referral->status === 'draft' && (int) $referral->referred_by_user_id === (int) $user->id && $user->hasPermission('psychology.referrals.create');
    }

    public function manage(User $user, PsychologyReferral $referral): bool
    {
        return $this->access->canViewReferral($user, $referral) && $user->hasPermission('psychology.referrals.update');
    }

    public function assign(User $user, PsychologyReferral $referral): bool
    {
        return $user->hasPermission('psychology.referrals.assign');
    }
}
