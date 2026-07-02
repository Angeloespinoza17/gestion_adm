<?php

namespace App\Policies;

use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;

class ApoyoEntrevistaPolicy
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, ApoyoEntrevista $interview): bool
    {
        if ($this->accessService->canViewConfidentialAttentions($user) || $this->accessService->canViewTeamAttentions($user)) {
            return true;
        }

        return (int) $interview->professional_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateAttention($user) || $this->accessService->canCreateFollowUp($user);
    }

    public function update(User $user, ApoyoEntrevista $interview): bool
    {
        return $this->accessService->canEditAnyAttention($user)
            || (int) $interview->professional_user_id === (int) $user->id;
    }
}
