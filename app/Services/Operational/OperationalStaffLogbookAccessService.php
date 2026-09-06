<?php

namespace App\Services\Operational;

use App\Models\Operational\OperationalStaffLogEntry;
use App\Models\Role;
use App\Models\User;

class OperationalStaffLogbookAccessService
{
    public function isStaffAccount(User $user): bool
    {
        return $user->isStaffAccount();
    }

    public function canView(User $user): bool
    {
        return $user->active
            && ($user->isSuperAdmin() || ($this->isStaffAccount($user) && ! $this->hasHomeOnlyAccess($user)));
    }

    public function canCreate(User $user): bool
    {
        return $user->active
            && $this->isStaffAccount($user)
            && ! $this->hasHomeOnlyAccess($user);
    }

    public function canUpdate(User $user, OperationalStaffLogEntry $entry): bool
    {
        return $this->canCreate($user)
            && $entry->owner_user_id !== null
            && (int) $entry->owner_user_id === (int) $user->id;
    }

    private function hasHomeOnlyAccess(User $user): bool
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles->contains('slug', Role::TEMPORARY_HOME_ONLY_SLUG);
        }

        return $user->roles()->where('slug', Role::TEMPORARY_HOME_ONLY_SLUG)->exists();
    }
}
