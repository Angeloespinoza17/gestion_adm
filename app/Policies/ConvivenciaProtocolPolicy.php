<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaProtocolPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageProtocols($user) || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaProtocol $protocol): bool
    {
        return $this->accessService->canViewProtocol($user, $protocol);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageProtocols($user);
    }

    public function update(User $user, ConvivenciaProtocol $protocol): bool
    {
        return $this->accessService->canManageProtocols($user) && $this->accessService->canViewProtocol($user, $protocol);
    }

    public function activate(User $user): bool
    {
        return $this->accessService->canActivateProtocols($user);
    }

    public function viewActivation(User $user, ConvivenciaProtocolActivation $activation): bool
    {
        return $this->accessService->canViewProtocolActivation($user, $activation);
    }
}
