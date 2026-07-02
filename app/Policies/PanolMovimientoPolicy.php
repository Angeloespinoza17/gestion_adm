<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesAccessService;

class PanolMovimientoPolicy
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
        return $this->accessService->canRegisterStockMovements($user);
    }
}
