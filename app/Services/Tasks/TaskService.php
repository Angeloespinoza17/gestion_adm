<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly TaskAccessService $accessService,
    ) {
    }

    public function create(array $payload, User $actor): Task
    {
        return DB::transaction(function () use ($payload, $actor) {
            $owner = User::query()->findOrFail((int) $payload['owner_user_id']);
            if (!$this->accessService->canCreateForOwner($actor, $owner)) {
                throw new AuthorizationException('No tienes autorización para crear tareas para este funcionario.');
            }

            $this->assertValidParent($payload['parent_task_id'] ?? null, null, (int) $owner->id, $actor);

            $status = $payload['status'] ?? Task::STATUS_PENDING;
            $task = Task::query()->create([
                ...$this->taskAttributes($payload),
                'owner_user_id' => $owner->id,
                'created_by_user_id' => $actor->id,
                'completed_at' => $status === Task::STATUS_COMPLETED ? now() : null,
            ]);

            $this->log($task, $actor, 'created', null, $task->only($this->loggedFields()));
            $this->refreshParentAfterSubtaskChange($task, $actor);

            return $this->loadTask($task);
        });
    }

    public function update(Task $task, array $payload, User $actor): Task
    {
        return DB::transaction(function () use ($task, $payload, $actor) {
            if (!$this->accessService->canUpdate($actor, $task)) {
                throw new AuthorizationException('No tienes autorización para modificar esta tarea.');
            }

            $owner = User::query()->findOrFail((int) $payload['owner_user_id']);
            if ((int) $owner->id !== (int) $task->owner_user_id && !$this->accessService->canManageBacklogs($actor)) {
                throw new AuthorizationException('Solo jefatura/admin puede cambiar el funcionario responsable.');
            }

            if (!$this->accessService->isStaffUser($owner)) {
                throw ValidationException::withMessages([
                    'owner_user_id' => ['El funcionario responsable debe ser un usuario activo de personal.'],
                ]);
            }

            $this->assertValidParent($payload['parent_task_id'] ?? null, $task, (int) $owner->id, $actor);

            $old = $task->only($this->loggedFields());
            $oldStatus = $task->status;
            $newStatus = $payload['status'] ?? $task->status;
            $attributes = $this->taskAttributes($payload);
            $attributes['owner_user_id'] = $owner->id;

            if ($newStatus === Task::STATUS_COMPLETED && $task->completed_at === null) {
                $attributes['completed_at'] = now();
            }

            if ($oldStatus === Task::STATUS_COMPLETED && $newStatus !== Task::STATUS_COMPLETED) {
                // Decisión del módulo: al mover una tarea fuera de "Completada" limpiamos completed_at para que los promedios de cierre no cuenten ciclos reabiertos.
                $attributes['completed_at'] = null;
            }

            $task->update($attributes);
            $task->refresh();

            $new = $task->only($this->loggedFields());
            if ($old !== $new) {
                $this->log($task, $actor, 'updated', $old, $new);
            }

            if (
                $task->parent_task_id
                && $oldStatus === Task::STATUS_COMPLETED
                && $newStatus !== Task::STATUS_COMPLETED
                && $task->parent?->status === Task::STATUS_COMPLETED
            ) {
                $this->log($task->parent, $actor, 'subtask_reopened_parent_kept_completed', [
                    'subtask_id' => $task->id,
                    'previous_status' => $oldStatus,
                ], [
                    'subtask_id' => $task->id,
                    'new_status' => $newStatus,
                ]);
            }

            $this->refreshParentAfterSubtaskChange($task, $actor);

            return $this->loadTask($task);
        });
    }

    public function updateStatus(Task $task, string $status, User $actor, ?int $sortOrder = null): Task
    {
        $payload = [
            ...$task->only([
                'title',
                'description',
                'priority',
                'stakeholder',
                'owner_user_id',
                'parent_task_id',
                'auto_complete_parent_on_subtasks_done',
            ]),
            'status' => $status,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'sort_order' => $sortOrder ?? $task->sort_order,
        ];

        return $this->update($task, $payload, $actor);
    }

    public function delete(Task $task, User $actor, bool $deleteSubtasks = false): void
    {
        DB::transaction(function () use ($task, $actor, $deleteSubtasks) {
            if (!$this->accessService->canDelete($actor, $task)) {
                throw new AuthorizationException('No tienes autorización para eliminar esta tarea.');
            }

            $subtaskCount = $task->subtasks()->count();
            if ($subtaskCount > 0 && !$deleteSubtasks) {
                throw ValidationException::withMessages([
                    'delete_subtasks' => ['Esta tarea tiene subtareas. Confirma si deseas eliminarlas en cascada.'],
                ]);
            }

            $this->deleteTaskTree($task, $actor);
        });
    }

    public function loadTask(Task $task): Task
    {
        return $task->load([
            'owner:id,name,email,user_type,staff_id',
            'owner.staff:id,full_name,cargo_id',
            'owner.staff.cargo:id,name',
            'creator:id,name,email,user_type,staff_id',
            'parent:id,title,status,owner_user_id',
            'subtasks.owner:id,name,email,user_type,staff_id',
            'subtasks.creator:id,name,email,user_type,staff_id',
            'activityLogs.user:id,name,email',
        ]);
    }

    private function taskAttributes(array $payload): array
    {
        return [
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'priority' => $payload['priority'],
            'status' => $payload['status'],
            'stakeholder' => $payload['stakeholder'] ?? null,
            'due_date' => $payload['due_date'] ?? null,
            'parent_task_id' => $payload['parent_task_id'] ?? null,
            'auto_complete_parent_on_subtasks_done' => (bool) ($payload['auto_complete_parent_on_subtasks_done'] ?? false),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    private function assertValidParent(?int $parentTaskId, ?Task $task, int $ownerUserId, User $actor): void
    {
        if (!$parentTaskId) {
            return;
        }

        if ($task && (int) $parentTaskId === (int) $task->id) {
            throw ValidationException::withMessages([
                'parent_task_id' => ['Una tarea no puede ser subtarea de sí misma.'],
            ]);
        }

        $parent = Task::query()->findOrFail($parentTaskId);

        if (!$this->accessService->canUpdate($actor, $parent) && !$this->accessService->canView($actor, $parent)) {
            throw new AuthorizationException('No tienes autorización sobre la tarea madre seleccionada.');
        }

        if ((int) $parent->owner_user_id !== $ownerUserId) {
            throw ValidationException::withMessages([
                'parent_task_id' => ['La tarea madre debe pertenecer al mismo funcionario responsable.'],
            ]);
        }

        $ancestor = $parent;
        while ($ancestor) {
            if ($task && (int) $ancestor->id === (int) $task->id) {
                throw ValidationException::withMessages([
                    'parent_task_id' => ['No se permiten relaciones circulares entre tareas.'],
                ]);
            }
            $ancestor = $ancestor->parent;
        }
    }

    private function refreshParentAfterSubtaskChange(Task $task, User $actor): void
    {
        if (!$task->parent_task_id) {
            return;
        }

        $parent = Task::query()->with('subtasks')->find($task->parent_task_id);
        if (!$parent || !$parent->auto_complete_parent_on_subtasks_done) {
            return;
        }

        $total = $parent->subtasks->count();
        $completed = $parent->subtasks->where('status', Task::STATUS_COMPLETED)->count();

        if ($total > 0 && $completed === $total && $parent->status !== Task::STATUS_COMPLETED) {
            $old = $parent->only($this->loggedFields());
            $parent->update([
                'status' => Task::STATUS_COMPLETED,
                'completed_at' => $parent->completed_at ?? now(),
            ]);
            $parent->refresh();
            $this->log($parent, $actor, 'auto_completed_by_subtasks', $old, $parent->only($this->loggedFields()));
        }
    }

    private function deleteTaskTree(Task $task, User $actor): void
    {
        $task->load('subtasks');

        foreach ($task->subtasks as $subtask) {
            $this->deleteTaskTree($subtask, $actor);
        }

        $this->log($task, $actor, 'deleted', $task->only($this->loggedFields()), null);
        $task->delete();
    }

    private function log(Task $task, User $actor, string $action, ?array $oldValue, ?array $newValue): void
    {
        TaskActivityLog::query()->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => $action,
            'old_value' => $oldValue ? array_filter($oldValue, fn ($value) => $value !== null) : null,
            'new_value' => $newValue ? array_filter($newValue, fn ($value) => $value !== null) : null,
        ]);
    }

    private function loggedFields(): array
    {
        return [
            'title',
            'description',
            'priority',
            'status',
            'stakeholder',
            'due_date',
            'owner_user_id',
            'created_by_user_id',
            'parent_task_id',
            'auto_complete_parent_on_subtasks_done',
            'completed_at',
            'sort_order',
        ];
    }
}
