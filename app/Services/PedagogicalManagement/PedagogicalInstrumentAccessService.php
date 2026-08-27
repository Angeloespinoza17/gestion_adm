<?php

namespace App\Services\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\User;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedagogicalInstrumentAccessService
{
    public function __construct(
        private readonly LibroDigitalAccessContext $schoolContext,
    ) {}

    public function resolveSchool(Request $request)
    {
        $user = $request->user();
        $schoolId = $request->integer('school_id');
        if ($user?->hasPermission('pedagogical-instruments.view') && $schoolId) {
            $institutionalSchool = $this->institutionalSchoolQuery()->whereKey($schoolId)->first();
            if ($institutionalSchool) {
                return $institutionalSchool;
            }
        }

        return $this->schoolContext->resolveSchool($request);
    }

    public function canAccessSchool(User $user, int $schoolId): bool
    {
        if ($user->hasPermission('pedagogical-instruments.view') && $this->institutionalSchoolQuery()->whereKey($schoolId)->exists()) {
            return true;
        }

        return $this->schoolContext->canAccessSchool($user, $schoolId);
    }

    public function visibleQuery(User $user): Builder
    {
        $query = PedagogicalInstrument::query();
        if (! $user->hasPermission('pedagogical-instruments.view-all')) {
            $query->where(function (Builder $visibility) use ($user): void {
                $visibility->where('owner_user_id', $user->id);
                if ($user->hasPermission('pedagogical-instruments.review-assigned')) {
                    $visibility->orWhereHas('courses', function (Builder $courses) use ($user): void {
                        $courses->whereExists(function ($assignments) use ($user): void {
                            $assignments->selectRaw('1')
                                ->from('pedagogical_coordinator_assignments as pca')
                                ->where('pca.coordinator_user_id', $user->id)
                                ->whereColumn('pca.school_id', 'pedagogical_instruments.school_id')
                                ->whereColumn('pca.academic_year_id', 'pedagogical_instruments.academic_year_id')
                                ->where(function ($target): void {
                                    $target->where(function ($course): void {
                                        $course->where('pca.target_type', 'course')
                                            ->whereColumn('pca.target_id', 'course_sections.id');
                                    })->orWhere(function ($level): void {
                                        $level->where('pca.target_type', 'level')
                                            ->whereColumn('pca.target_id', 'course_sections.education_level_id');
                                    });
                                });
                        });
                    });
                }
            });
        }

        if (! $user->isSuperAdmin()) {
            $defaultSchoolRbd = trim((string) config('libro_digital.default_school.rbd'));
            $query->where(function (Builder $schoolScope) use ($defaultSchoolRbd, $user): void {
                if ($defaultSchoolRbd !== '' && $user->hasPermission('pedagogical-instruments.view')) {
                    $schoolScope->whereHas('school', fn (Builder $school) => $school
                        ->where('active', true)->where('rbd', $defaultSchoolRbd));
                }
                $method = $defaultSchoolRbd !== '' && $user->hasPermission('pedagogical-instruments.view')
                    ? 'orWhereHas'
                    : 'whereHas';
                $schoolScope->{$method}('school.users', fn (Builder $membership) => $membership
                    ->where('users.id', $user->id)
                    ->where('lcd_school_users.active', true)
                    ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_from')->orWhere('lcd_school_users.valid_from', '<=', today()))
                    ->where(fn (Builder $dates) => $dates->whereNull('lcd_school_users.valid_to')->orWhere('lcd_school_users.valid_to', '>=', today()))
                );
            });
        }

        return $query;
    }

    public function canView(User $user, PedagogicalInstrument $instrument): bool
    {
        return $user->hasPermission('pedagogical-instruments.view')
            && $this->canAccessSchool($user, (int) $instrument->school_id)
            && (
                $user->hasPermission('pedagogical-instruments.view-all')
                || (int) $instrument->owner_user_id === (int) $user->id
                || ($user->hasPermission('pedagogical-instruments.review-assigned') && $this->isAssigned($user, $instrument))
            );
    }

    public function canManage(User $user, PedagogicalInstrument $instrument, string $permission): bool
    {
        return $user->hasPermission($permission)
            && $this->canAccessSchool($user, (int) $instrument->school_id)
            && ($user->hasPermission('pedagogical-instruments.view-all') || (int) $instrument->owner_user_id === (int) $user->id);
    }

    public function canReview(User $user, PedagogicalInstrument $instrument): bool
    {
        return $user->hasPermission('pedagogical-instruments.decide')
            && $this->canAccessSchool($user, (int) $instrument->school_id)
            && ($user->hasPermission('pedagogical-instruments.view-all') || $this->isAssigned($user, $instrument));
    }

    public function isAssigned(User $user, PedagogicalInstrument $instrument): bool
    {
        $courseIds = $instrument->relationLoaded('courses')
            ? $instrument->courses->pluck('id')
            : $instrument->courses()->pluck('course_sections.id');
        if ($courseIds->isEmpty()) {
            return false;
        }

        return DB::table('pedagogical_coordinator_assignments as pca')
            ->where('pca.coordinator_user_id', $user->id)
            ->where('pca.school_id', $instrument->school_id)
            ->where('pca.academic_year_id', $instrument->academic_year_id)
            ->where(function ($scope) use ($courseIds): void {
                $scope->where(function ($courses) use ($courseIds): void {
                    $courses->where('pca.target_type', 'course')->whereIn('pca.target_id', $courseIds);
                })->orWhere(function ($levels) use ($courseIds): void {
                    $levels->where('pca.target_type', 'level')
                        ->whereIn('pca.target_id', DB::table('course_sections')->whereIn('id', $courseIds)->select('education_level_id'));
                });
            })
            ->exists();
    }

    private function institutionalSchoolQuery(): Builder
    {
        $rbd = trim((string) config('libro_digital.default_school.rbd'));

        return \App\Models\LibroDigital\School::query()
            ->where('active', true)
            ->when($rbd !== '', fn (Builder $query) => $query->where('rbd', $rbd), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}
