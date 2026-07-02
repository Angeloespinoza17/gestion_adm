<?php

namespace App\Policies;

use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\User;
use App\Services\Infirmary\InfirmaryAccessService;

class InfirmaryMedicationAuthorizationPolicy
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, InfirmaryMedicationAuthorization $authorization): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageMedication($user);
    }

    public function update(User $user, InfirmaryMedicationAuthorization $authorization): bool
    {
        return $this->accessService->canManageMedication($user);
    }

    public function delete(User $user, InfirmaryMedicationAuthorization $authorization): bool
    {
        return $this->accessService->canManageMedication($user);
    }
}
