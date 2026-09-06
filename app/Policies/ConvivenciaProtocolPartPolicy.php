<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaProtocolPartPolicy
{
    public function __construct(private readonly ConvivenciaAccessService $accessService) {}

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageProtocols($user)
            || $this->accessService->canActivateProtocols($user)
            || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaProtocolPart $part): bool
    {
        return $this->viewAny($user) && (! $part->is_sensitive || $this->accessService->canManageProtocols($user));
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageProtocols($user);
    }

    public function update(User $user, ConvivenciaProtocolPart $part): bool
    {
        return $this->accessService->canManageProtocols($user);
    }

    public function delete(User $user, ConvivenciaProtocolPart $part): bool
    {
        return $this->accessService->canManageProtocols($user);
    }
}
