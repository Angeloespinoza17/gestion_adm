<?php

namespace App\Services\RelevantCalendar;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalendarEventService
{
    public function __construct(
        private readonly CalendarEventAccessService $accessService,
        private readonly CalendarEventAuditService $auditService,
        private readonly CalendarRecurrenceService $recurrenceService,
    ) {
    }

    public function createEvent(array $payload, User $actor): CalendarEvent
    {
        return DB::transaction(function () use ($payload, $actor) {
            return match ($payload['creation_mode'] ?? 'single') {
                'recurring' => $this->createRecurringSeries($payload, $actor),
                'process' => $this->createProcess($payload, $actor),
                default => $this->createSingleEvent($payload, $actor),
            };
        });
    }

    public function updateEvent(CalendarEvent $event, array $payload, User $actor): CalendarEvent
    {
        return DB::transaction(function () use ($event, $payload, $actor) {
            if ($event->event_kind === CalendarEvent::KIND_OCCURRENCE) {
                $scope = $payload['edit_scope'] ?? 'this_occurrence';

                return match ($scope) {
                    'future' => $this->splitSeriesFromOccurrence($event, $payload, $actor),
                    'all' => $this->updateRecurringSeries($this->recurrenceService->seriesMaster($event), $payload, $actor, $event),
                    default => $this->updateSingleLikeEvent($event, $payload, $actor, true),
                };
            }

            if ($event->event_kind === CalendarEvent::KIND_SERIES_MASTER) {
                return $this->updateRecurringSeries($event, $payload, $actor, null);
            }

            if ($event->event_kind === CalendarEvent::KIND_PROCESS) {
                return $this->updateProcess($event, $payload, $actor);
            }

            return $this->updateSingleLikeEvent($event, $payload, $actor, false);
        });
    }

