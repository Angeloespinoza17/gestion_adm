<?php

namespace Tests\Feature\Library;

use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BibliotecaCatalogsStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogs_return_every_student_without_the_previous_five_hundred_record_limit(): void
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->syncWithoutDetaching($role);
        Sanctum::actingAs($user);

        StudentProfile::factory()->count(501)->create();
        $studentCount = StudentProfile::query()->count();

        $this->assertGreaterThan(500, $studentCount);

        $this->getJson('/api/biblioteca/catalogs')
            ->assertOk()
            ->assertJsonCount($studentCount, 'students');
    }
}
