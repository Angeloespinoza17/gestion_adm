<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaComplaintPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageComplaints($user) || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaComplaint $complaint): bool
    {
        return $this->accessService->canViewComplaint($user, $complaint);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageComplaints($user);
    }

    public function update(User $user, ConvivenciaComplaint $complaint): bool
    {
        return $this->accessService->canManageComplaints($user) && $this->accessService->canViewComplaint($user, $complaint);
    }

    public function delete(User $user, ConvivenciaComplaint $complaint): bool
    {
        return $this->accessService->canManageComplaints($user) && $this->accessService->canViewComplaint($user, $complaint);
    }
}
