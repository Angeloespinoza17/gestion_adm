<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaCasePolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaCase $case): bool
    {
        return $this->accessService->canViewCase($user, $case);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canCreateCase($user);
    }

    public function update(User $user, ConvivenciaCase $case): bool
    {
        return $this->accessService->canEditCases($user) && $this->accessService->canViewCase($user, $case);
    }

    public function delete(User $user, ConvivenciaCase $case): bool
    {
        return $this->accessService->canEditCases($user) && $this->accessService->canViewCase($user, $case);
    }
}
