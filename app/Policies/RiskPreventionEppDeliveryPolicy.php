<?php

namespace App\Policies;

use App\Models\User;
use App\Services\RiskPrevention\RiskPreventionAccessService;

class RiskPreventionEppDeliveryPolicy
{
    public function __construct(private readonly RiskPreventionAccessService $accessService) {}

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewEppDeliveries($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canRegisterEppDeliveries($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->accessService->canManage($user);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->accessService->canManage($user);
    }
}
