<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBulkDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_user_can_delete_selected_regular_users(): void
    {
        $actor = $this->authenticateUserAdministrator();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $untouchedUser = User::factory()->create();

        $this->deleteJson('/api/admin/users/bulk', [
            'users' => [$firstUser->id, $secondUser->id],
        ])
            ->assertOk()
            ->assertJsonPath('deleted_count', 2);

        $this->assertDatabaseMissing('users', ['id' => $firstUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $secondUser->id]);
        $this->assertDatabaseHas('users', ['id' => $untouchedUser->id]);
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_bulk_deletion_is_rejected_atomically_when_selection_contains_a_protected_user(): void
    {
        $actor = $this->authenticateUserAdministrator();
        $regularUser = User::factory()->create();
        $superAdmin = User::factory()->create(['active' => true]);
        $superAdminRole = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $superAdmin->roles()->attach($superAdminRole);

        $this->deleteJson('/api/admin/users/bulk', [
            'users' => [$regularUser->id, $actor->id, $superAdmin->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('users');

        $this->assertDatabaseHas('users', ['id' => $regularUser->id]);
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_user_listing_marks_current_and_super_admin_accounts_as_protected(): void
    {
        $actor = $this->authenticateUserAdministrator();
        $regularUser = User::factory()->create(['name' => 'Usuario eliminable']);
        $superAdmin = User::factory()->create(['name' => 'Usuario protegido']);
        $superAdminRole = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $superAdmin->roles()->attach($superAdminRole);

        $response = $this->getJson('/api/admin/users?per_page=100')->assertOk();
        $users = collect($response->json('data'))->keyBy('id');

        $this->assertFalse($users->get($actor->id)['can_delete']);
        $this->assertFalse($users->get($superAdmin->id)['can_delete']);
        $this->assertTrue($users->get($regularUser->id)['can_delete']);
    }

    private function authenticateUserAdministrator(): User
    {
        $permission = Permission::query()->create([
            'name' => 'Administrar usuarios',
            'slug' => 'administrar_usuarios',
            'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Administrador ' . Str::random(8),
            'slug' => 'administrador_' . Str::lower(Str::random(12)),
            'active' => true,
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
