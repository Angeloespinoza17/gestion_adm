<?php

namespace App\Policies;

use App\Models\Security\SecurityShift;
use App\Models\User;
use App\Services\Security\SecurityAccessService;

class SecurityShiftPolicy
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, SecurityShift $shift): bool
    {
        return $this->accessService->canViewShift($user, $shift);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageShifts($user);
    }

    public function update(User $user, SecurityShift $shift): bool
    {
        return $this->accessService->canManageShifts($user)
            || ($this->accessService->isShiftOwner($user, $shift) && $this->accessService->canRegisterRounds($user));
    }

    public function start(User $user, SecurityShift $shift): bool
    {
        return $this->accessService->canStartShift($user, $shift);
    }

    public function finish(User $user, SecurityShift $shift): bool
    {
        return !$shift->is_weekly_template
            && $this->accessService->canStartShift($user, $shift);
    }

    public function createRound(User $user, SecurityShift $shift): bool
    {
        return $this->accessService->canRegisterRoundOnShift($user, $shift);
    }
}
