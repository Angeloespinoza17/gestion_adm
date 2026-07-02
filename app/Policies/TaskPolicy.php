<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;

class TaskPolicy
{
    public function __construct(
        private readonly TaskAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ver_tareas') || $user->isSuperAdmin();
    }

    public function view(User $user, Task $task): bool
    {
        return $this->accessService->canView($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('ver_tareas') || $user->isSuperAdmin();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->accessService->canUpdate($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->accessService->canDelete($user, $task);
    }

    public function manageAssigners(User $user): bool
    {
        return $this->accessService->canManageAssigners($user);
    }
}
