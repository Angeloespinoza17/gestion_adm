<?php

namespace App\Policies;

use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;

class ApoyoDerivacionPolicy
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, ApoyoDerivacion $derivation): bool
    {
        return $this->accessService->canViewDerivation($user, $derivation);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateDerivation($user);
    }

    public function update(User $user, ApoyoDerivacion $derivation): bool
    {
        return $this->accessService->canCreateDerivation($user) || $this->accessService->canRespondDerivation($user);
    }
}
