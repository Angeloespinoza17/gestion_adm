<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaInterviewPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageInterviews($user) || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaInterview $interview): bool
    {
        return $this->accessService->canViewInterview($user, $interview);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageInterviews($user);
    }

    public function update(User $user, ConvivenciaInterview $interview): bool
    {
        return $this->accessService->canManageInterviews($user) && $this->accessService->canViewInterview($user, $interview);
    }

    public function delete(User $user, ConvivenciaInterview $interview): bool
    {
        return $this->update($user, $interview);
    }
}
