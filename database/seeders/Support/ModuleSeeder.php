<?php

namespace Database\Seeders\Support;

use App\Models\AcademicYear;
use App\Models\Cargo;
use App\Models\Commune;
use App\Models\ContractTemplate;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventorySubcategory;
use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenanceDependency;
use App\Models\PermissionType;
use App\Models\Region;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffOrganigramRelation;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

abstract class ModuleSeeder extends Seeder
{
    protected function creator(): User
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@cnscgestion.cl');

        return User::query()
            ->where('email', $email)
            ->first()
            ?: User::query()->orderBy('id')->firstOrFail();
    }

    /**
     * @param  array<int, string>  $roleSlugs
     * @param  array<int, string>  $departmentSlugs
     * @return array{staff: Staff, user: User}
     */
    protected function upsertStaffUser(
        array $staffPayload,
        array $userPayload,
        array $roleSlugs = [],
        array $departmentSlugs = []
    ): array {
        $actor = $this->creator();
        $plainPassword = $userPayload['password'] ?? null;
        $cargoSlug = $staffPayload['cargo_slug'] ?? null;

        unset($userPayload['password'], $staffPayload['cargo_slug']);

        if ($cargoSlug) {
            $staffPayload['cargo_id'] = $this->cargo($cargoSlug)->id;
        }

        $staffPayload['created_by'] = $staffPayload['created_by'] ?? $actor->id;
        $staffPayload['updated_by'] = $actor->id;
        $staffPayload['active'] = $staffPayload['active'] ?? true;

        $staff = Staff::query()->updateOrCreate(
            ['rut' => $staffPayload['rut']],
            $staffPayload,
        );

        if ($departmentSlugs !== []) {
            $departmentIds = collect($departmentSlugs)
                ->map(fn (string $slug) => $this->department($slug)->id)
                ->all();

            $staff->departments()->sync($departmentIds);
        }

        $user = User::query()->where('staff_id', $staff->id)->first()
            ?: User::query()->where('email', $userPayload['email'])->first()
            ?: new User();

        $user->fill(array_merge([
            'staff_id' => $staff->id,
            'cargo_id' => $staff->cargo_id,
            'user_type' => 'staff',
            'active' => true,
            'name' => $staff->full_name,
        ], $userPayload));

        if (!$user->exists || $plainPassword !== null) {
            $user->password = Hash::make((string) ($plainPassword ?: 'Demo123!'));
        }

        $user->save();

        if ($roleSlugs !== []) {
            $roleIds = collect($roleSlugs)
                ->map(fn (string $slug) => $this->role($slug)->id)
                ->all();

            $user->roles()->sync($roleIds);
        }

        return [
            'staff' => $staff->fresh(['departments', 'user']),
            'user' => $user->fresh(['roles']),
        ];
    }

    protected function upsertOrganigramRelation(
        Staff $staff,
        Staff $relatedStaff,
        string $relationshipType,
        int $priority = 1,
        bool $isPrimary = true,
        ?string $notes = null,
    ): void {
        StaffOrganigramRelation::query()->updateOrCreate(
            [
                'staff_id' => $staff->id,
                'related_staff_id' => $relatedStaff->id,
                'relationship_type' => $relationshipType,
            ],
            [
                'priority' => $priority,
                'is_primary' => $isPrimary,
                'active' => true,
                'notes' => $notes,
                'created_by' => $this->creator()->id,
                'updated_by' => $this->creator()->id,
            ],
        );
    }

    protected function role(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->firstOrFail();
    }

    protected function cargo(string $slug): Cargo
    {
        return Cargo::query()->where('slug', $slug)->firstOrFail();
    }

    protected function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    protected function staffByEmail(string $email): Staff
    {
        return Staff::query()
            ->where('institutional_email', $email)
            ->orWhere('personal_email', $email)
            ->firstOrFail();
    }

    protected function student(string $rut): StudentProfile
    {
        return StudentProfile::query()->where('rut', $rut)->firstOrFail();
    }

    protected function activeEnrollment(StudentProfile $student, ?AcademicYear $academicYear = null): ?StudentEnrollment
    {
        $academicYear ??= $this->activeAcademicYear();

        return StudentEnrollment::query()
            ->with(['academicYear', 'courseSection.educationLevel'])
            ->where('student_profile_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->first();
    }

    protected function department(string $slug): Department
    {
        return Department::query()->where('slug', $slug)->firstOrFail();
    }

    protected function region(string $name): ?Region
    {
        return Region::query()->where('name', $name)->first();
    }

    protected function commune(string $name): ?Commune
    {
        return Commune::query()->where('name', $name)->first();
    }

    protected function permissionType(string $name): PermissionType
    {
        return PermissionType::query()->where('name', $name)->firstOrFail();
    }

    protected function activeAcademicYear(): AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->firstOrFail();
    }

    protected function academicYear(int $year): AcademicYear
    {
        return AcademicYear::query()->where('year', $year)->firstOrFail();
    }

    protected function course(int $year, string $levelName, string $sectionName): CourseSection
    {
        return CourseSection::query()
            ->whereHas('academicYear', fn ($query) => $query->where('year', $year))
            ->whereHas('educationLevel', fn ($query) => $query->where('name', $levelName))
            ->where('section_name', $sectionName)
            ->firstOrFail();
    }

    protected function dependency(string $code): MaintenanceDependency
    {
        return MaintenanceDependency::query()->where('code', $code)->firstOrFail();
    }

    protected function category(string $slug): InventoryCategory
    {
        return InventoryCategory::query()->where('slug', $slug)->firstOrFail();
    }

    protected function subcategory(string $categorySlug, string $subcategorySlug): InventorySubcategory
    {
        return InventorySubcategory::query()
            ->whereHas('category', fn ($query) => $query->where('slug', $categorySlug))
            ->where('slug', $subcategorySlug)
            ->firstOrFail();
    }

    protected function contractTemplate(string $slug): ContractTemplate
    {
        return ContractTemplate::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MaintenanceChecklistItem>
     */
    protected function ensureChecklistItems(): Collection
    {
        $existing = MaintenanceChecklistItem::query()
            ->where('active', true)
            ->orderBy('system')
            ->orderBy('subdimension')
            ->orderBy('id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $defaults = [
            ['system' => 'Infraestructura', 'subdimension' => 'Muros y cielo', 'review' => 'Revisar humedad, fisuras o daños visibles.'],
            ['system' => 'Electricidad', 'subdimension' => 'Enchufes e iluminación', 'review' => 'Verificar funcionamiento y fijaciones.'],
            ['system' => 'Seguridad', 'subdimension' => 'Señalética y extintores', 'review' => 'Confirmar visibilidad y vigencia.'],
        ];

        foreach ($defaults as $item) {
            MaintenanceChecklistItem::query()->updateOrCreate(
                [
                    'system' => $item['system'],
                    'subdimension' => $item['subdimension'],
                    'review' => $item['review'],
                ],
                ['active' => true],
            );
        }

        return MaintenanceChecklistItem::query()
            ->where('active', true)
            ->orderBy('system')
            ->orderBy('subdimension')
            ->orderBy('id')
            ->get();
    }
}
