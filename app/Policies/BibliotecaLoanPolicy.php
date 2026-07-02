<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Library\BibliotecaAccessService;

class BibliotecaLoanPolicy
{
    public function __construct(
        private readonly BibliotecaAccessService $accessService,
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
        return $this->accessService->canRegisterLoans($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canRegisterReturns($user)
            || $this->accessService->canRenewLoans($user)
            || $this->accessService->canManageOverdue($user);
    }
}
