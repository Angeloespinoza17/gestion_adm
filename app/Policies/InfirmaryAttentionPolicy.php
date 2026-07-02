<?php

namespace App\Policies;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\User;
use App\Services\Infirmary\InfirmaryAccessService;

class InfirmaryAttentionPolicy
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, InfirmaryAttention $attention): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateAttention($user);
    }

    public function update(User $user, InfirmaryAttention $attention): bool
    {
        return $this->accessService->canEditAttention($user);
    }

    public function delete(User $user, InfirmaryAttention $attention): bool
    {
        return $this->accessService->canDeleteAttention($user);
    }
}
