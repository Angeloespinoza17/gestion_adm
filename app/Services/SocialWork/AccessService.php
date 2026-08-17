<?php

namespace App\Services\SocialWork;

use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessService
{
    public function applyCaseVisibility(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasPermission('social_work.highly_confidential.view')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('responsible_user_id', $user->id);

            if ($user->hasPermission('social_work.confidential.view')) {
                $visible->orWhere('confidentiality', '!=', 'altamente_restringido');
            } else {
                $visible->orWhere('confidentiality', 'interno');
            }
        });
    }

    public function canViewCase(User $user, SocialCase $case): bool
    {
        return $this->applyCaseVisibility(SocialCase::query()->whereKey($case), $user)->exists();
    }

    public function canViewLevel(User $user, string $level): bool
    {
        return match ($level) {
            'altamente_restringido' => $user->hasPermission('social_work.highly_confidential.view'),
            'restringido' => $user->hasPermission('social_work.confidential.view') || $user->hasPermission('social_work.highly_confidential.view'),
            default => $user->hasPermission('social_work.cases.view') || $user->hasPermission('social_work.students.view'),
        };
    }
}
