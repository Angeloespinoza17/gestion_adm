<?php

namespace App\Services\Permissions;

use App\Models\Department;
use App\Models\PermissionRequest;
use App\Models\PermissionRequestWatcher;
use App\Models\PermissionType;
use App\Models\PermissionTypeWatcher;
use App\Models\Staff;
use App\Models\StaffPermissionWatcher;
use App\Models\User;
use App\Notifications\PendingPermissionReviewNotification;
use App\Notifications\PermissionRequestStatusNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PermissionWorkflowService
{
    public function saveDraft(PermissionRequest $permissionRequest, array $payload, User $actor, bool $isNew): PermissionRequest
    {
        if (!$isNew && !$permissionRequest->isEditable() && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'status' => 'La solicitud ya no puede editarse desde este estado.',
            ]);
        }

        return DB::transaction(function () use ($permissionRequest, $payload, $actor, $isNew) {
            $staff = Staff::query()
                ->with(['cargo:id,name', 'departments:id,name,responsible_staff_id', 'departments.responsibleStaff.user:id,name,email,staff_id'])
                ->findOrFail($payload['staff_id']);
            $permissionType = PermissionType::query()->findOrFail($payload['permission_type_id']);

            $departmentIds = array_values(array_unique($payload['department_ids'] ?? $staff->departments->pluck('id')->all()));
            $departments = $departmentIds !== []
                ? Department::query()->whereIn('id', $departmentIds)->get(['id', 'name', 'responsible_staff_id'])
                : $staff->departments;

            $managerUser = $this->resolveManagerUser($payload['direct_manager_user_id'] ?? null, $departments, $staff);
            $duration = $this->calculateDuration($payload);
            $withPay = $this->resolveWithPay($permissionType, $payload, $permissionRequest);
            $affectsSalary = (bool) ($payload['affects_salary'] ?? $permissionType->affects_salary ?? false);

            if ($withPay === false) {
                $affectsSalary = true;
            }

            $permissionRequest->fill([
                'staff_id' => $staff->id,
                'requested_by_user_id' => $permissionRequest->requested_by_user_id ?: $actor->id,
                'created_by' => $isNew ? $actor->id : $permissionRequest->created_by,
                'updated_by' => $actor->id,
                'direct_manager_user_id' => $managerUser?->id,
                'permission_type_id' => $permissionType->id,
                'cargo_name' => $staff->cargo?->name,
                'direct_manager_name' => $managerUser?->staff?->full_name ?: $managerUser?->name,
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'start_time' => $payload['start_time'] ?? null,
                'end_time' => $payload['end_time'] ?? null,
                'duration_hours' => $duration['hours'],
                'duration_days' => $duration['days'],
                'duration_label' => $duration['label'],
                'is_full_day' => $duration['is_full_day'],
                'is_half_day' => $duration['is_half_day'],
                'with_pay' => $withPay,
                'affects_salary' => $affectsSalary,
                'affects_attendance' => (bool) ($payload['affects_attendance'] ?? $permissionType->affects_attendance ?? true),
                'requires_replacement' => (bool) ($payload['requires_replacement'] ?? $permissionType->requires_replacement ?? false),
                'reason' => $payload['reason'],
                'description' => $payload['description'] ?? null,
                'employee_observations' => $payload['employee_observations'] ?? null,
                'visible_observations' => $payload['visible_observations'] ?? $permissionRequest->visible_observations,
                'internal_observations' => $payload['internal_observations'] ?? $permissionRequest->internal_observations,
                'urgency' => (bool) ($payload['urgency'] ?? false),
                'retroactive' => (bool) ($payload['retroactive'] ?? false),
                'attendance_status' => $permissionRequest->attendance_status ?: 'pendiente',
                'payroll_status' => $permissionRequest->payroll_status ?: 'no_aplica',
                'salary_discount_hours' => $payload['salary_discount_hours'] ?? $permissionRequest->salary_discount_hours,
                'salary_discount_days' => $payload['salary_discount_days'] ?? $permissionRequest->salary_discount_days,
                'requires_regularization' => (bool) ($payload['requires_regularization'] ?? $permissionRequest->requires_regularization),
                'status' => $permissionRequest->exists ? $permissionRequest->status : 'borrador',
            ]);

            $oldStatus = $permissionRequest->getOriginal('status');

            $permissionRequest->save();
            $permissionRequest->departments()->sync($departmentIds);

            $this->recordLog(
                $permissionRequest,
                $actor,
                $isNew ? 'creada' : 'actualizada',
                $oldStatus,
                $permissionRequest->status,
                [
                    'reason' => $permissionRequest->reason,
                    'department_ids' => $departmentIds,
                ],
            );

            return $this->loadRequest($permissionRequest);
        });
    }

    public function submit(PermissionRequest $permissionRequest, User $actor, ?string $comment = null): PermissionRequest
    {
        if (!$permissionRequest->isEditable() && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden enviar solicitudes en borrador u observadas.',
            ]);
        }

        if ($permissionRequest->permissionType?->requires_attachment && !$permissionRequest->documents()->exists()) {
            throw ValidationException::withMessages([
                'documents' => 'Este tipo de permiso requiere al menos un documento de respaldo.',
            ]);
        }

        return DB::transaction(function () use ($permissionRequest, $actor, $comment) {
            $oldStatus = $permissionRequest->status;
            $this->syncRequestWatchers($permissionRequest);
            $steps = $this->stepsForType($permissionRequest->permissionType);
            $permissionRequest->submitted_at = now();

            if ($steps === []) {
                $permissionRequest->status = 'aprobado';
                $permissionRequest->current_step = null;
                $permissionRequest->approved_at = now();
                $permissionRequest->attendance_status = $permissionRequest->affects_attendance ? 'ausencia_autorizada' : 'no_aplica';
                $permissionRequest->payroll_status = $this->resolvePayrollStatus($permissionRequest);
            } else {
                $permissionRequest->status = $this->statusForStep($steps[0]);
                $permissionRequest->current_step = $steps[0];
            }

            $permissionRequest->save();

            $this->recordLog($permissionRequest, $actor, 'enviada_revision', $oldStatus, $permissionRequest->status, [
                'comment' => $comment,
            ]);

            if ($permissionRequest->status === 'aprobado') {
                $this->notifyStatus($permissionRequest, 'Solicitud de permiso aprobada', 'Tu solicitud fue aprobada automáticamente.', $comment);
                $this->notifyConfiguredWatchers($permissionRequest, 'Solicitud de permiso aprobada', 'Se registró una solicitud de permiso que quedó aprobada automáticamente.');
            } else {
                $this->notifyStepReviewers($permissionRequest);
                $this->notifyStatus($permissionRequest, 'Solicitud de permiso ingresada', 'Tu solicitud fue ingresada y quedó pendiente de revisión.', $comment);
                $this->notifyConfiguredWatchers(
                    $permissionRequest,
                    'Nueva solicitud de permiso registrada',
                    'Se registró una nueva solicitud de permiso para seguimiento.',
                    $this->usersForStep($permissionRequest)->pluck('id')->all(),
                );
            }

            return $this->loadRequest($permissionRequest);
        });
    }

    public function approve(PermissionRequest $permissionRequest, User $actor, array $payload = []): PermissionRequest
    {
        if (!$permissionRequest->current_step) {
            throw ValidationException::withMessages([
                'status' => 'La solicitud no tiene una etapa pendiente de aprobación.',
            ]);
        }

        if ((int) $permissionRequest->requested_by_user_id === (int) $actor->id && !$actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'approver' => 'No puedes aprobar tu propia solicitud.',
            ]);
        }

        return DB::transaction(function () use ($permissionRequest, $actor, $payload) {
            $oldStatus = $permissionRequest->status;
            $comment = $payload['comments'] ?? null;
            $internalComment = $payload['internal_comments'] ?? null;

            $permissionRequest->approvals()->create([
                'approver_user_id' => $actor->id,
                'role_or_step' => $permissionRequest->current_step,
                'status' => 'aprobado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);

            if (array_key_exists('with_pay', $payload)) {
                $permissionRequest->with_pay = $payload['with_pay'];
            }

            if (array_key_exists('affects_salary', $payload)) {
                $permissionRequest->affects_salary = (bool) $payload['affects_salary'];
            }

            if ($permissionRequest->with_pay === false) {
                $permissionRequest->affects_salary = true;
            }

            if (array_key_exists('affects_attendance', $payload)) {
                $permissionRequest->affects_attendance = (bool) $payload['affects_attendance'];
            }

            if (array_key_exists('salary_discount_hours', $payload)) {
                $permissionRequest->salary_discount_hours = $payload['salary_discount_hours'];
            }

            if (array_key_exists('salary_discount_days', $payload)) {
                $permissionRequest->salary_discount_days = $payload['salary_discount_days'];
            }

            if (!empty($payload['visible_observations'])) {
                $permissionRequest->visible_observations = $payload['visible_observations'];
            }

            if (!empty($payload['internal_observations'])) {
                $permissionRequest->internal_observations = $payload['internal_observations'];
            }

            $steps = $this->stepsForType($permissionRequest->permissionType);
            $currentIndex = array_search($permissionRequest->current_step, $steps, true);
            $nextStep = $currentIndex === false ? null : ($steps[$currentIndex + 1] ?? null);

            if ($nextStep) {
                $permissionRequest->current_step = $nextStep;
                $permissionRequest->status = $this->statusForStep($nextStep);
            } else {
                $permissionRequest->current_step = null;
                $permissionRequest->status = 'aprobado';
                $permissionRequest->approved_at = now();
                $permissionRequest->attendance_status = $permissionRequest->affects_attendance ? 'ausencia_autorizada' : 'no_aplica';
                $permissionRequest->payroll_status = $payload['payroll_status'] ?? $this->resolvePayrollStatus($permissionRequest);
            }

            $permissionRequest->save();

            $this->recordLog($permissionRequest, $actor, 'aprobada_etapa', $oldStatus, $permissionRequest->status, [
                'step' => $oldStatus,
                'comment' => $comment,
                'internal_comment' => $internalComment,
            ]);

            if ($permissionRequest->status === 'aprobado') {
                $this->notifyStatus($permissionRequest, 'Solicitud de permiso aprobada', 'Tu solicitud fue aprobada.', $comment);
                $this->notifyConfiguredWatchers($permissionRequest, 'Solicitud de permiso aprobada', 'La solicitud de permiso fue aprobada.', [$permissionRequest->requested_by_user_id]);

                if ($permissionRequest->with_pay === false) {
                    $this->notifyPayrollReview($permissionRequest, 'Solicitud de permiso sin goce', 'Existe un permiso aprobado sin goce de remuneraciones que requiere revisión administrativa.');
                }
            } else {
                $this->notifyStepReviewers($permissionRequest);
                $this->notifyStatus($permissionRequest, 'Solicitud de permiso avanza de etapa', 'Tu solicitud avanzó a la siguiente etapa de revisión.', $comment);
                $this->notifyConfiguredWatchers($permissionRequest, 'Solicitud de permiso actualizada', 'La solicitud de permiso avanzó a una nueva etapa de revisión.', [$permissionRequest->requested_by_user_id]);
            }

            return $this->loadRequest($permissionRequest);
        });
    }

    public function reject(PermissionRequest $permissionRequest, User $actor, string $comment, ?string $internalComment = null): PermissionRequest
    {
        return DB::transaction(function () use ($permissionRequest, $actor, $comment, $internalComment) {
            $oldStatus = $permissionRequest->status;

            $permissionRequest->approvals()->create([
                'approver_user_id' => $actor->id,
                'role_or_step' => $permissionRequest->current_step ?: 'manual',
                'status' => 'rechazado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);

            $permissionRequest->status = 'rechazado';
            $permissionRequest->current_step = null;
            $permissionRequest->rejected_at = now();
            $permissionRequest->visible_observations = $comment;
            $permissionRequest->internal_observations = $internalComment ?: $permissionRequest->internal_observations;
            $permissionRequest->attendance_status = 'pendiente_regularizacion';
            $permissionRequest->requires_regularization = true;
            $permissionRequest->save();

            $this->recordLog($permissionRequest, $actor, 'rechazada', $oldStatus, $permissionRequest->status, [
                'comment' => $comment,
                'internal_comment' => $internalComment,
            ]);

            $this->notifyStatus($permissionRequest, 'Solicitud de permiso rechazada', 'Tu solicitud fue rechazada.', $comment);
            $this->notifyConfiguredWatchers($permissionRequest, 'Solicitud de permiso rechazada', 'La solicitud de permiso fue rechazada.', [$permissionRequest->requested_by_user_id]);

            return $this->loadRequest($permissionRequest);
        });
    }

    public function observe(PermissionRequest $permissionRequest, User $actor, string $comment, ?string $internalComment = null): PermissionRequest
    {
        return DB::transaction(function () use ($permissionRequest, $actor, $comment, $internalComment) {
            $oldStatus = $permissionRequest->status;

            $permissionRequest->approvals()->create([
                'approver_user_id' => $actor->id,
                'role_or_step' => $permissionRequest->current_step ?: 'manual',
                'status' => 'observado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);

            $permissionRequest->status = 'observado';
            $permissionRequest->current_step = null;
            $permissionRequest->visible_observations = $comment;
            $permissionRequest->internal_observations = $internalComment ?: $permissionRequest->internal_observations;
            $permissionRequest->save();

            $this->recordLog($permissionRequest, $actor, 'observada', $oldStatus, $permissionRequest->status, [
                'comment' => $comment,
                'internal_comment' => $internalComment,
            ]);

            $this->notifyStatus($permissionRequest, 'Solicitud de permiso observada', 'Tu solicitud requiere correcciones o antecedentes adicionales.', $comment);
            $this->notifyConfiguredWatchers($permissionRequest, 'Solicitud de permiso observada', 'La solicitud de permiso requiere correcciones o antecedentes adicionales.', [$permissionRequest->requested_by_user_id]);

            return $this->loadRequest($permissionRequest);
        });
    }

    public function cancel(PermissionRequest $permissionRequest, User $actor, ?string $comment = null): PermissionRequest
    {
        return DB::transaction(function () use ($permissionRequest, $actor, $comment) {
            $oldStatus = $permissionRequest->status;
            $permissionRequest->status = 'cancelado';
            $permissionRequest->current_step = null;
            $permissionRequest->cancelled_at = now();
            $permissionRequest->save();

            $this->recordLog($permissionRequest, $actor, 'cancelada', $oldStatus, $permissionRequest->status, [
                'comment' => $comment,
            ]);

            $this->notifyStatus($permissionRequest, 'Solicitud de permiso cancelada', 'La solicitud fue cancelada.', $comment);
            $this->notifyConfiguredWatchers($permissionRequest, 'Solicitud de permiso cancelada', 'La solicitud de permiso fue cancelada.', [$permissionRequest->requested_by_user_id]);

            return $this->loadRequest($permissionRequest);
        });
    }

    public function execute(PermissionRequest $permissionRequest, User $actor, ?string $comment = null): PermissionRequest
    {
        if ($permissionRequest->status !== 'aprobado') {
            throw ValidationException::withMessages([
                'status' => 'Solo las solicitudes aprobadas pueden marcarse como ejecutadas.',
            ]);
        }

        return DB::transaction(function () use ($permissionRequest, $actor, $comment) {
            $oldStatus = $permissionRequest->status;
            $permissionRequest->status = 'ejecutado';
            $permissionRequest->executed_at = now();
            $permissionRequest->save();

            $this->recordLog($permissionRequest, $actor, 'ejecutada', $oldStatus, $permissionRequest->status, [
                'comment' => $comment,
            ]);

            return $this->loadRequest($permissionRequest);
        });
    }

    public function validateAttachment(
        PermissionRequest $permissionRequest,
        int $documentId,
        User $actor,
        string $validationStatus,
        ?string $comment = null,
    ): PermissionRequest {
        return DB::transaction(function () use ($permissionRequest, $documentId, $actor, $validationStatus, $comment) {
            $document = $permissionRequest->documents()->findOrFail($documentId);
            $document->update([
                'validated_by_user_id' => $actor->id,
                'validated_at' => now(),
                'validation_status' => $validationStatus,
                'comments' => $comment,
            ]);

            $this->recordLog($permissionRequest, $actor, 'documento_validado', $permissionRequest->status, $permissionRequest->status, [
                'document_id' => $document->id,
                'validation_status' => $validationStatus,
                'comment' => $comment,
            ]);

            return $this->loadRequest($permissionRequest);
        });
    }

    private function resolveManagerUser(?int $managerUserId, Collection $departments, Staff $staff): ?User
    {
        if ($managerUserId) {
            return User::query()->find($managerUserId);
        }

        $staff->loadMissing([
            'organigramRelations.relatedStaff.user:id,name,email,staff_id',
        ]);

        $directManager = $staff->organigramRelations
            ->where('active', true)
            ->where('relationship_type', 'direct_manager')
            ->sortBy([
                ['is_primary', 'desc'],
                ['priority', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        if ($directManager?->relatedStaff?->user) {
            return $directManager->relatedStaff->user;
        }

        foreach ($departments as $department) {
            if ($department->responsible_staff_id) {
                $manager = User::query()->where('staff_id', $department->responsible_staff_id)->first();
                if ($manager) {
                    return $manager;
                }
            }
        }

        return null;
    }

    private function resolveWithPay(PermissionType $permissionType, array $payload, PermissionRequest $permissionRequest): ?bool
    {
        if (array_key_exists('with_pay', $payload)) {
            return $payload['with_pay'];
        }

        if ($permissionRequest->exists) {
            return $permissionRequest->with_pay;
        }

        if ($permissionType->allows_with_pay && !$permissionType->allows_without_pay) {
            return true;
        }

        if (!$permissionType->allows_with_pay && $permissionType->allows_without_pay) {
            return false;
        }

        return null;
    }

    private function calculateDuration(array $payload): array
    {
        $startDate = Carbon::parse($payload['start_date']);
        $endDate = Carbon::parse($payload['end_date']);
        $isHalfDay = (bool) ($payload['is_half_day'] ?? false);
        $startTime = $payload['start_time'] ?? null;
        $endTime = $payload['end_time'] ?? null;

        if ($isHalfDay) {
            return [
                'hours' => 4,
                'days' => 0.50,
                'label' => 'Media jornada',
                'is_full_day' => false,
                'is_half_day' => true,
            ];
        }

        if ($startTime && $endTime) {
            $start = Carbon::parse($payload['start_date'] . ' ' . $startTime);
            $end = Carbon::parse($payload['end_date'] . ' ' . $endTime);
            $hours = round($start->diffInMinutes($end) / 60, 2);

            return [
                'hours' => $hours,
                'days' => round($hours / 8, 2),
                'label' => rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') . ' horas',
                'is_full_day' => false,
                'is_half_day' => false,
            ];
        }

        $days = (float) $startDate->diffInDays($endDate) + 1;

        return [
            'hours' => null,
            'days' => $days,
            'label' => $days === 1.0 ? 'Jornada completa' : rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.') . ' días',
            'is_full_day' => true,
            'is_half_day' => false,
        ];
    }

    private function stepsForType(?PermissionType $permissionType): array
    {
        if (!$permissionType) {
            return [];
        }

        $steps = [];

        if ($permissionType->requires_manager_approval) {
            $steps[] = 'manager';
        }

        if ($permissionType->requires_direction_approval) {
            $steps[] = 'direction';
        }

        if ($permissionType->requires_hr_approval) {
            $steps[] = 'hr';
        }

        return $steps;
    }

    private function statusForStep(string $step): string
    {
        return match ($step) {
            'manager' => 'pendiente_jefatura',
            'direction' => 'pendiente_direccion',
            'hr' => 'pendiente_rrhh',
            default => 'ingresado',
        };
    }

    private function resolvePayrollStatus(PermissionRequest $permissionRequest): string
    {
        if (!$permissionRequest->affects_salary && $permissionRequest->with_pay !== false) {
            return 'no_aplica';
        }

        return 'por_informar';
    }

    private function syncRequestWatchers(PermissionRequest $permissionRequest): void
    {
        $permissionRequest->loadMissing([
            'permissionType.watchers.role:id,name,slug',
            'permissionType.watchers.user:id,name,email,staff_id',
            'staff.permissionWatchers.role:id,name,slug',
            'staff.permissionWatchers.user:id,name,email,staff_id',
            'requestedBy:id,name,email',
            'directManagerUser:id,name,email,staff_id',
        ]);

        $typeWatchers = $permissionRequest->permissionType?->watchers
            ? $permissionRequest->permissionType->watchers->where('active', true)->values()
            : collect();
        $staffWatchers = $permissionRequest->staff?->permissionWatchers
            ? $permissionRequest->staff->permissionWatchers->where('active', true)->values()
            : collect();

        $resolved = [];

        foreach ($typeWatchers as $typeWatcher) {
            $users = $this->resolveUsersForWatcher($typeWatcher, $permissionRequest);
            $sourceLabel = $this->labelForWatcher($typeWatcher, 'type');

            foreach ($users as $user) {
                if (!$user || !$user->active) {
                    continue;
                }

                if (!isset($resolved[$user->id])) {
                    $resolved[$user->id] = [
                        'permission_request_id' => $permissionRequest->id,
                        'user_id' => $user->id,
                        'permission_type_watcher_id' => $typeWatcher->id,
                        'staff_permission_watcher_id' => null,
                        'source_type' => $typeWatcher->target_type,
                        'source_label' => $sourceLabel,
                        'notify' => (bool) $typeWatcher->notify,
                        'can_view' => (bool) $typeWatcher->can_view,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    continue;
                }

                $resolved[$user->id]['notify'] = $resolved[$user->id]['notify'] || (bool) $typeWatcher->notify;
                $resolved[$user->id]['can_view'] = $resolved[$user->id]['can_view'] || (bool) $typeWatcher->can_view;
                $resolved[$user->id]['source_type'] = 'multiple';
                $resolved[$user->id]['source_label'] = collect([
                    $resolved[$user->id]['source_label'],
                    $sourceLabel,
                ])->filter()->unique()->implode(' | ');
                $resolved[$user->id]['updated_at'] = now();
            }
        }

        foreach ($staffWatchers as $staffWatcher) {
            $users = $this->resolveUsersForWatcher($staffWatcher, $permissionRequest);
            $sourceLabel = $this->labelForWatcher($staffWatcher, 'staff');

            foreach ($users as $user) {
                if (!$user || !$user->active) {
                    continue;
                }

                if (!isset($resolved[$user->id])) {
                    $resolved[$user->id] = [
                        'permission_request_id' => $permissionRequest->id,
                        'user_id' => $user->id,
                        'permission_type_watcher_id' => null,
                        'staff_permission_watcher_id' => $staffWatcher->id,
                        'source_type' => 'staff_' . $staffWatcher->target_type,
                        'source_label' => $sourceLabel,
                        'notify' => (bool) $staffWatcher->notify,
                        'can_view' => (bool) $staffWatcher->can_view,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    continue;
                }

                $resolved[$user->id]['notify'] = $resolved[$user->id]['notify'] || (bool) $staffWatcher->notify;
                $resolved[$user->id]['can_view'] = $resolved[$user->id]['can_view'] || (bool) $staffWatcher->can_view;
                $resolved[$user->id]['source_type'] = 'multiple';
                $resolved[$user->id]['source_label'] = collect([
                    $resolved[$user->id]['source_label'],
                    $sourceLabel,
                ])->filter()->unique()->implode(' | ');
                $resolved[$user->id]['updated_at'] = now();
            }
        }

        $permissionRequest->watchers()->delete();

        if ($resolved !== []) {
            $permissionRequest->watchers()->createMany(array_values($resolved));
        }
    }

    private function notifyStepReviewers(PermissionRequest $permissionRequest): void
    {
        $stepLabel = collect(PermissionRequest::STEP_OPTIONS)
            ->firstWhere('value', $permissionRequest->current_step)['label'] ?? $permissionRequest->current_step;

        $users = $this->usersForStep($permissionRequest);

        if ($users->isNotEmpty()) {
            Notification::send($users, new PendingPermissionReviewNotification($permissionRequest, $stepLabel));
        }
    }

    private function notifyConfiguredWatchers(
        PermissionRequest $permissionRequest,
        string $subject,
        string $headline,
        array $excludeUserIds = [],
    ): void {
        $permissionRequest->loadMissing('watchers.user:id,name,email');

        $watchers = $permissionRequest->watchers
            ->filter(fn (PermissionRequestWatcher $watcher) => $watcher->notify && $watcher->user)
            ->reject(fn (PermissionRequestWatcher $watcher) => in_array((int) $watcher->user_id, array_map('intval', $excludeUserIds), true))
            ->values();

        $users = $watchers
            ->pluck('user')
            ->filter(fn (?User $user) => $user && !empty($user->email))
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new PermissionRequestStatusNotification($permissionRequest, $subject, $headline));

        $permissionRequest->watchers()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->update(['notified_at' => now()]);
    }

    private function notifyStatus(PermissionRequest $permissionRequest, string $subject, string $headline, ?string $comment = null): void
    {
        $recipient = $permissionRequest->requestedBy;

        if ($recipient) {
            $recipient->notify(new PermissionRequestStatusNotification($permissionRequest, $subject, $headline, $comment));
        }
    }

    private function notifyPayrollReview(PermissionRequest $permissionRequest, string $subject, string $headline): void
    {
        $users = $this->usersByPermission('revisar_permisos_rrhh');

        if ($users->isNotEmpty()) {
            Notification::send($users, new PermissionRequestStatusNotification($permissionRequest, $subject, $headline));
        }
    }

    private function usersForStep(PermissionRequest $permissionRequest): Collection
    {
        return match ($permissionRequest->current_step) {
            'manager' => User::query()->whereKey($permissionRequest->direct_manager_user_id)->get(),
            'direction' => $this->usersByPermission('aprobar_permisos_direccion', 'direccion'),
            'hr' => $this->usersByPermission('revisar_permisos_rrhh'),
            default => collect(),
        };
    }

    private function resolveUsersForWatcher(PermissionTypeWatcher|StaffPermissionWatcher $watcher, PermissionRequest $permissionRequest): Collection
    {
        return match ($watcher->target_type) {
            'manager' => User::query()
                ->where('active', true)
                ->whereKey($permissionRequest->direct_manager_user_id)
                ->get(),
            'direction' => $this->usersByPermission('aprobar_permisos_direccion', 'direccion'),
            'hr' => $this->usersByPermission('revisar_permisos_rrhh', 'rrhh'),
            'role' => User::query()
                ->where('active', true)
                ->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($watcher->role_id))
                ->get(),
            'user' => User::query()
                ->where('active', true)
                ->whereKey($watcher->user_id)
                ->get(),
            default => collect(),
        };
    }

    private function labelForWatcher(PermissionTypeWatcher|StaffPermissionWatcher $watcher, string $scope): string
    {
        $prefix = $scope === 'staff' ? 'Funcionario' : 'Tipo';

        return match ($watcher->target_type) {
            'manager' => $prefix . ': Jefatura directa',
            'direction' => $prefix . ': Dirección',
            'hr' => $prefix . ': RRHH / Administración',
            'role' => $prefix . ': Rol ' . ($watcher->role?->name ?? 'Sin rol'),
            'user' => $prefix . ': Usuario ' . ($watcher->user?->name ?? 'Sin usuario'),
            default => $prefix . ': Configuración',
        };
    }

    private function usersByPermission(string $permissionSlug, ?string $roleSlug = null): Collection
    {
        return User::query()
            ->where('active', true)
            ->where(function ($query) use ($permissionSlug, $roleSlug) {
                $query
                    ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn ($permissionQuery) => $permissionQuery->where('slug', $permissionSlug));

                if ($roleSlug) {
                    $query->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', $roleSlug));
                }
            })
            ->get()
            ->unique('id')
            ->values();
    }

    private function loadRequest(PermissionRequest $permissionRequest): PermissionRequest
    {
        return $permissionRequest->fresh()->load([
            'staff:id,full_name,rut,cargo_id,institutional_email,personal_email,start_date,workday,contract_hours',
            'staff.cargo:id,name',
            'staff.departments:id,name,color,responsible_staff_id',
            'permissionType:id,name,requires_attachment,allows_with_pay,allows_without_pay,allows_hourly,allows_half_day,requires_manager_approval,requires_direction_approval,requires_hr_approval,max_days,minimum_notice_days,allows_retroactive,affects_salary,affects_attendance,requires_replacement,active',
            'departments:id,name,color,responsible_staff_id',
            'requestedBy:id,name,email,staff_id',
            'createdBy:id,name',
            'updatedBy:id,name',
            'directManagerUser:id,name,email,staff_id',
            'approvals.approverUser:id,name,email',
            'documents.uploadedByUser:id,name,email',
            'documents.validatedByUser:id,name,email',
            'replacements.replacedStaff:id,full_name',
            'replacements.replacementStaff:id,full_name',
            'watchers.user:id,name,email',
            'logs.user:id,name,email',
        ]);
    }

    private function recordLog(
        PermissionRequest $permissionRequest,
        ?User $user,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        array $details = [],
    ): void {
        $permissionRequest->logs()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'details' => $details,
        ]);
    }
}
