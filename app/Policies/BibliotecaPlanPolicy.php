<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Library\BibliotecaAccessService;

class BibliotecaPlanPolicy
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
        return $this->accessService->canManageReadingPlan($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canManageReadingPlan($user);
    }
}
