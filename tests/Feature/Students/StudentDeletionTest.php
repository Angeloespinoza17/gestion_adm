<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentMovement;
use App\Models\StudentProfile;
use App\Models\StudentPromotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_impact_reports_records_that_will_be_deleted_or_preserved(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['eliminar_estudiantes']));
        $context = $this->createStudentWithAcademicHistory();

        $this->getJson("/api/students/{$context['student']->id}/deletion-impact")
            ->assertOk()
            ->assertJsonPath('data.account.exists', false)
            ->assertJsonPath('data.delete_total', 3)
            ->assertJsonPath('data.preserve_total', 1)
            ->assertJsonFragment(['label' => 'Matrículas', 'count' => 1])
            ->assertJsonFragment(['label' => 'Movimientos de matrícula', 'count' => 1])
            ->assertJsonFragment(['label' => 'Promociones académicas', 'count' => 1])
            ->assertJsonFragment(['label' => 'Objetos recibidos en portería', 'count' => 1]);
    }

    public function test_deleting_an_orphan_student_removes_restricting_records_and_preserves_history(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['eliminar_estudiantes']));
        $context = $this->createStudentWithAcademicHistory();

        $this->deleteJson("/api/students/{$context['student']->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ficha de estudiante eliminada correctamente.');

        $this->assertDatabaseMissing('student_profiles', ['id' => $context['student']->id]);
        $this->assertDatabaseMissing('student_enrollments', ['id' => $context['enrollment']->id]);
        $this->assertDatabaseMissing('student_enrollment_movements', ['id' => $context['movement']->id]);
        $this->assertDatabaseMissing('student_promotions', ['id' => $context['promotion']->id]);
        $this->assertDatabaseHas('porter_received_items', [
            'id' => $context['received_item_id'],
            'student_profile_id' => null,
        ]);
    }

    public function test_deleting_a_student_removes_its_linked_account_in_the_same_operation(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['eliminar_estudiantes']));
        $student = $this->createStudent();
        $linkedUser = User::factory()->create([
            'student_id' => $student->id,
            'user_type' => 'student',
        ]);

        $this->deleteJson("/api/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Estudiante y cuenta de acceso eliminadas correctamente.');

        $this->assertDatabaseMissing('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseMissing('student_profiles', ['id' => $student->id]);
    }

    public function test_deleting_a_student_user_from_admin_also_removes_the_profile(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));
        $context = $this->createStudentWithAcademicHistory();
        $linkedUser = User::factory()->create([
            'student_id' => $context['student']->id,
            'user_type' => 'student',
        ]);

        $this->deleteJson("/api/admin/users/{$linkedUser->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Usuario y ficha de estudiante eliminados correctamente.');

        $this->assertDatabaseMissing('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseMissing('student_profiles', ['id' => $context['student']->id]);
        $this->assertDatabaseMissing('student_enrollments', ['id' => $context['enrollment']->id]);
    }

    public function test_bulk_user_deletion_reports_and_removes_linked_student_profiles(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));
        $student = $this->createStudent();
        $linkedUser = User::factory()->create([
            'student_id' => $student->id,
            'user_type' => 'student',
        ]);

        $this->deleteJson('/api/admin/users/bulk', ['users' => [$linkedUser->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('deleted_student_count', 1);

        $this->assertDatabaseMissing('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseMissing('student_profiles', ['id' => $student->id]);
    }

    public function test_an_orphan_student_account_can_be_recreated(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['editar_estudiantes']));
        Role::query()->create([
            'name' => 'Estudiante',
            'slug' => 'estudiante',
            'active' => true,
        ]);
        $student = $this->createStudent();

        $this->postJson("/api/students/{$student->id}/account")
            ->assertCreated()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('users', [
            'student_id' => $student->id,
            'user_type' => 'student',
            'active' => true,
        ]);
    }

    public function test_updating_an_orphan_student_does_not_silently_recreate_its_account(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['editar_estudiantes']));
        $student = $this->createStudent();

        $this->putJson("/api/students/{$student->id}", [
            'first_name' => 'Nombre actualizado',
            'account_active' => true,
        ])->assertOk();

        $this->assertDatabaseHas('student_profiles', [
            'id' => $student->id,
            'first_name' => 'Nombre actualizado',
        ]);
        $this->assertDatabaseMissing('users', ['student_id' => $student->id]);
    }

    /**
     * @return array{student: StudentProfile, enrollment: StudentEnrollment, movement: StudentEnrollmentMovement, promotion: StudentPromotion, received_item_id: int}
     */
    private function createStudentWithAcademicHistory(): array
    {
        $student = $this->createStudent();
        $year = AcademicYear::query()->create([
            'name' => 'Año de prueba',
            'year' => 2099,
            'is_active' => true,
        ]);
        $level = EducationLevel::query()->create([
            'name' => 'Nivel '.Str::random(8),
            'order' => 999,
            'type' => 'basica',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => 'Curso de prueba A',
            'active' => true,
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'matriculada',
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $course->display_name,
        ]);
        $movement = StudentEnrollmentMovement::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'movement_type' => 'matricula',
            'snapshot_year_name' => $year->name,
        ]);
        $promotion = StudentPromotion::query()->create([
            'student_profile_id' => $student->id,
            'from_academic_year_id' => $year->id,
            'from_course_section_id' => $course->id,
            'promotion_status' => 'promovida',
        ]);
        $receivedItemId = DB::table('porter_received_items')->insertGetId([
            'recipient_type' => 'student',
            'student_profile_id' => $student->id,
            'received_at' => now(),
            'received_from_name' => 'Apoderado de prueba',
            'item_type' => 'otro',
            'description' => 'Objeto histórico que debe conservarse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('student', 'enrollment', 'movement', 'promotion') + [
            'received_item_id' => $receivedItemId,
        ];
    }

    private function createStudent(): StudentProfile
    {
        return StudentProfile::query()->create([
            'first_name' => 'Estudiante',
            'last_name' => 'Prueba '.Str::random(8),
            'rut' => (string) random_int(10000000, 99999999),
            'general_status' => 'activo',
        ]);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.Str::random(8),
            'slug' => 'rol_'.Str::lower(Str::random(12)),
            'active' => true,
        ]);
        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));
        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);

        return $user;
    }
}
