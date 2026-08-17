<?php

namespace Tests\Feature\Tasks;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\Task;
use App\Models\TaskAssigner;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TaskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_active_staff_user_gets_only_the_base_task_workspace_access(): void
    {
        $tasksModule = SystemModule::query()->create([
            'slug' => 'tasks',
            'name' => 'Tareas',
            'frontend_route' => null,
            'icon' => 'bx-list-check',
            'sort_order' => 43,
            'active' => true,
        ]);
        foreach ([
            ['slug' => 'tasks_backlog', 'name' => 'Mi backlog', 'route' => '/tasks/backlog', 'sort' => 1],
            ['slug' => 'tasks_assigners', 'name' => 'Asignadores', 'route' => '/tasks/assigners', 'sort' => 2],
            ['slug' => 'tasks_reports', 'name' => 'Reportes', 'route' => '/tasks/reports', 'sort' => 3],
        ] as $module) {
            SystemModule::query()->create([
                'slug' => $module['slug'],
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $tasksModule->id,
            ]);
        }

        $staffUser = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        Sanctum::actingAs($staffUser);

        $permissions = $this->getJson('/api/me/permissions')->assertOk()->json('data');
        $this->assertContains('ver_tareas', $permissions);
        $this->assertContains('gestionar_tareas', $permissions);
        $this->assertNotContains('administrar_asignadores_tareas', $permissions);
        $this->assertNotContains('ver_reportes_tareas', $permissions);

        $moduleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');
        $this->assertSame(['tasks', 'tasks_backlog'], $moduleSlugs->all());

        $this->getJson('/api/tasks')->assertOk();
        $this->postJson('/api/tasks', $this->taskPayload($staffUser))->assertCreated();
        $this->getJson('/api/tasks/assigners')->assertForbidden();
        $this->getJson('/api/tasks/reports')->assertForbidden();
    }

    public function test_staff_user_can_create_own_task(): void
    {
        $user = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', $this->taskPayload($user, [
            'title' => 'Preparar informe mensual',
            'priority' => Task::PRIORITY_HIGH,
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Preparar informe mensual')
            ->assertJsonPath('data.owner_user_id', $user->id);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Preparar informe mensual',
            'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_authorized_assigner_can_create_task_for_target_user(): void
    {
        $target = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $assigner = $this->userWithPermissions(['ver_tareas']);

        TaskAssigner::query()->create([
            'target_user_id' => $target->id,
            'assigner_user_id' => $assigner->id,
            'created_by_user_id' => $target->id,
            'active' => true,
        ]);

        Sanctum::actingAs($assigner);

        $response = $this->postJson('/api/tasks', $this->taskPayload($target, [
            'title' => 'Actualizar presentación de consejo',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.owner_user_id', $target->id)
            ->assertJsonPath('data.created_by_user_id', $assigner->id);
    }

    public function test_unauthorized_user_cannot_create_task_for_another_user(): void
    {
        $target = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $actor = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/tasks', $this->taskPayload($target))
            ->assertForbidden();
    }

    public function test_team_permission_never_exposes_another_users_private_backlog(): void
    {
        $owner = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $manager = $this->userWithPermissions(['ver_tareas', 'ver_tareas_equipo', 'administrar_asignadores_tareas']);
        $task = Task::query()->create($this->modelTaskPayload($owner, ['title' => 'Tarea privada del funcionario']));

        Sanctum::actingAs($manager);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonMissing(['id' => $task->id]);

        $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_only_explicit_stakeholders_can_see_a_shared_task(): void
    {
        $owner = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $stakeholder = $this->userWithPermissions(['ver_tareas']);
        $outsider = $this->userWithPermissions(['ver_tareas', 'ver_tareas_equipo']);

        Sanctum::actingAs($owner);
        $taskId = $this->postJson('/api/tasks', $this->taskPayload($owner, [
            'title' => 'Preparar cuenta pública',
            'stakeholder_user_ids' => [$stakeholder->id],
        ]))->assertCreated()->json('data.id');

        $this->assertDatabaseHas('task_stakeholders', [
            'task_id' => $taskId,
            'user_id' => $stakeholder->id,
        ]);

        Sanctum::actingAs($stakeholder);
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonFragment(['id' => $taskId, 'title' => 'Preparar cuenta pública']);
        $this->getJson("/api/tasks/{$taskId}")->assertOk();
        $this->putJson("/api/tasks/{$taskId}", $this->taskPayload($owner))->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->getJson('/api/tasks')->assertOk()->assertJsonMissing(['id' => $taskId]);
    }

    public function test_assigner_does_not_see_assigned_task_unless_added_as_stakeholder(): void
    {
        $target = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $assigner = $this->userWithPermissions(['ver_tareas']);

        TaskAssigner::query()->create([
            'target_user_id' => $target->id,
            'assigner_user_id' => $assigner->id,
            'created_by_user_id' => $target->id,
            'active' => true,
        ]);

        Sanctum::actingAs($assigner);
        $taskId = $this->postJson('/api/tasks', $this->taskPayload($target, [
            'title' => 'Tarea asignada sin compartir',
            'stakeholder_user_ids' => [],
        ]))->assertCreated()->json('data.id');

        $this->getJson('/api/tasks')->assertOk()->assertJsonMissing(['id' => $taskId]);

        Sanctum::actingAs($target);
        $this->getJson('/api/tasks')->assertOk()->assertJsonFragment(['id' => $taskId]);
    }

    public function test_global_reports_require_special_permission_and_do_not_expand_backlog_visibility(): void
    {
        $owner = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $reporter = $this->userWithPermissions(['ver_tareas', 'ver_reportes_tareas']);
        $withoutReports = $this->userWithPermissions(['ver_tareas']);
        $task = Task::query()->create($this->modelTaskPayload($owner, ['title' => 'Tarea visible solo en reporte']));

        Sanctum::actingAs($reporter);
        $this->getJson('/api/tasks')->assertOk()->assertJsonMissing(['id' => $task->id]);
        $this->getJson('/api/tasks/reports')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id, 'title' => 'Tarea visible solo en reporte']);
        $this->getJson('/api/tasks/all')->assertForbidden();

        Sanctum::actingAs($withoutReports);
        $this->getJson('/api/tasks/reports')->assertForbidden();
    }

    public function test_only_superadmin_can_use_the_all_tasks_operational_view(): void
    {
        $owner = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $task = Task::query()->create($this->modelTaskPayload($owner, ['title' => 'Tarea institucional global']));
        $superAdminRole = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $superAdmin = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $superAdmin->roles()->attach($superAdminRole);

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonMissing(['id' => $task->id]);
        $this->getJson('/api/tasks/all')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id, 'title' => 'Tarea institucional global']);
        $this->getJson('/api/tasks/all/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
        $this->getJson('/api/tasks/all/catalogs')->assertOk();
    }

    public function test_all_tasks_view_supports_pagination_priority_sorting_and_visibility_filters(): void
    {
        $owner = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        $stakeholder = $this->userWithPermissions(['ver_tareas']);

        $urgent = Task::query()->create($this->modelTaskPayload($owner, [
            'title' => 'Urgente privada',
            'priority' => Task::PRIORITY_URGENT,
            'stakeholder' => null,
        ]));
        $high = Task::query()->create($this->modelTaskPayload($owner, [
            'title' => 'Alta privada',
            'priority' => Task::PRIORITY_HIGH,
            'stakeholder' => null,
        ]));
        $low = Task::query()->create($this->modelTaskPayload($owner, [
            'title' => 'Baja compartida',
            'priority' => Task::PRIORITY_LOW,
            'stakeholder' => null,
        ]));
        $low->stakeholders()->attach($stakeholder->id);
        Task::query()->create($this->modelTaskPayload($owner, [
            'title' => 'Subtarea excluida por defecto',
            'parent_task_id' => $urgent->id,
            'stakeholder' => null,
        ]));

        $superAdminRole = Role::query()->create([
            'name' => 'Super Admin filtros',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $superAdmin = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $superAdmin->roles()->attach($superAdminRole);
        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/tasks/all?per_page=2')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/tasks/all?priority=' . Task::PRIORITY_URGENT)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $urgent->id);

        $this->getJson('/api/tasks/all?visibility=private')
            ->assertOk()
            ->assertJsonPath('total', 2);
        $this->getJson('/api/tasks/all?visibility=shared')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $low->id);

        $this->getJson('/api/tasks/all?sort_by=priority&sort_direction=asc&per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $urgent->id)
            ->assertJsonPath('data.1.id', $high->id)
            ->assertJsonPath('data.2.id', $low->id);

        $this->getJson('/api/tasks/all/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
        $this->getJson('/api/tasks/all/stats?include_subtasks=1')
            ->assertOk()
            ->assertJsonPath('data.total', 4);
    }

    public function test_task_and_subtask_crud_with_explicit_cascade_delete(): void
    {
        $user = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        Sanctum::actingAs($user);

        $taskId = $this->postJson('/api/tasks', $this->taskPayload($user, [
            'title' => 'Organizar jornada',
        ]))
            ->assertCreated()
            ->json('data.id');

        $subtaskId = $this->postJson("/api/tasks/{$taskId}/subtasks", $this->taskPayload($user, [
            'title' => 'Confirmar sala',
            'parent_task_id' => $taskId,
        ]))
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/tasks/{$subtaskId}", $this->taskPayload($user, [
            'title' => 'Confirmar sala principal',
            'parent_task_id' => $taskId,
            'status' => Task::STATUS_IN_PROGRESS,
        ]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Confirmar sala principal');

        $this->deleteJson("/api/tasks/{$taskId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delete_subtasks']);

        $this->deleteJson("/api/tasks/{$taskId}", ['delete_subtasks' => true])
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $taskId]);
        $this->assertSoftDeleted('tasks', ['id' => $subtaskId]);
    }

    public function test_parent_task_auto_completes_when_all_subtasks_are_completed_and_option_enabled(): void
    {
        $user = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        Sanctum::actingAs($user);

        $parent = Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Cerrar plan lector',
            'auto_complete_parent_on_subtasks_done' => true,
        ]));
        $subtask = Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Validar evidencias',
            'parent_task_id' => $parent->id,
        ]));

        $this->putJson("/api/tasks/{$subtask->id}/status", [
            'status' => Task::STATUS_COMPLETED,
        ])->assertOk();

        $parent->refresh();
        $this->assertSame(Task::STATUS_COMPLETED, $parent->status);
        $this->assertNotNull($parent->completed_at);
    }

    public function test_parent_task_does_not_auto_complete_when_option_is_disabled(): void
    {
        $user = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        Sanctum::actingAs($user);

        $parent = Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Preparar inducción',
            'auto_complete_parent_on_subtasks_done' => false,
        ]));
        $subtask = Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Enviar invitación',
            'parent_task_id' => $parent->id,
        ]));

        $this->putJson("/api/tasks/{$subtask->id}/status", [
            'status' => Task::STATUS_COMPLETED,
        ])->assertOk();

        $parent->refresh();
        $this->assertSame(Task::STATUS_PENDING, $parent->status);
        $this->assertNull($parent->completed_at);
    }

    public function test_statistics_and_main_filters_are_calculated_for_visible_tasks(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');

        $user = $this->userWithPermissions(['ver_tareas', 'gestionar_tareas']);
        Sanctum::actingAs($user);

        Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Tarea vencida',
            'status' => Task::STATUS_PENDING,
            'due_date' => '2026-06-25',
        ]));
        Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Tarea en progreso',
            'status' => Task::STATUS_IN_PROGRESS,
            'due_date' => '2026-07-03',
            'priority' => Task::PRIORITY_HIGH,
        ]));
        Task::query()->create($this->modelTaskPayload($user, [
            'title' => 'Tarea completada',
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]));

        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.due_next_7_days', 1);

        $this->getJson('/api/tasks?status=' . Task::STATUS_IN_PROGRESS)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tarea en progreso');

        Carbon::setTestNow();
    }

    public function test_task_seeder_creates_idempotent_base_tasks_for_super_admin(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(TaskSeeder::class);
        $this->seed(TaskSeeder::class);

        $superAdmin = User::query()
            ->where('email', env('SUPER_ADMIN_EMAIL', 'superadmin@cnscgestion.cl'))
            ->firstOrFail();

        $baseTitles = [
            'Revisar permisos y accesos críticos',
            'Configurar asignadores de tareas iniciales',
            'Auditar usuarios funcionarios activos',
            'Planificar revisión semanal del backlog institucional',
            'Documentar criterios de priorización Eisenhower',
        ];

        $this->assertSame('staff', $superAdmin->fresh()->user_type);
        $this->assertSame(
            count($baseTitles),
            Task::query()
                ->where('owner_user_id', $superAdmin->id)
                ->whereNull('parent_task_id')
                ->whereIn('title', $baseTitles)
                ->count(),
        );
        $this->assertSame(
            5,
            Task::query()
                ->where('owner_user_id', $superAdmin->id)
                ->whereNotNull('parent_task_id')
                ->count(),
        );
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol ' . Str::random(8),
            'slug' => 'rol_' . Str::random(12),
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

    private function taskPayload(User $owner, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Tarea de prueba',
            'description' => 'Descripción larga para la tarea.',
            'priority' => Task::PRIORITY_MEDIUM,
            'status' => Task::STATUS_PENDING,
            'stakeholder' => 'Dirección',
            'due_date' => '2026-07-15',
            'owner_user_id' => $owner->id,
            'parent_task_id' => null,
            'auto_complete_parent_on_subtasks_done' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    private function modelTaskPayload(User $owner, array $overrides = []): array
    {
        return array_merge($this->taskPayload($owner), [
            'created_by_user_id' => $owner->id,
            'completed_at' => null,
        ], $overrides);
    }
}
