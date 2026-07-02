<?php

namespace App\Policies;

use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;

class ConvivenciaDailyLogPolicy
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessService->canManageDailyLogs($user) || $this->accessService->canViewCases($user);
    }

    public function view(User $user, ConvivenciaDailyLog $dailyLog): bool
    {
        return $this->accessService->canViewDailyLog($user, $dailyLog);
    }

    public function create(User $user): bool
    {
        return $this->accessService->canManageDailyLogs($user);
    }

    public function update(User $user, ConvivenciaDailyLog $dailyLog): bool
    {
        return $this->accessService->canManageDailyLogs($user) && $this->accessService->canViewDailyLog($user, $dailyLog);
    }

    public function delete(User $user, ConvivenciaDailyLog $dailyLog): bool
    {
        return $this->update($user, $dailyLog);
    }
}
