<?php

namespace App\Policies\LibroDigital;

use App\Models\Schedule\ScheduleSubject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('libro_digital.books.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('libro_digital.subject_catalog.manage');
    }

    public function update(User $user, ScheduleSubject $subject): bool
    {
        return $user->hasPermission('libro_digital.subject_catalog.manage');
    }
}
