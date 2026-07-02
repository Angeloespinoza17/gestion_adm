<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesAccessService;

class PanolInsumoPolicy
{
    public function __construct(
        private readonly CentroApuntesAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageInventory($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canManageInventory($user);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->accessService->canManageInventory($user);
    }
}
