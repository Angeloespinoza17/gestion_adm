<?php

namespace App\Services\Psychology;

use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PsychologyAccessService
{
    public function applyReferralVisibility(Builder $query, User $user, bool $aggregate = false): Builder
    {
        if ($this->isUnprivilegedSuperAdmin($user)) {
            return $aggregate ? $query : $query->whereRaw('1 = 0');
        }
        if ($aggregate && $this->canSeeAllAggregates($user)) {
            return $query;
        }
        if ($user->hasPermission('psychology.referrals.view_all')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('referred_by_user_id', $user->id)
                ->orWhere('assigned_user_id', $user->id)
                ->orWhereHas('assignments', fn (Builder $assignment) => $assignment->where('user_id', $user->id)->whereNull('ended_at'));
        });
    }

    public function canViewReferral(User $user, PsychologyReferral $referral): bool
    {
        if (! $user->hasPermission('psychology.access')) {
            return false;
        }

        return $this->applyReferralVisibility(PsychologyReferral::query()->whereKey($referral), $user)->exists();
    }

    public function applyCaseVisibility(Builder $query, User $user, bool $aggregate = false): Builder
    {
        if ($this->isUnprivilegedSuperAdmin($user)) {
            return $aggregate ? $query : $query->whereRaw('1 = 0');
        }
        if ($aggregate && $this->canSeeAllAggregates($user)) {
            return $query;
        }
        if ($user->hasPermission('psychology.cases.view_all')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('responsible_user_id', $user->id)
                ->orWhereHas('assignments', fn (Builder $assignment) => $assignment->where('user_id', $user->id)->whereNull('ended_at'))
                ->orWhereHas('collaborators', fn (Builder $participant) => $participant->where('users.id', $user->id));
        });
    }

    public function canViewCase(User $user, PsychologyCase $case): bool
    {
        if (! $user->hasPermission('psychology.access')) {
            return false;
        }

        return $this->applyCaseVisibility(PsychologyCase::query()->whereKey($case), $user)->exists();
    }

    public function canViewPrivateNotes(User $user, PsychologyCase $case): bool
    {
        if (! $this->canViewCase($user, $case)) {
            return false;
        }

        return $this->hasExplicitPermission($user, 'psychology.sessions.view_private')
            || $this->hasExplicitPermission($user, 'psychology.sensitive.override');
    }

    public function canViewRisk(User $user, PsychologyCase $case): bool
    {
        return $this->canViewCase($user, $case)
            && ($user->hasPermission('psychology.risk.view') || $user->hasPermission('psychology.risk.create'));
    }

    public function hasExplicitPermission(User $user, string $slug): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->roles()->whereHas('permissions', fn (Builder $permission) => $permission->where('slug', $slug)->where('active', true))->exists();
    }

    public function canAccessNominalDomain(User $user): bool
    {
        return ! $this->isUnprivilegedSuperAdmin($user);
    }

    private function isUnprivilegedSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin() && ! $this->hasExplicitPermission($user, 'psychology.sensitive.override');
    }

    private function canSeeAllAggregates(User $user): bool
    {
        return $user->hasPermission('psychology.cases.view_all')
            || ($user->hasPermission('psychology.reports.aggregate')
                && ! $user->hasPermission('psychology.cases.view_assigned'));
    }
}
