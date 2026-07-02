<?php

namespace App\Policies;

use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;

class ApoyoPlanPolicy
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, ApoyoPlan $plan): bool
    {
        if ($this->accessService->canViewConfidentialAttentions($user) || $this->accessService->canViewTeamAttentions($user)) {
            return true;
        }

        return (int) $plan->responsible_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreatePlan($user);
    }

    public function update(User $user, ApoyoPlan $plan): bool
    {
        return $this->accessService->canCreatePlan($user)
            && ($this->accessService->canEditAnyAttention($user) || (int) $plan->responsible_user_id === (int) $user->id);
    }
}
