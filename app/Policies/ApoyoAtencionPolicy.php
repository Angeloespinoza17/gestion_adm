<?php

namespace App\Policies;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;

class ApoyoAtencionPolicy
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, ApoyoAtencion $attention): bool
    {
        return $this->accessService->canViewAttention($user, $attention);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateAttention($user);
    }

    public function update(User $user, ApoyoAtencion $attention): bool
    {
        return $this->accessService->canEditAttention($user, $attention);
    }

    public function delete(User $user, ApoyoAtencion $attention): bool
    {
        return $this->accessService->canDeleteAttention($user);
    }
}