    public function deleteEvent(CalendarEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor) {
            $before = $this->auditService->snapshot($event);

            $event->loadMissing(['attachments', 'childEvents.attachments']);

            foreach ($event->attachments as $attachment) {
                if ($attachment->file_path) {
                    Storage::disk('local')->delete($attachment->file_path);
                }
            }

            foreach ($event->childEvents as $child) {
                foreach ($child->attachments as $attachment) {
                    if ($attachment->file_path) {
                        Storage::disk('local')->delete($attachment->file_path);
                    }
                }

                $child->delete();
            }

            $event->delete();

            $this->auditService->log(
                $event,
                $actor,
                'deleted',
                $before,
                null,
                'Evento eliminado.',
            );
        });
    }

    private function createSingleEvent(array $payload, User $actor): CalendarEvent
    {
        $this->assertDepartmentAccess($actor, $payload['department_id'] ?? null);

        $event = CalendarEvent::query()->create(
            $this->buildEventAttributes(
                $payload,
                $actor,
                CalendarEvent::KIND_SINGLE,
            )
        );

        $this->syncUsers($event, $payload);
        $this->syncReminders($event, $payload['reminders'] ?? []);
        $this->auditService->log(
            $event,
            $actor,
            'created',
            null,
            $this->auditService->snapshot($event),
            'Evento creado.',
        );

        return $event->fresh($this->detailRelations());
    }

    private function createRecurringSeries(array $payload, User $actor, ?array $overrideRule = null, ?string $groupId = null): CalendarEvent
    {
        $this->assertDepartmentAccess($actor, $payload['department_id'] ?? null);

        $recurrenceRule = $overrideRule
            ?: $this->recurrenceService->normalizeRule($payload['recurrence'] ?? [], (string) $payload['start_date']);

        $event = CalendarEvent::query()->create(
            $this->buildEventAttributes(
                $payload,
                $actor,
                CalendarEvent::KIND_SERIES_MASTER,
                null,
                [
                    'is_recurring' => true,
                    'auto_generate_occurrences' => (bool) ($recurrenceRule['auto_generate'] ?? false),
                    'recurrence_rule' => $recurrenceRule,
                    'recurrence_group_id' => $groupId ?: (string) Str::uuid(),
                ],
            )
        );

        $this->syncUsers($event, $payload);
        $this->syncReminders($event, $payload['reminders'] ?? []);
        $this->recurrenceService->ensureOccurrencesForEvent(
            $event,
            Carbon::parse($event->start_date->format('Y-m-d'), config('app.timezone')),
            $this->recurrenceService->generationEndForEvent($event, Carbon::parse($event->start_date->format('Y-m-d'), config('app.timezone')))
        );

        $this->auditService->log(
            $event,
            $actor,
            'created_series',
            null,
            $this->auditService->snapshot($event),
            'Serie recurrente creada.',
        );

        return $event->fresh($this->detailRelations());
    }

    private function createProcess(array $payload, User $actor): CalendarEvent
    {
        $this->assertDepartmentAccess($actor, $payload['department_id'] ?? null);

        $range = $this->stageRange($payload['stages'] ?? []);
        $processPayload = array_merge($payload, [
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
        ]);

        $process = CalendarEvent::query()->create(
            $this->buildEventAttributes(
                $processPayload,
                $actor,
                CalendarEvent::KIND_PROCESS,
            )
        );

        $this->syncUsers($process, $payload);
        $this->syncReminders($process, $payload['reminders'] ?? []);
        $this->syncProcessStages($process, $payload, $actor);

        $this->auditService->log(
            $process,
            $actor,
            'created_process',
            null,
            $this->auditService->snapshot($process),
            'Proceso con etapas creado.',
        );

        return $process->fresh($this->detailRelations());
    }

    private function updateSingleLikeEvent(CalendarEvent $event, array $payload, User $actor, bool $isOccurrence): CalendarEvent
    {
        $before = $this->auditService->snapshot($event);
        $this->assertDepartmentAccessForUpdate($actor, $event, $payload['department_id'] ?? $event->department_id);

        $event->update(
            $this->buildEventAttributes(
                $payload,
                $actor,
                $event->event_kind,
                $event,
                [
                    'is_recurring' => $event->is_recurring,
                    'auto_generate_occurrences' => $event->auto_generate_occurrences,
                    'recurrence_rule' => $event->recurrence_rule,
                    'recurrence_group_id' => $event->recurrence_group_id,
                    'parent_event_id' => $event->parent_event_id,
                    'is_exception' => $isOccurrence ? true : $event->is_exception,
                ],
            )
        );

        $this->syncUsers($event, $payload);
        $this->syncReminders($event, $payload['reminders'] ?? []);
        $this->auditService->log(
            $event,
            $actor,
            $isOccurrence ? 'updated_occurrence' : 'updated',
            $before,
            $this->auditService->snapshot($event),
            $isOccurrence ? 'Ocurrencia actualizada.' : 'Evento actualizado.',
        );

        return $event->fresh($this->detailRelations());
    }

    private function updateRecurringSeries(
        CalendarEvent $master,
        array $payload,
        User $actor,
        ?CalendarEvent $selectedOccurrence = null,
    ): CalendarEvent {
        $before = $this->auditService->snapshot($master);
        $this->assertDepartmentAccessForUpdate($actor, $master, $payload['department_id'] ?? $master->department_id);

        $startDate = (string) ($payload['start_date'] ?? $selectedOccurrence?->start_date?->format('Y-m-d') ?? $master->start_date?->format('Y-m-d'));
        $incomingRule = $payload['recurrence'] ?? [];
        $normalizedRule = $incomingRule !== []
            ? $this->recurrenceService->normalizeRule($incomingRule, $startDate)
            : array_merge($master->recurrence_rule ?: [], ['starts_on' => $startDate]);

        $master->update(
            $this->buildEventAttributes(
                array_merge($payload, ['start_date' => $startDate]),
                $actor,
                CalendarEvent::KIND_SERIES_MASTER,
                $master,
                [
                    'is_recurring' => true,
                    'auto_generate_occurrences' => (bool) ($normalizedRule['auto_generate'] ?? false),
                    'recurrence_rule' => $normalizedRule,
                    'recurrence_group_id' => $master->recurrence_group_id ?: (string) Str::uuid(),
                ],
            )
        );

        $this->syncUsers($master, $payload);
        $this->syncReminders($master, $payload['reminders'] ?? []);
        $this->propagateMasterMetadataToOccurrences($master, $actor);

        $refreshFrom = Carbon::parse(
            $selectedOccurrence?->start_date?->format('Y-m-d')
                ?? $payload['start_date']
                ?? now(config('app.timezone'))->toDateString(),
            config('app.timezone')
        )->startOfDay();

        $this->recurrenceService->refreshFutureOccurrences($master, $refreshFrom);

        $this->auditService->log(
            $master,
            $actor,
            'updated_series',
            $before,
            $this->auditService->snapshot($master),
            'Serie recurrente actualizada.',
        );

        return $master->fresh($this->detailRelations());
    }

    private function splitSeriesFromOccurrence(CalendarEvent $occurrence, array $payload, User $actor): CalendarEvent
    {
        $master = $this->recurrenceService->seriesMaster($occurrence);
        $this->assertDepartmentAccessForUpdate($actor, $master, $payload['department_id'] ?? $master->department_id);

        $splitStartDate = (string) ($payload['start_date'] ?? $occurrence->start_date?->format('Y-m-d'));
        $splitStart = Carbon::parse($splitStartDate, config('app.timezone'))->startOfDay();

        $oldBefore = $this->auditService->snapshot($master);
        $oldRule = $master->recurrence_rule ?: [];
        $oldRule['ends_on'] = $splitStart->copy()->subDay()->toDateString();

        $master->update([
            'recurrence_rule' => $oldRule,
            'updated_by' => $actor->id,
        ]);

        $this->recurrenceService->refreshFutureOccurrences($master, $splitStart);

        $newPayload = array_merge($this->payloadFromEvent($master), $payload, [
            'creation_mode' => 'recurring',
            'start_date' => $splitStartDate,
            'recurrence' => $payload['recurrence'] ?? array_merge($master->recurrence_rule ?: [], [
                'mode' => ($master->recurrence_rule['mode'] ?? 'custom'),
                'frequency' => ($master->recurrence_rule['frequency'] ?? 'monthly'),
                'interval' => ($master->recurrence_rule['interval'] ?? 1),
                'weekdays' => ($master->recurrence_rule['weekdays'] ?? []),
                'monthly_mode' => ($master->recurrence_rule['monthly_mode'] ?? 'day_of_month'),
                'day_of_month' => ($master->recurrence_rule['day_of_month'] ?? (int) $splitStart->day),
                'ends_on' => null,
                'auto_generate' => (bool) ($master->recurrence_rule['auto_generate'] ?? false),
            ]),
            'participant_user_ids' => $this->eventUserIdsByRole($master, 'participant'),
            'informed_user_ids' => $this->eventUserIdsByRole($master, 'informed'),
            'reminders' => $master->reminders
                ->map(fn ($reminder) => [
                    'reminder_type' => $reminder->reminder_type,
                    'days_before' => $reminder->days_before,
                    'reminder_date' => $reminder->reminder_date?->format('Y-m-d'),
                    'is_active' => $reminder->is_active,
                ])
                ->all(),
        ]);

        $newSeries = $this->createRecurringSeries(
            $newPayload,
            $actor,
            null,
            $master->recurrence_group_id ?: (string) Str::uuid(),
        );

        $this->auditService->log(
            $master,
            $actor,
            'split_series',
            $oldBefore,
            $this->auditService->snapshot($master),
            'Serie original acotada por edición de futuras ocurrencias.',
        );

        $this->auditService->log(
            $newSeries,
            $actor,
            'split_series_created',
            null,
            $this->auditService->snapshot($newSeries),
            'Nueva serie creada desde una ocurrencia y sus futuras.',
        );

        return $newSeries;
    }

    private function updateProcess(CalendarEvent $process, array $payload, User $actor): CalendarEvent
    {
        $before = $this->auditService->snapshot($process);
        $this->assertDepartmentAccessForUpdate($actor, $process, $payload['department_id'] ?? $process->department_id);

        $range = isset($payload['stages']) ? $this->stageRange($payload['stages']) : [
            'start_date' => $payload['start_date'] ?? $process->start_date?->format('Y-m-d'),
            'end_date' => $payload['end_date'] ?? $process->end_date?->format('Y-m-d'),
        ];

        $process->update(
            $this->buildEventAttributes(
                array_merge($payload, $range),
                $actor,
                CalendarEvent::KIND_PROCESS,
                $process,
                [
                    'is_recurring' => false,
                    'auto_generate_occurrences' => false,
                    'recurrence_rule' => null,
                    'recurrence_group_id' => $process->recurrence_group_id,
                ],
            )
        );

        $this->syncUsers($process, $payload);
        $this->syncReminders($process, $payload['reminders'] ?? []);

        if (isset($payload['stages']) && is_array($payload['stages'])) {
            $this->syncProcessStages($process, $payload, $actor);
            $updatedRange = $this->stageRange($payload['stages']);
            $process->update([
                'start_date' => $updatedRange['start_date'],
                'end_date' => $updatedRange['end_date'],
                'updated_by' => $actor->id,
            ]);
        }

        $this->auditService->log(
            $process,
            $actor,
            'updated_process',
            $before,
            $this->auditService->snapshot($process),
            'Proceso actualizado.',
        );

        return $process->fresh($this->detailRelations());
    }

    private function syncProcessStages(CalendarEvent $process, array $payload, User $actor): void
    {
        $existingStages = $process->childEvents()
            ->where('event_kind', CalendarEvent::KIND_STAGE)
            ->get()
            ->keyBy('id');

        $keepIds = [];
        foreach (($payload['stages'] ?? []) as $index => $stagePayload) {
            $stageId = isset($stagePayload['id']) ? (int) $stagePayload['id'] : null;
            $stage = $stageId ? $existingStages->get($stageId) : null;
            $before = $stage ? $this->auditService->snapshot($stage) : null;

            $attributes = $this->buildEventAttributes(
                array_merge($payload, $stagePayload, [
                    'department_id' => $payload['department_id'] ?? $process->department_id,
                    'process_type_id' => $payload['process_type_id'] ?? $process->process_type_id,
                    'institution_id' => $payload['institution_id'] ?? $process->institution_id,
                    'priority' => $stagePayload['priority'] ?? $payload['priority'] ?? $process->priority,
                    'status' => $stagePayload['status'] ?? $payload['status'] ?? $process->status,
                    'description' => $stagePayload['description'] ?? $process->description,
                ]),
                $actor,
                CalendarEvent::KIND_STAGE,
                $stage,
                [
                    'parent_event_id' => $process->id,
                    'stage_key' => $stagePayload['stage_key'] ?? null,
                    'stage_order' => $index + 1,
                    'is_recurring' => false,
                    'auto_generate_occurrences' => false,
                    'recurrence_rule' => null,
                    'recurrence_group_id' => $process->recurrence_group_id ?: (string) Str::uuid(),
                ],
            );

            if ($stage) {
                $stage->update($attributes);
            } else {
                $stage = CalendarEvent::query()->create($attributes);
            }

            $keepIds[] = $stage->id;
            $this->syncUsers($stage, $payload);
            $this->syncReminders($stage, $stagePayload['reminders'] ?? []);
            $this->auditService->log(
                $stage,
                $actor,
                $before ? 'updated_stage' : 'created_stage',
                $before,
                $this->auditService->snapshot($stage),
                $before ? 'Etapa actualizada.' : 'Etapa creada.',
            );
        }

        $existingStages
            ->filter(fn (CalendarEvent $stage) => !in_array($stage->id, $keepIds, true))
            ->each(function (CalendarEvent $stage) use ($actor) {
                $before = $this->auditService->snapshot($stage);
                $stage->delete();
                $this->auditService->log(
                    $stage,
                    $actor,
                    'deleted_stage',
                    $before,
                    null,
                    'Etapa eliminada del proceso.',
                );
            });
    }

    private function syncUsers(CalendarEvent $event, array $payload): void
    {
        $event->eventUsers()->delete();

        $rows = collect(array_unique(array_map('intval', $payload['participant_user_ids'] ?? [])))
            ->map(fn ($userId) => [
                'user_id' => $userId,
                'role_in_event' => 'participant',
            ])
            ->concat(
                collect(array_unique(array_map('intval', $payload['informed_user_ids'] ?? [])))
                    ->map(fn ($userId) => [
                        'user_id' => $userId,
                        'role_in_event' => 'informed',
                    ])
            )
            ->values()
            ->all();

        if ($rows !== []) {
            $event->eventUsers()->createMany($rows);
        }
    }

    private function syncReminders(CalendarEvent $event, array $reminders): void
    {
        $event->reminders()->delete();

        $rows = collect($reminders)
            ->map(function (array $reminder) {
                $type = $reminder['reminder_type'] ?? null;
                if (!$type) {
                    return null;
                }

                return [
                    'reminder_type' => $type,
                    'days_before' => array_key_exists('days_before', $reminder)
                        ? (int) ($reminder['days_before'] ?? 0)
                        : null,
                    'reminder_date' => $reminder['reminder_date'] ?? null,
                    'sent_at' => null,
                    'is_active' => (bool) ($reminder['is_active'] ?? true),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($rows !== []) {
            $event->reminders()->createMany($rows);
        }
    }

    private function propagateMasterMetadataToOccurrences(CalendarEvent $master, User $actor): void
    {
        $master->loadMissing(['childEvents.eventUsers', 'childEvents.reminders', 'eventUsers', 'reminders']);

        foreach ($master->childEvents as $occurrence) {
            $occurrence->update([
                'title' => $master->title,
                'description' => $master->description,
                'process_type_id' => $master->process_type_id,
                'institution_id' => $master->institution_id,
                'department_id' => $master->department_id,
                'responsible_user_id' => $master->responsible_user_id,
                'priority' => $master->priority,
                'requires_submission' => $master->requires_submission,
                'requires_payment' => $master->requires_payment,
                'requires_signature' => $master->requires_signature,
                'requires_review' => $master->requires_review,
                'requires_approval' => $master->requires_approval,
                'external_url' => $master->external_url,
                'internal_observations' => $master->internal_observations,
                'updated_by' => $actor->id,
            ]);

            if (!$occurrence->is_terminal) {
                $occurrence->update([
                    'status' => $master->status,
                    'start_time' => $master->start_time,
                    'end_time' => $master->end_time,
                ]);
                $this->syncUsers($occurrence, [
                    'participant_user_ids' => $this->eventUserIdsByRole($master, 'participant'),
                    'informed_user_ids' => $this->eventUserIdsByRole($master, 'informed'),
                ]);
                $this->syncReminders(
                    $occurrence,
                    $master->reminders
                        ->map(fn ($reminder) => [
                            'reminder_type' => $reminder->reminder_type,
                            'days_before' => $reminder->days_before,
                            'reminder_date' => $reminder->reminder_date?->format('Y-m-d'),
                            'is_active' => $reminder->is_active,
                        ])
                        ->all()
                );
            }
        }
    }

    private function buildEventAttributes(
        array $payload,
        User $actor,
        string $eventKind,
        ?CalendarEvent $existing = null,
        array $overrides = [],
    ): array {
        $status = (string) ($payload['status'] ?? $existing?->status ?? 'pendiente');
        $isTerminal = in_array($status, CalendarEvent::TERMINAL_STATUSES, true);
        $isArchived = $status === 'archivado';

        $completedBy = $existing?->completed_by;
        $completedAt = $existing?->completed_at;
        $archivedAt = $existing?->archived_at;

        if ($isArchived) {
            $archivedAt = $archivedAt ?: now();
        } else {
            $archivedAt = null;
        }

        if ($isTerminal && !$isArchived) {
            $completedBy = $completedBy ?: $actor->id;
            $completedAt = $completedAt ?: now();
        }

        if (!$isTerminal) {
            $completedBy = null;
            $completedAt = null;
        }

        return array_merge([
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'process_type_id' => $payload['process_type_id'] ?? null,
            'institution_id' => $payload['institution_id'] ?? null,
            'department_id' => $payload['department_id'] ?? null,
            'responsible_user_id' => $payload['responsible_user_id'] ?? null,
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'] ?? $payload['start_date'],
            'start_time' => $payload['start_time'] ?? null,
            'end_time' => $payload['end_time'] ?? null,
            'priority' => $payload['priority'] ?? 'media',
            'status' => $status,
            'requires_submission' => (bool) ($payload['requires_submission'] ?? false),
            'requires_payment' => (bool) ($payload['requires_payment'] ?? false),
            'requires_signature' => (bool) ($payload['requires_signature'] ?? false),
            'requires_review' => (bool) ($payload['requires_review'] ?? false),
            'requires_approval' => (bool) ($payload['requires_approval'] ?? false),
            'is_recurring' => false,
            'auto_generate_occurrences' => false,
            'recurrence_rule' => null,
            'recurrence_group_id' => $existing?->recurrence_group_id,
            'parent_event_id' => $existing?->parent_event_id,
            'event_kind' => $eventKind,
            'stage_key' => $existing?->stage_key,
            'stage_order' => $existing?->stage_order,
            'is_exception' => $existing?->is_exception ?? false,
            'external_url' => $payload['external_url'] ?? null,
            'internal_observations' => $payload['internal_observations'] ?? null,
            'created_by' => $existing?->created_by ?? $actor->id,
            'updated_by' => $actor->id,
            'completed_by' => $completedBy,
            'completed_at' => $completedAt,
            'archived_at' => $archivedAt,
        ], $overrides);
    }

    private function stageRange(array $stages): array
    {
        $dates = collect($stages)
            ->flatMap(function (array $stage) {
                return array_filter([
                    $stage['start_date'] ?? null,
                    $stage['end_date'] ?? null,
                ]);
            })
            ->values();

        if ($dates->isEmpty()) {
            $today = now(config('app.timezone'))->toDateString();

            return [
                'start_date' => $today,
                'end_date' => $today,
            ];
        }

        return [
            'start_date' => Carbon::parse($dates->min(), config('app.timezone'))->toDateString(),
            'end_date' => Carbon::parse($dates->max(), config('app.timezone'))->toDateString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'processType:id,name,color',
            'institution:id,name,website_url,color',
            'department:id,name,color,responsible_staff_id',
            'responsibleUser:id,name,email,staff_id',
            'eventUsers.user:id,name,email,staff_id',
            'reminders',
            'attachments.uploadedBy:id,name,email',
            'logs.user:id,name,email',
            'parentEvent:id,title,event_kind,start_date,end_date,status',
            'childEvents.processType:id,name,color',
            'childEvents.institution:id,name,color',
            'childEvents.responsibleUser:id,name,email',
        ];
    }

    private function payloadFromEvent(CalendarEvent $event): array
    {
        return Arr::only($event->toArray(), [
            'title',
            'description',
            'process_type_id',
            'institution_id',
            'department_id',
            'responsible_user_id',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'priority',
            'status',
            'external_url',
            'internal_observations',
            'requires_submission',
            'requires_payment',
            'requires_signature',
            'requires_review',
            'requires_approval',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function eventUserIdsByRole(CalendarEvent $event, string $role): array
    {
        $event->loadMissing('eventUsers');

        return $event->eventUsers
            ->where('role_in_event', $role)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function assertDepartmentAccess(User $actor, ?int $departmentId): void
    {
        if ($this->accessService->canCreateForDepartment($actor, $departmentId)) {
            return;
        }

        throw ValidationException::withMessages([
            'department_id' => 'No tienes permisos para gestionar eventos en el departamento seleccionado.',
        ]);
    }

    private function assertDepartmentAccessForUpdate(User $actor, CalendarEvent $event, ?int $departmentId): void
    {
        if ($this->accessService->canManageAll($actor)) {
            return;
        }

        if ($this->accessService->canManageDepartments($actor) && $departmentId && in_array($departmentId, $this->accessService->managedDepartmentIds($actor), true)) {
            return;
        }

        if ($this->accessService->canUpdate($actor, $event)) {
            return;
        }

        throw ValidationException::withMessages([
            'department_id' => 'No tienes permisos para actualizar este evento.',
        ]);
    }
}
