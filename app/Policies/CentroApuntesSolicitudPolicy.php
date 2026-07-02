<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesAccessService;

class CentroApuntesSolicitudPolicy
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
        return $this->accessService->canCreateRequest($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->accessService->canEditRequest($user);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->accessService->canDeleteRequest($user);
    }

    public function changeStatus(User $user, mixed $model = null): bool
    {
        return $this->accessService->canChangeRequestStatus($user);
    }

    public function deliver(User $user, mixed $model = null): bool
    {
        return $this->accessService->canRegisterRequestDelivery($user);
    }
}
