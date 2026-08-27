<?php

namespace App\Policies\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\User;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAccessService;

class PedagogicalInstrumentPolicy
{
    public function __construct(private readonly PedagogicalInstrumentAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pedagogical-instruments.view');
    }

    public function view(User $user, PedagogicalInstrument $instrument): bool
    {
        return $this->access->canView($user, $instrument);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pedagogical-instruments.create');
    }

    public function update(User $user, PedagogicalInstrument $instrument): bool
    {
        return $this->access->canManage($user, $instrument, 'pedagogical-instruments.update');
    }

    public function archive(User $user, PedagogicalInstrument $instrument): bool
    {
        return $this->access->canManage($user, $instrument, 'pedagogical-instruments.archive');
    }

    public function download(User $user, PedagogicalInstrument $instrument): bool
    {
        return $user->hasPermission('pedagogical-instruments.download')
            && $this->access->canView($user, $instrument);
    }

    public function analyze(User $user, PedagogicalInstrument $instrument): bool
    {
        return $this->access->canManage($user, $instrument, 'pedagogical-instruments.analyze');
    }

    public function resolveValidations(User $user, PedagogicalInstrument $instrument): bool
    {
        return $this->access->canManage($user, $instrument, 'pedagogical-instruments.resolve-validations');
    }

    public function review(User $user, PedagogicalInstrument $instrument): bool
    {
        return $this->access->canReview($user, $instrument);
    }

    public function generateAiReport(User $user, PedagogicalInstrument $instrument): bool
    {
        return $user->hasPermission('pedagogical-instruments.ai-report')
            && $this->access->canReview($user, $instrument);
    }
}
