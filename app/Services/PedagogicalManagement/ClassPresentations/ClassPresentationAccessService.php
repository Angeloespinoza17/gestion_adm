<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClassPresentationAccessService
{
    public function schoolsFor(User $user): Builder
    {
        return School::query()->where('active', true)
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $today = Carbon::today()->toDateString();
                $query->whereHas('users', fn (Builder $membership) => $membership
                    ->where('users.id', $user->id)
                    ->where('lcd_school_users.active', true)
                    ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_from')->orWhere('lcd_school_users.valid_from', '<=', $today))
                    ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_to')->orWhere('lcd_school_users.valid_to', '>=', $today))
                );
            });
    }

    public function canAccessSchool(User $user, int $schoolId): bool
    {
        return $this->schoolsFor($user)->whereKey($schoolId)->exists();
    }

    public function visibleQuery(User $user): Builder
    {
        return ClassPresentation::query()
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query
                ->whereIn('school_id', $this->schoolsFor($user)->select('lcd_schools.id'))
                ->when(! $user->hasPermission('class-presentations.view-all'), fn (Builder $own) => $own->where('user_id', $user->id)));
    }

    public function canView(User $user, ClassPresentation $presentation): bool
    {
        return $user->hasPermission('class-presentations.view')
            && $this->canAccessSchool($user, (int) $presentation->school_id)
            && ($user->isSuperAdmin() || $user->hasPermission('class-presentations.view-all') || (int) $presentation->user_id === (int) $user->id);
    }
}
