<?php

namespace App\Policies;

use App\Models\Infirmary\InfirmaryMedication;
use App\Models\User;
use App\Services\Infirmary\InfirmaryAccessService;

class InfirmaryMedicationPolicy
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, InfirmaryMedication $medication): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageInventory($user);
    }

    public function update(User $user, InfirmaryMedication $medication): bool
    {
        return $this->accessService->canManageInventory($user);
    }

    public function delete(User $user, InfirmaryMedication $medication): bool
    {
        return $this->accessService->canManageInventory($user);
    }
}
