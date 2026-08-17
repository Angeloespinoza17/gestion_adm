<?php

namespace App\Policies\LibroDigital;

use App\Models\LibroDigital\ClassSession;
use App\Models\User;
use App\Services\LibroDigital\LibroDigitalAccessContext;

class ClassSessionPolicy
{
    public function __construct(private readonly LibroDigitalAccessContext $access) {}

    public function view(User $user, ClassSession $session): bool
    {
        return $user->hasPermission('libro_digital.sessions.view')
            && $this->access->canAccessSchool($user, (int) $session->school_id);
    }

    public function update(User $user, ClassSession $session): bool
    {
        if (! $user->hasPermission('libro_digital.sessions.manage')
            || ! $this->access->canAccessSchool($user, (int) $session->school_id)) {
            return false;
        }

        return $this->hasManagementOverride($user)
            || ($user->staff_id !== null && in_array((int) $user->staff_id, [
                (int) $session->scheduled_teacher_id,
                (int) $session->actual_teacher_id,
                (int) $session->substitute_teacher_id,
            ], true));
    }

    public function attendance(User $user, ClassSession $session): bool
    {
        return $user->hasPermission('libro_digital.attendance.manage')
            && $this->access->canAccessSchool($user, (int) $session->school_id)
            && ($this->hasManagementOverride($user) || $this->isAssignedTeacher($user, $session));
    }

    public function lesson(User $user, ClassSession $session): bool
    {
        return $user->hasPermission('libro_digital.lesson.manage')
            && $this->access->canAccessSchool($user, (int) $session->school_id)
            && ($this->hasManagementOverride($user) || $this->isAssignedTeacher($user, $session));
    }

    public function sign(User $user, ClassSession $session): bool
    {
        return $user->hasPermission('libro_digital.sign')
            && $this->access->canAccessSchool($user, (int) $session->school_id)
            && $this->isAssignedTeacher($user, $session);
    }

    private function isAssignedTeacher(User $user, ClassSession $session): bool
    {
        return $user->staff_id !== null && (int) $user->staff_id === (int) $session->actual_teacher_id;
    }

    private function hasManagementOverride(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('libro_digital.books.manage')
            || $user->hasPermission('libro_digital.closures.manage');
    }
}
