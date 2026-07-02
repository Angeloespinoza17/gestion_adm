<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaDerivationPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageInternalDerivations($user)
            || $this->accessService->canManageExternalDerivations($user)
            || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaDerivation $derivation): bool
    {
        return $this->accessService->canViewDerivation($user, $derivation);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageInternalDerivations($user)
            || $this->accessService->canManageExternalDerivations($user);
    }

    public function update(User $user, ConvivenciaDerivation $derivation): bool
    {
        return $this->create($user) && $this->accessService->canViewDerivation($user, $derivation);
    }

    public function delete(User $user, ConvivenciaDerivation $derivation): bool
    {
        return $this->update($user, $derivation);
    }
}
