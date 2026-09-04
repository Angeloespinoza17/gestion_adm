<?php

namespace App\Policies\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\User;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationAccessService;

class ClassPresentationPolicy
{
    public function __construct(private readonly ClassPresentationAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('class-presentations.view');
    }

    public function view(User $user, ClassPresentation $presentation): bool
    {
        return $this->access->canView($user, $presentation);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('class-presentations.create');
    }

    public function download(User $user, ClassPresentation $presentation): bool
    {
        return $user->hasPermission('class-presentations.download') && $this->access->canView($user, $presentation);
    }

    public function regenerate(User $user, ClassPresentation $presentation): bool
    {
        return $user->hasPermission('class-presentations.regenerate')
            && $this->access->canView($user, $presentation)
            && $presentation->status !== ClassPresentationStatus::Archived;
    }

    public function archive(User $user, ClassPresentation $presentation): bool
    {
        return $user->hasPermission('class-presentations.archive')
            && $this->access->canView($user, $presentation)
            && $presentation->status !== ClassPresentationStatus::Archived;
    }
}
