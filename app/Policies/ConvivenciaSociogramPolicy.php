<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaSociogramPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewSociograms($user);
    }

    public function view(User $user, ConvivenciaSociogram $sociogram): bool
    {
        return $this->accessService->canViewSociogram($user, $sociogram);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageSociograms($user);
    }

    public function update(User $user, ConvivenciaSociogram $sociogram): bool
    {
        return $this->accessService->canManageSociograms($user) && $this->accessService->canViewSociogram($user, $sociogram);
    }

    public function delete(User $user, ConvivenciaSociogram $sociogram): bool
    {
        return $this->update($user, $sociogram);
    }
}
