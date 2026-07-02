<?php

namespace App\Policies;

use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;

class ApoyoSeguimientoPolicy
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, ApoyoSeguimiento $followUp): bool
    {
        return $followUp->attention
            ? $this->accessService->canViewAttention($user, $followUp->attention)
            : $this->accessService->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateFollowUp($user);
    }

    public function update(User $user, ApoyoSeguimiento $followUp): bool
    {
        return $this->accessService->canCreateFollowUp($user)
            && ($this->accessService->canEditAnyAttention($user) || (int) $followUp->responsible_user_id === (int) $user->id);
    }
}
