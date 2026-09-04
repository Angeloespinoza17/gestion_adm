<?php

namespace App\Services\PedagogicalManagement;

use App\Models\CourseSection;
use App\Models\PedagogicalManagement\PedagogicalCoordinatorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedagogicalCoordinatorAssignmentService
{
    public const COORDINATOR_ROLE_SLUGS = ['coordinadora_academica', 'coordinador_academico'];

    /** @param array<string,mixed> $data */
    public function sync(array $data, User $actor): void
    {
        $schoolId = (int) $data['school_id'];
        $yearId = (int) $data['academic_year_id'];
        $coordinatorId = (int) $data['coordinator_user_id'];
        $courseIds = array_values(array_unique(array_map('intval', (array) ($data['course_ids'] ?? []))));
        $levelIds = array_values(array_unique(array_map('intval', (array) ($data['education_level_ids'] ?? []))));

        if (! DB::table('lcd_school_academic_years')->where('school_id', $schoolId)->where('academic_year_id', $yearId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'El año académico no está habilitado para el establecimiento.']);
        }
        $coordinator = User::query()->whereKey($coordinatorId)->where('active', true)
            ->whereHas('roles', fn ($roles) => $roles
                ->whereIn('roles.slug', self::COORDINATOR_ROLE_SLUGS)
                ->where('roles.active', true))
            ->with(['roles' => fn ($roles) => $roles
                ->whereIn('roles.slug', self::COORDINATOR_ROLE_SLUGS)
                ->where('roles.active', true)
                ->select('roles.id', 'roles.slug', 'roles.name')])
            ->first();
        if (! $coordinator) {
            throw ValidationException::withMessages(['coordinator_user_id' => 'La persona seleccionada no tiene un rol activo de coordinación académica.']);
        }

        $courses = CourseSection::query()->whereIn('id', $courseIds)->where('academic_year_id', $yearId)->where('active', true)->get(['id', 'education_level_id']);
        if ($courses->count() !== count($courseIds)) {
            throw ValidationException::withMessages(['course_ids' => 'Uno o más cursos no pertenecen al año académico seleccionado.']);
        }
        $availableLevelIds = CourseSection::query()->where('academic_year_id', $yearId)->where('active', true)->whereIn('education_level_id', $levelIds)->distinct()->pluck('education_level_id');
        if ($availableLevelIds->count() !== count($levelIds)) {
            throw ValidationException::withMessages(['education_level_ids' => 'Uno o más niveles no tienen cursos activos en el año seleccionado.']);
        }

        DB::transaction(function () use ($schoolId, $yearId, $coordinatorId, $courseIds, $levelIds, $actor, $coordinator): void {
            $this->ensureCurrentSchoolAccess($schoolId, $coordinator, $actor);

            PedagogicalCoordinatorAssignment::query()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $yearId)
                ->where('coordinator_user_id', $coordinatorId)
                ->delete();

            $now = now();
            $rows = collect($courseIds)->map(fn (int $id): array => [
                'school_id' => $schoolId,
                'academic_year_id' => $yearId,
                'coordinator_user_id' => $coordinatorId,
                'target_type' => PedagogicalCoordinatorAssignment::TARGET_COURSE,
                'target_id' => $id,
                'assigned_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->merge(collect($levelIds)->map(fn (int $id): array => [
                'school_id' => $schoolId,
                'academic_year_id' => $yearId,
                'coordinator_user_id' => $coordinatorId,
                'target_type' => PedagogicalCoordinatorAssignment::TARGET_LEVEL,
                'target_id' => $id,
                'assigned_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]))->values()->all();
            if ($rows !== []) {
                PedagogicalCoordinatorAssignment::query()->insert($rows);
            }
        }, 3);
    }

    private function ensureCurrentSchoolAccess(int $schoolId, User $coordinator, User $actor): void
    {
        $today = today()->toDateString();
        $membership = DB::table('lcd_school_users')
            ->where('school_id', $schoolId)
            ->where('user_id', $coordinator->id)
            ->first(['id', 'valid_from']);
        $role = $coordinator->roles->first();

        $attributes = [
            'role_snapshot' => $role?->name ?? 'Coordinación académica',
            'valid_from' => ! $membership?->valid_from || $membership->valid_from > $today
                ? $today
                : $membership->valid_from,
            'valid_to' => null,
            'active' => true,
            'assigned_by' => $actor->id,
            'updated_at' => now(),
        ];

        if ($membership) {
            DB::table('lcd_school_users')->where('id', $membership->id)->update($attributes);

            return;
        }

        DB::table('lcd_school_users')->insert([
            'school_id' => $schoolId,
            'user_id' => $coordinator->id,
            'permission_scope' => json_encode(['source' => 'pedagogical_coordinator_assignment']),
            'created_at' => now(),
            ...$attributes,
        ]);
    }
}
