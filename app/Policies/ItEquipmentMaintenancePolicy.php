<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Informatica\InformaticaAccessService;

class ItEquipmentMaintenancePolicy
{
    public function __construct(
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewMaintenance($user);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->accessService->canViewMaintenance($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateMaintenance($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canEditMaintenance($user)
            || $this->accessService->canCloseMaintenance($user);
    }
}
