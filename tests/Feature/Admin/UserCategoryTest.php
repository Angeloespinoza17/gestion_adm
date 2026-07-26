<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_categorizing_a_user_as_staff_creates_a_linked_staff_profile(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios', 'ver_funcionarios']));

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Cuenta sin ficha',
            'email' => 'cuenta.sin.ficha@example.test',
            'password' => 'password-segura',
            'user_type' => 'staff',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_type', 'staff');

        $user = User::query()->where('email', 'cuenta.sin.ficha@example.test')->firstOrFail();
        $this->assertNotNull($user->staff_id);
        $this->assertDatabaseHas('staff', [
            'id' => $user->staff_id,
            'full_name' => 'Cuenta sin ficha',
            'institutional_email' => 'cuenta.sin.ficha@example.test',
            'active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'user_type' => 'staff',
            'staff_id' => $user->staff_id,
        ]);

        $this->getJson('/api/staff?search=Cuenta%20sin%20ficha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->staff_id)
            ->assertJsonPath('data.0.user.id', $user->id);
    }

    public function test_categorizing_an_existing_user_as_staff_links_an_existing_unassigned_profile(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $staff = Staff::query()->create([
            'full_name' => 'Ficha laboral existente',
            'institutional_email' => 'persona.existente@example.test',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Persona existente',
            'email' => 'persona.existente@example.test',
            'user_type' => null,
            'staff_id' => null,
        ]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'user_type' => 'staff',
        ])
            ->assertOk()
            ->assertJsonPath('data.user_type', 'staff')
            ->assertJsonPath('data.staff_id', $staff->id);

        $this->assertSame($staff->id, $user->fresh()->staff_id);
        $this->assertSame(1, Staff::query()->count());
        $this->assertSame('Ficha laboral existente', $staff->fresh()->full_name);
    }

    public function test_editing_an_unlinked_staff_user_creates_only_one_profile(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $user = User::factory()->create([
            'name' => 'Funcionario pendiente',
            'email' => 'funcionario.pendiente@example.test',
            'user_type' => 'staff',
            'staff_id' => null,
        ]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Funcionario pendiente',
            'user_type' => 'staff',
        ])
            ->assertOk();

        $staffId = $user->fresh()->staff_id;
        $this->assertNotNull($staffId);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Funcionario pendiente actualizado',
            'user_type' => 'staff',
        ])
            ->assertOk()
            ->assertJsonPath('data.staff_id', $staffId);

        $this->assertSame(1, Staff::query()->count());
    }

    public function test_staff_backfill_links_existing_and_creates_missing_profiles_without_destructive_down(): void
    {
        $existingStaff = Staff::query()->create([
            'full_name' => 'Ficha existente sin cuenta',
            'institutional_email' => 'ficha.existente@example.test',
            'status' => 'activo',
            'active' => true,
        ]);
        $existingProfileUser = User::factory()->create([
            'name' => 'Cuenta para ficha existente',
            'email' => 'ficha.existente@example.test',
            'user_type' => 'staff',
            'staff_id' => null,
        ]);
        $missingProfileUser = User::factory()->create([
            'name' => 'Cuenta que necesita ficha',
            'email' => 'ficha.nueva@example.test',
            'user_type' => 'staff',
            'staff_id' => null,
        ]);

        $migration = require database_path('migrations/2026_07_26_170000_backfill_staff_profiles_for_staff_users.php');
        $migration->up();

        $this->assertSame($existingStaff->id, $existingProfileUser->fresh()->staff_id);
        $this->assertNotNull($missingProfileUser->fresh()->staff_id);
        $this->assertSame(2, Staff::query()->count());

        $migration->down();

        $this->assertSame(2, Staff::query()->count());
        $this->assertNotNull($existingProfileUser->fresh()->staff_id);
        $this->assertNotNull($missingProfileUser->fresh()->staff_id);
    }

    public function test_preview_category_is_reserved_for_internal_accounts(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $this->postJson('/api/admin/users', [
            'name' => 'Cuenta técnica manual',
            'email' => 'vista.previa.manual@example.test',
            'password' => 'password-segura',
            'user_type' => 'role_preview',
            'active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_type');

        $previewUser = User::factory()->create([
            'user_type' => 'role_preview',
            'active' => true,
        ]);

        $this->putJson("/api/admin/users/{$previewUser->id}", [
            'user_type' => 'staff',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_type');

        $this->deleteJson("/api/admin/users/{$previewUser->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('users');

        $this->assertDatabaseHas('users', [
            'id' => $previewUser->id,
            'user_type' => 'role_preview',
        ]);
    }

    public function test_linked_staff_user_can_be_reclassified_without_deleting_staff_profile(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $staff = Staff::query()->create([
            'full_name' => 'Funcionario categorizado',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'staff_id' => $staff->id,
            'user_type' => 'staff',
            'active' => true,
        ]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'user_type' => 'student',
        ])
            ->assertOk()
            ->assertJsonPath('data.user_type', 'student')
            ->assertJsonPath('data.staff_id', null);

        $this->assertSame('student', $user->fresh()->user_type);
        $this->assertNull($user->fresh()->staff_id);
        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'full_name' => 'Funcionario categorizado',
        ]);
    }

    public function test_editing_a_linked_staff_user_keeps_the_profile_when_category_does_not_change(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $staff = Staff::query()->create([
            'full_name' => 'Funcionaria vinculada',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Nombre anterior',
            'staff_id' => $staff->id,
            'user_type' => 'staff',
            'active' => true,
        ]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Nombre actualizado',
            'user_type' => 'staff',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre actualizado')
            ->assertJsonPath('data.user_type', 'staff')
            ->assertJsonPath('data.staff_id', $staff->id);

        $this->assertSame($staff->id, $user->fresh()->staff_id);
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.Str::random(8),
            'slug' => 'rol_'.Str::random(12),
            'active' => true,
        ]);

        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));

        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
