<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaMeasurePolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageMeasures($user) || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaMeasure $measure): bool
    {
        return $this->accessService->canViewMeasure($user, $measure);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageMeasures($user);
    }

    public function update(User $user, ConvivenciaMeasure $measure): bool
    {
        return $this->accessService->canManageMeasures($user) && $this->accessService->canViewMeasure($user, $measure);
    }

    public function delete(User $user, ConvivenciaMeasure $measure): bool
    {
        return $this->update($user, $measure);
    }
}
