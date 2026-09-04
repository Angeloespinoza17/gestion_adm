<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\School;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedagogicalCoordinatorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_based_coordinator_is_listed_and_receives_school_access_when_assigned(): void
    {
        $configurator = User::factory()->create(['active' => true]);
        $configurator->roles()->sync([
            Role::query()->where('slug', 'super_admin')->firstOrFail()->id,
        ]);
        $coordinator = User::factory()->create(['active' => true]);
        $coordinator->roles()->sync([
            Role::query()->where('slug', 'coordinadora_academica')->firstOrFail()->id,
        ]);
        $school = School::query()->create([
            'rbd' => '70123-4',
            'name' => 'Colegio de prueba',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2037,
            'name' => 'Año escolar 2037',
            'starts_at' => '2037-03-01',
            'ends_at' => '2037-12-20',
            'is_active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        $level = EducationLevel::factory()->create([
            'name' => 'Nivel coordinación 2037',
            'order' => 937,
            'type' => 'media',
        ]);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '1° Medio Coordinación',
            'section_name' => 'A',
            'active' => true,
        ]);

        $this->assertDatabaseMissing('lcd_school_users', [
            'school_id' => $school->id,
            'user_id' => $coordinator->id,
        ]);

        $this->actingAs($configurator)
            ->getJson('/api/pedagogical-management/coordinator-assignments?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $coordinator->id,
                'name' => $coordinator->name,
                'has_school_access' => false,
            ]);

        $this->actingAs($configurator)
            ->putJson('/api/pedagogical-management/coordinator-assignments', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'coordinator_user_id' => $coordinator->id,
                'course_ids' => [$course->id],
                'education_level_ids' => [],
            ])
            ->assertOk();

        $this->assertDatabaseHas('lcd_school_users', [
            'school_id' => $school->id,
            'user_id' => $coordinator->id,
            'active' => true,
            'assigned_by' => $configurator->id,
            'valid_to' => null,
        ]);
        $this->assertDatabaseHas('pedagogical_coordinator_assignments', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'coordinator_user_id' => $coordinator->id,
            'target_type' => 'course',
            'target_id' => $course->id,
        ]);

        $this->actingAs($configurator)
            ->getJson('/api/pedagogical-management/coordinator-assignments?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $coordinator->id,
                'has_school_access' => true,
            ]);
    }
}
