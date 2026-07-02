<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaPlanPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManagePlans($user) || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaPlan $plan): bool
    {
        return $this->accessService->canViewPlan($user, $plan);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManagePlans($user);
    }

    public function update(User $user, ConvivenciaPlan $plan): bool
    {
        return $this->accessService->canManagePlans($user) && $this->accessService->canViewPlan($user, $plan);
    }

    public function delete(User $user, ConvivenciaPlan $plan): bool
    {
        return $this->update($user, $plan);
    }
}
