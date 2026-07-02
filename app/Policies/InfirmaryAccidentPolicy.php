<?php

namespace App\Policies;

use App\Models\Infirmary\InfirmaryAccident;
use App\Models\User;
use App\Services\Infirmary\InfirmaryAccessService;

class InfirmaryAccidentPolicy
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, InfirmaryAccident $accident): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageAccidents($user);
    }

    public function update(User $user, InfirmaryAccident $accident): bool
    {
        return $this->accessService->canManageAccidents($user);
    }

    public function delete(User $user, InfirmaryAccident $accident): bool
    {
        return $this->accessService->canManageAccidents($user);
    }
}
