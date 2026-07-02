<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Informatica\InformaticaAccessService;

class ItEquipmentLoanPolicy
{
    public function __construct(
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewLoans($user);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->accessService->canViewLoans($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateLoans($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canReturnLoans($user)
            || $this->accessService->canCancelLoans($user);
    }
}
