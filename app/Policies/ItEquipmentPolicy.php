<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Informatica\InformaticaAccessService;

class ItEquipmentPolicy
{
    public function __construct(
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewEquipment($user);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->accessService->canViewEquipment($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateEquipment($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canEditEquipment($user);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->accessService->canDeleteEquipment($user);
    }
}
