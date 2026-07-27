<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Permission;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_combines_disseminated_calendar_tasks_and_documents_for_the_current_user(): void
    {
        Carbon::setTestNow('2026-07-26 09:00:00');

        $user = $this->userWithPermissions([
            'ver_calendario_fechas_relevantes',
            'ver_tareas',
            'ver_documentos_prevencion_difundibles',
        ]);
        $otherUser = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);

        CalendarEvent::query()->create([
            'title' => 'Jornada institucional difundida',
            'start_date' => '2026-07-28',
            'end_date' => '2026-07-28',
            'priority' => 'alta',
            'status' => 'pendiente',
            'event_kind' => CalendarEvent::KIND_SINGLE,
            'is_disseminable' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        CalendarEvent::query()->create([
            'title' => 'Reunión interna no difundida',
            'start_date' => '2026-07-29',
            'end_date' => '2026-07-29',
            'priority' => 'media',
            'status' => 'pendiente',
            'event_kind' => CalendarEvent::KIND_SINGLE,
            'is_disseminable' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Task::query()->create([
            'title' => 'Preparar informe para hoy',
            'priority' => Task::PRIORITY_URGENT,
            'status' => Task::STATUS_PENDING,
            'due_date' => '2026-07-26',
            'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        Task::query()->create([
            'title' => 'Tarea perteneciente a otra persona',
            'priority' => Task::PRIORITY_HIGH,
            'status' => Task::STATUS_PENDING,
            'due_date' => '2026-07-26',
            'owner_user_id' => $otherUser->id,
            'created_by_user_id' => $otherUser->id,
        ]);

        RiskPreventionDocument::query()->create([
            'document_type' => 'protocolo',
            'title' => 'Protocolo preventivo vigente',
            'version_number' => '2.0',
            'valid_from' => '2026-07-01',
            'valid_until' => '2027-07-01',
            'status' => RiskPreventionDocument::STATUS_VIGENTE,
            'is_disseminable' => true,
            'document_path' => 'risk-prevention/testing/protocolo.pdf',
            'document_name' => 'protocolo.pdf',
            'disseminated_at' => now(),
        ]);

        RiskPreventionDocument::query()->create([
            'document_type' => 'informe',
            'title' => 'Documento preventivo interno',
            'version_number' => '1.0',
            'status' => RiskPreventionDocument::STATUS_VIGENTE,
            'is_disseminable' => false,
            'document_path' => 'risk-prevention/testing/interno.pdf',
            'document_name' => 'interno.pdf',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/inicio/overview');

        $response
            ->assertOk()
            ->assertJsonPath('tasks.pending_count', 1)
            ->assertJsonPath('tasks.today_count', 1)
            ->assertJsonPath('documents.total', 1)
            ->assertJsonPath('documents.items.0.title', 'Protocolo preventivo vigente')
            ->assertJsonFragment(['title' => 'Jornada institucional difundida'])
            ->assertJsonFragment(['title' => 'Preparar informe para hoy'])
            ->assertJsonFragment(['type' => 'task'])
            ->assertJsonMissing(['title' => 'Reunión interna no difundida'])
            ->assertJsonMissing(['title' => 'Tarea perteneciente a otra persona'])
            ->assertJsonMissing(['title' => 'Documento preventivo interno']);

        $sources = collect($response->json('calendar.sources'))->pluck('key')->all();

        $this->assertContains('relevant_calendar', $sources);
        $this->assertContains('task', $sources);

        Carbon::setTestNow();
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol Inicio '.Str::random(8),
            'slug' => 'rol_inicio_'.Str::random(12),
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
