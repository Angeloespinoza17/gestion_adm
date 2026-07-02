<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = $this->resolveSuperAdmin();

        if (!$superAdmin) {
            $this->call(RbacSeeder::class);
            $superAdmin = $this->resolveSuperAdmin();
        }

        if (!$superAdmin) {
            $this->command?->warn('No se encontró un usuario super admin para sembrar tareas base.');

            return;
        }

        if (!$superAdmin->user_type) {
            $superAdmin->forceFill(['user_type' => 'staff'])->save();
        }

        foreach ($this->baseTasks() as $index => $definition) {
            $subtasks = $definition['subtasks'] ?? [];
            unset($definition['subtasks']);

            $task = $this->upsertTask($superAdmin, $definition, null, ($index + 1) * 10);

            foreach ($subtasks as $subtaskIndex => $subtask) {
                $this->upsertTask($superAdmin, $subtask, $task, $subtaskIndex + 1);
            }
        }
    }

    private function resolveSuperAdmin(): ?User
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@cnscgestion.cl');

        return User::query()->where('email', $email)->first()
            ?: User::query()
                ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
                ->orderBy('id')
                ->first();
    }

    private function upsertTask(User $superAdmin, array $definition, ?Task $parent, int $sortOrder): Task
    {
        $status = $definition['status'];

        $task = Task::withTrashed()->updateOrCreate(
            [
                'title' => $definition['title'],
                'owner_user_id' => $superAdmin->id,
                'parent_task_id' => $parent?->id,
            ],
            [
                'description' => $definition['description'],
                'priority' => $definition['priority'],
                'status' => $status,
                'stakeholder' => $definition['stakeholder'],
                'due_date' => $definition['due_date'],
                'created_by_user_id' => $superAdmin->id,
                'auto_complete_parent_on_subtasks_done' => $definition['auto_complete_parent_on_subtasks_done'] ?? false,
                'completed_at' => $status === Task::STATUS_COMPLETED ? now() : null,
                'sort_order' => $sortOrder,
            ],
        );

        if ($task->trashed()) {
            $task->restore();
        }

        return $task;
    }

    private function baseTasks(): array
    {
        return [
            [
                'title' => 'Revisar permisos y accesos críticos',
                'description' => 'Validar que los roles administrativos conserven acceso a módulos críticos, permisos de tareas y administración de asignadores.',
                'priority' => Task::PRIORITY_URGENT,
                'status' => Task::STATUS_IN_PROGRESS,
                'stakeholder' => 'Administración',
                'due_date' => now()->addDays(2)->toDateString(),
                'auto_complete_parent_on_subtasks_done' => true,
                'subtasks' => [
                    [
                        'title' => 'Confirmar acceso de super admin al módulo Tareas',
                        'description' => 'Entrar al backlog y verificar que tabla, calendario, kanban y estadísticas carguen correctamente.',
                        'priority' => Task::PRIORITY_HIGH,
                        'status' => Task::STATUS_COMPLETED,
                        'stakeholder' => 'Administración',
                        'due_date' => now()->addDay()->toDateString(),
                    ],
                    [
                        'title' => 'Revisar permisos de asignadores de tareas',
                        'description' => 'Comprobar que jefaturas y administración puedan gestionar autorizaciones de asignadores.',
                        'priority' => Task::PRIORITY_HIGH,
                        'status' => Task::STATUS_IN_PROGRESS,
                        'stakeholder' => 'Administración',
                        'due_date' => now()->addDays(2)->toDateString(),
                    ],
                    [
                        'title' => 'Verificar menú lateral para roles administrativos',
                        'description' => 'Confirmar que los accesos del módulo Tareas aparezcan en la navegación según permisos.',
                        'priority' => Task::PRIORITY_MEDIUM,
                        'status' => Task::STATUS_PENDING,
                        'stakeholder' => 'Administración',
                        'due_date' => now()->addDays(3)->toDateString(),
                    ],
                ],
            ],
            [
                'title' => 'Configurar asignadores de tareas iniciales',
                'description' => 'Definir qué usuarios o jefaturas pueden cargar tareas al backlog de funcionarios específicos.',
                'priority' => Task::PRIORITY_HIGH,
                'status' => Task::STATUS_PENDING,
                'stakeholder' => 'Dirección',
                'due_date' => now()->addDays(5)->toDateString(),
                'auto_complete_parent_on_subtasks_done' => true,
                'subtasks' => [
                    [
                        'title' => 'Identificar jefaturas responsables por área',
                        'description' => 'Levantar la lista de responsables que podrán crear tareas para cada equipo.',
                        'priority' => Task::PRIORITY_MEDIUM,
                        'status' => Task::STATUS_PENDING,
                        'stakeholder' => 'Dirección',
                        'due_date' => now()->addDays(4)->toDateString(),
                    ],
                    [
                        'title' => 'Crear autorizaciones activas en Asignadores de tareas',
                        'description' => 'Registrar las autorizaciones iniciales y dejar inactivas las que requieran validación posterior.',
                        'priority' => Task::PRIORITY_HIGH,
                        'status' => Task::STATUS_PENDING,
                        'stakeholder' => 'Dirección',
                        'due_date' => now()->addDays(5)->toDateString(),
                    ],
                ],
            ],
            [
                'title' => 'Auditar usuarios funcionarios activos',
                'description' => 'Revisar que los usuarios funcionarios estén activos, tengan rol o cargo vigente y puedan operar su backlog personal.',
                'priority' => Task::PRIORITY_MEDIUM,
                'status' => Task::STATUS_BLOCKED,
                'stakeholder' => 'RRHH',
                'due_date' => now()->addDays(8)->toDateString(),
            ],
            [
                'title' => 'Planificar revisión semanal del backlog institucional',
                'description' => 'Definir una rutina semanal para revisar tareas vencidas, próximas a vencer, bloqueadas y asignadas por terceros.',
                'priority' => Task::PRIORITY_MEDIUM,
                'status' => Task::STATUS_IN_REVIEW,
                'stakeholder' => 'Unidad de Gestión',
                'due_date' => now()->addDays(12)->toDateString(),
            ],
            [
                'title' => 'Documentar criterios de priorización Eisenhower',
                'description' => 'Dejar una guía breve para que los funcionarios clasifiquen tareas urgentes, importantes, delegables y postergables.',
                'priority' => Task::PRIORITY_LOW,
                'status' => Task::STATUS_COMPLETED,
                'stakeholder' => 'Equipo directivo',
                'due_date' => now()->subDay()->toDateString(),
            ],
        ];
    }
}
