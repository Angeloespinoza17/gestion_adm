<?php

namespace App\Policies;

use App\Models\Psychology\PsychologyCase;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;

class PsychologyCasePolicy
{
    public function __construct(private readonly PsychologyAccessService $access) {}

    public function view(User $user, PsychologyCase $case): bool
    {
        return $this->access->canViewCase($user, $case);
    }

    public function update(User $user, PsychologyCase $case): bool
    {
        return $this->access->canViewCase($user, $case) && $case->status !== 'closed' && ($user->hasPermission('psychology.sessions.create') || $user->hasPermission('psychology.cases.reassign'));
    }

    public function close(User $user, PsychologyCase $case): bool
    {
        return $this->access->canViewCase($user, $case) && $user->hasPermission('psychology.cases.close');
    }

    public function reopen(User $user, PsychologyCase $case): bool
    {
        return $this->access->canViewCase($user, $case) && $user->hasPermission('psychology.cases.reopen');
    }
}
