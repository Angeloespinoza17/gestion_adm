<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\SyncPedagogicalCoordinatorAssignmentsRequest;
use App\Models\CourseSection;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalCoordinatorAssignment;
use App\Models\User;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\PedagogicalManagement\PedagogicalCoordinatorAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedagogicalCoordinatorAssignmentController extends Controller
{
    public function index(Request $request, LibroDigitalAccessContext $access): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pedagogical-coordinators.configure'), 403);
        $request->validate([
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            'coordinator_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);
        $school = School::query()->findOrFail($request->integer('school_id'));
        abort_unless($access->canAccessSchool($request->user(), $school->id), 403);

        $years = $school->academicYears()->orderByDesc('academic_years.year')->get([
            'academic_years.id', 'academic_years.name', 'academic_years.year', 'academic_years.is_active',
        ])->unique('id')->values();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_active', true)?->id ?: $years->first()?->id);
        $courses = CourseSection::query()->where('academic_year_id', $yearId)->where('active', true)
            ->with('educationLevel:id,name,type,order')
            ->orderBy('display_name')->get(['id', 'academic_year_id', 'education_level_id', 'display_name']);
        $levels = $courses->pluck('educationLevel')->filter()->unique('id')->sortBy('order')->values()
            ->map(fn ($level): array => ['id' => $level->id, 'name' => $level->name, 'type' => $level->type]);
        $coordinators = User::query()->where('users.active', true)
            ->whereHas('roles', fn (Builder $roles) => $roles
                ->whereIn('roles.slug', PedagogicalCoordinatorAssignmentService::COORDINATOR_ROLE_SLUGS)
                ->where('roles.active', true))
            ->with(['roles' => fn ($roles) => $roles
                ->whereIn('roles.slug', PedagogicalCoordinatorAssignmentService::COORDINATOR_ROLE_SLUGS)
                ->select('roles.id', 'roles.slug', 'roles.name')])
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);
        $today = today()->toDateString();
        $activeSchoolUserIds = DB::table('lcd_school_users')
            ->where('school_id', $school->id)
            ->whereIn('user_id', $coordinators->pluck('id'))
            ->where('active', true)
            ->where(fn ($dates) => $dates->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($dates) => $dates->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->pluck('user_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true]);
        $coordinators = $coordinators
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'has_school_access' => isset($activeSchoolUserIds[(int) $user->id]),
            ]);

        $assignments = PedagogicalCoordinatorAssignment::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $yearId)
            ->when($request->integer('coordinator_user_id'), fn (Builder $query, int $id) => $query->where('coordinator_user_id', $id))
            ->get(['id', 'coordinator_user_id', 'target_type', 'target_id']);

        return response()->json(['data' => [
            'school' => ['id' => $school->id, 'name' => $school->name],
            'academic_years' => $years,
            'selected_academic_year_id' => $yearId,
            'coordinators' => $coordinators,
            'courses' => $courses->map(fn (CourseSection $course): array => [
                'id' => $course->id,
                'name' => $course->display_name,
                'education_level_id' => $course->education_level_id,
            ]),
            'education_levels' => $levels,
            'assignments' => $assignments,
        ]]);
    }

    public function update(
        SyncPedagogicalCoordinatorAssignmentsRequest $request,
        PedagogicalCoordinatorAssignmentService $service,
        LibroDigitalAccessContext $access,
    ): JsonResponse {
        $data = $request->validated();
        abort_unless($access->canAccessSchool($request->user(), (int) $data['school_id']), 403);
        $service->sync($data, $request->user());

        return response()->json(['message' => 'Cursos y niveles asignados correctamente.']);
    }
}
