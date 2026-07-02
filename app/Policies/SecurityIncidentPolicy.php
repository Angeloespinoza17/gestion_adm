<?php

namespace App\Policies;

use App\Models\Security\SecurityIncident;
use App\Models\User;
use App\Services\Security\SecurityAccessService;

class SecurityIncidentPolicy
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function view(User $user, SecurityIncident $incident): bool
    {
        return $this->accessService->canViewIncident($user, $incident);
    }

    public function update(User $user, SecurityIncident $incident): bool
    {
        return $this->accessService->canUpdateIncident($user, $incident);
    }
}
