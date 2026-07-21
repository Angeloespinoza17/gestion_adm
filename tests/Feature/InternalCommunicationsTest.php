<?php

namespace Tests\Feature;

use App\Models\InternalCommunications\InternalAnnouncement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternalCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_targeted_announcement_is_visible_on_home_and_can_be_acknowledged(): void
    {
        $manager = $this->userWithPermissions([
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
        ]);
        $targetRole = $this->role('Docentes prueba');
        $otherRole = $this->role('Porteria prueba');
        $targetUser = $this->userWithRole($targetRole);
        $otherUser = $this->userWithRole($otherRole);

        Sanctum::actingAs($manager);

        $announcementId = $this->postJson('/api/internal-communications', [
            'title' => 'Reunion tecnica por ciclo',
            'body' => 'Recordatorio interno para el equipo destinatario.',
            'category' => 'Academico',
            'priority' => InternalAnnouncement::PRIORITY_IMPORTANT,
            'status' => InternalAnnouncement::STATUS_PUBLISHED,
            'audience_all' => false,
            'requires_ack' => true,
            'role_ids' => [$targetRole->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Reunion tecnica por ciclo')
            ->json('data.id');

        Sanctum::actingAs($targetUser);

        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Reunion tecnica por ciclo'])
            ->assertJsonPath('internal_announcements.unread_count', 1)
            ->assertJsonPath('internal_announcements.pending_ack_count', 1);

        $this->postJson("/api/internal-communications/{$announcementId}/read", [
            'acknowledged' => true,
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['read_at', 'acknowledged_at']]);

        $this->assertDatabaseHas('internal_announcement_reads', [
            'internal_announcement_id' => $announcementId,
            'user_id' => $targetUser->id,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Reunion tecnica por ciclo']);

        $this->postJson("/api/internal-communications/{$announcementId}/read", [
            'acknowledged' => true,
        ])->assertNotFound();
    }

    public function test_requires_an_audience_when_not_published_for_everyone(): void
    {
        Sanctum::actingAs($this->userWithPermissions([
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
        ]));

        $this->postJson('/api/internal-communications', [
            'title' => 'Aviso sin destinatarios',
            'body' => 'Contenido del aviso.',
            'priority' => InternalAnnouncement::PRIORITY_NORMAL,
            'status' => InternalAnnouncement::STATUS_DRAFT,
            'audience_all' => false,
            'role_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = $this->role('Rol permisos');

        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));

        $role->permissions()->sync($permissions->pluck('id')->all());

        return $this->userWithRole($role);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);

        $user->roles()->attach($role);

        return $user;
    }

    private function role(string $name): Role
    {
        return Role::query()->create([
            'name' => $name.' '.Str::random(6),
            'slug' => 'rol_'.Str::random(16),
            'active' => true,
        ]);
    }
}
