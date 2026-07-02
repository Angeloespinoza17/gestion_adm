<?php

namespace App\Http\Controllers\Spaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spaces\StoreDependencyReservationRequest;
use App\Http\Requests\Spaces\UpdateDependencyReservationRequest;
use App\Models\DependencyReservation;
use App\Models\Department;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DependencyReservationController extends Controller
{
    public function catalogs(): JsonResponse
    {
        $this->syncFinishedReservations();

        return response()->json([
            'dependencies' => MaintenanceDependency::query()
                ->where('is_reservable', true)
                ->with([
                    'type:id,name,color',
                    'responsibleStaff:id,full_name',
                    'approvers:id,name,email,staff_id',
                    'approvers.staff:id,full_name,rut',
                ])
                ->orderBy('name')
                ->get([
                    'id',
                    'dependency_type_id',
                    'responsible_staff_id',
                    'code',
                    'name',
                    'availability_status',
                    'calendar_color',
                    'requires_approval',
                    'location',
                    'floor_sector',
                ]),
            'dependency_types' => \App\Models\DependencyType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'staff' => Staff::query()
                ->with('departments:id,name,color')
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut']),
            'departments' => Department::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'statuses' => [
                ['value' => DependencyReservation::STATUS_PENDING, 'label' => 'Pendiente'],
                ['value' => DependencyReservation::STATUS_APPROVED, 'label' => 'Aprobada'],
                ['value' => DependencyReservation::STATUS_REJECTED, 'label' => 'Rechazada'],
                ['value' => DependencyReservation::STATUS_CANCELLED, 'label' => 'Cancelada'],
                ['value' => DependencyReservation::STATUS_FINISHED, 'label' => 'Finalizada'],
            ],
            'repetition_types' => [
                ['value' => 'none', 'label' => 'Sin repetición'],
                ['value' => 'daily', 'label' => 'Diaria'],
                ['value' => 'weekly', 'label' => 'Semanal'],
                ['value' => 'monthly', 'label' => 'Mensual'],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->syncFinishedReservations();

        $search = trim((string) $request->query('search'));
        $dependencyId = $request->query('dependency_id');
        $typeId = $request->query('dependency_type_id');
        $staffId = $request->query('staff_id');
        $departmentId = $request->query('department_id');
        $status = trim((string) $request->query('status'));
        $date = $request->query('date');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = DependencyReservation::query()
            ->with([
                'dependency:id,dependency_type_id,responsible_staff_id,code,name,calendar_color,requires_approval,availability_status',
                'dependency.type:id,name,color',
                'dependency.approvers:id,name,email,staff_id',
                'staff:id,full_name,rut',
                'staff.departments:id,name,color',
                'department:id,name,color',
                'createdBy:id,name',
                'approvedBy:id,name',
                'cancelledBy:id,name',
                'collaborators:id,dependency_reservation_id,staff_id,external_email',
                'collaborators.staff:id,full_name,rut',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('activity', 'like', "%{$search}%")
                        ->orWhere('observations', 'like', "%{$search}%")
                        ->orWhereHas('dependency', fn ($dependencyQuery) => $dependencyQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($typeId, fn ($query) => $query->whereHas('dependency', fn ($dependencyQuery) => $dependencyQuery->where('dependency_type_id', $typeId)))
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($status !== '', fn ($query) => $query->where('status', $status));

        if ($date) {
            $query->whereDate('starts_at', Carbon::parse((string) $date)->toDateString());
        }

        if ($dateFrom) {
            $query->where('ends_at', '>=', Carbon::parse((string) $dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('starts_at', '<=', Carbon::parse((string) $dateTo)->endOfDay());
        }

        $reservations = $query
            ->orderBy('starts_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($reservations);
    }

    public function show(DependencyReservation $dependencyReservation): JsonResponse
    {
        $this->syncFinishedReservations();

        return response()->json([
            'data' => $this->loadReservation($dependencyReservation),
        ]);
    }

    public function store(StoreDependencyReservationRequest $request): JsonResponse
    {
        $dependency = MaintenanceDependency::query()
            ->where('is_reservable', true)
            ->findOrFail($request->integer('maintenance_dependency_id'));
        $this->assertDependencyReservable($dependency);
        $collaboratorStaffIds = collect($request->input('collaborator_staff_ids', []))->map(fn ($id) => (int) $id)->all();
        $collaboratorExternalEmails = collect($request->input('collaborator_external_emails', []))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->all();

        $occurrences = $this->buildOccurrences(
            $request->startsAt(),
            $request->endsAt(),
            (string) $request->input('repetition_type', 'none'),
            $request->input('repetition_until')
        );

        $seriesUuid = count($occurrences) > 1 ? (string) Str::uuid() : null;

        $reservations = DB::transaction(function () use ($request, $dependency, $occurrences, $seriesUuid, $collaboratorStaffIds, $collaboratorExternalEmails) {
            $items = new Collection();

            foreach ($occurrences as [$startsAt, $endsAt]) {
                $this->ensureNoOverlap($dependency->id, $startsAt, $endsAt);

                $reservation = DependencyReservation::query()->create([
                    'maintenance_dependency_id' => $dependency->id,
                    'staff_id' => $request->integer('staff_id'),
                    'department_id' => $request->integer('department_id') ?: null,
                    'title' => $request->string('title')->toString(),
                    'activity' => $request->input('activity'),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'repetition_type' => $request->input('repetition_type', 'none'),
                    'repetition_until' => $request->input('repetition_until'),
                    'series_uuid' => $seriesUuid,
                    'status' => $dependency->requires_approval
                        ? DependencyReservation::STATUS_PENDING
                        : DependencyReservation::STATUS_APPROVED,
                    'observations' => $request->input('observations'),
                    'estimated_attendees' => $request->input('estimated_attendees'),
                    'special_requirements' => $request->input('special_requirements'),
                    'created_by' => $request->user()?->id,
                    'approved_at' => $dependency->requires_approval ? null : now(),
                ]);

                $this->syncCollaborators($reservation, $collaboratorStaffIds, $collaboratorExternalEmails);
                $items->push($this->loadReservation($reservation));
            }

            return $items;
        });

        return response()->json([
            'message' => $reservations->count() > 1
                ? 'Serie de reservas creada correctamente.'
                : 'Reserva creada correctamente.',
            'data' => $reservations,
        ], 201);
    }

    public function update(UpdateDependencyReservationRequest $request, DependencyReservation $dependencyReservation): JsonResponse
    {
        $this->syncFinishedReservations();
        $this->assertReservationEditable($request, $dependencyReservation);

        $dependency = MaintenanceDependency::query()
            ->where('is_reservable', true)
            ->findOrFail($request->integer('maintenance_dependency_id'));
        $this->assertDependencyReservable($dependency);
        $collaboratorStaffIds = collect($request->input('collaborator_staff_ids', []))->map(fn ($id) => (int) $id)->all();
        $collaboratorExternalEmails = collect($request->input('collaborator_external_emails', []))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->all();

        $startsAt = $request->startsAt();
        $endsAt = $request->endsAt();

        $this->ensureNoOverlap($dependency->id, $startsAt, $endsAt, $dependencyReservation->id);

        $status = $dependencyReservation->status;
        if (
            $dependencyReservation->status === DependencyReservation::STATUS_PENDING
            && !$dependency->requires_approval
        ) {
            $status = DependencyReservation::STATUS_APPROVED;
        }

        $dependencyReservation->update([
            'maintenance_dependency_id' => $dependency->id,
            'staff_id' => $request->integer('staff_id'),
            'department_id' => $request->integer('department_id') ?: null,
            'title' => $request->string('title')->toString(),
            'activity' => $request->input('activity'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'repetition_type' => $request->input('repetition_type', 'none'),
            'repetition_until' => $request->input('repetition_until'),
            'status' => $status,
            'observations' => $request->input('observations'),
            'estimated_attendees' => $request->input('estimated_attendees'),
            'special_requirements' => $request->input('special_requirements'),
            'approved_at' => $status === DependencyReservation::STATUS_APPROVED
                ? ($dependencyReservation->approved_at ?: now())
                : $dependencyReservation->approved_at,
        ]);
        $this->syncCollaborators($dependencyReservation, $collaboratorStaffIds, $collaboratorExternalEmails);

        return response()->json([
            'message' => 'Reserva actualizada correctamente.',
            'data' => $this->loadReservation($dependencyReservation->fresh()),
        ]);
    }

    public function cancel(Request $request, DependencyReservation $dependencyReservation): JsonResponse
    {
        $this->syncFinishedReservations();

        if (!$request->user()?->hasPermission('cancelar_reservas')) {
            return response()->json(['message' => 'Forbidden. Missing permission: cancelar_reservas'], 403);
        }

        if ($dependencyReservation->status === DependencyReservation::STATUS_FINISHED) {
            return response()->json([
                'message' => 'No es posible cancelar una reserva finalizada.',
            ], 422);
        }

        $dependencyReservation->update([
            'status' => DependencyReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Reserva cancelada correctamente.',
            'data' => $this->loadReservation($dependencyReservation->fresh()),
        ]);
    }

    public function approve(Request $request, DependencyReservation $dependencyReservation): JsonResponse
    {
        $this->syncFinishedReservations();
        $this->ensureCanModerate($request, $dependencyReservation, 'aprobar_reservas');

        if ($dependencyReservation->status !== DependencyReservation::STATUS_PENDING) {
            return response()->json([
                'message' => 'Solo las reservas pendientes pueden aprobarse.',
            ], 422);
        }

        $this->ensureNoOverlap(
            $dependencyReservation->maintenance_dependency_id,
            Carbon::parse($dependencyReservation->starts_at),
            Carbon::parse($dependencyReservation->ends_at),
            $dependencyReservation->id
        );

        $dependencyReservation->update([
            'status' => DependencyReservation::STATUS_APPROVED,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        return response()->json([
            'message' => 'Reserva aprobada correctamente.',
            'data' => $this->loadReservation($dependencyReservation->fresh()),
        ]);
    }

    public function reject(Request $request, DependencyReservation $dependencyReservation): JsonResponse
    {
        $this->syncFinishedReservations();
        $this->ensureCanModerate($request, $dependencyReservation, 'rechazar_reservas');

        if ($dependencyReservation->status !== DependencyReservation::STATUS_PENDING) {
            return response()->json([
                'message' => 'Solo las reservas pendientes pueden rechazarse.',
            ], 422);
        }

        $dependencyReservation->update([
            'status' => DependencyReservation::STATUS_REJECTED,
            'approved_by' => $request->user()?->id,
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        return response()->json([
            'message' => 'Reserva rechazada correctamente.',
            'data' => $this->loadReservation($dependencyReservation->fresh()),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $this->syncFinishedReservations();

        $dateFrom = $request->query('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->query('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->endOfDay()
            : now()->endOfMonth();

        $dependencyId = $request->query('dependency_id');
        $typeId = $request->query('dependency_type_id');
        $staffId = $request->query('staff_id');
        $departmentId = $request->query('department_id');
        $status = trim((string) $request->query('status'));

        $events = DependencyReservation::query()
            ->with([
                'dependency:id,dependency_type_id,name,calendar_color',
                'dependency.type:id,name',
                'staff:id,full_name',
                'department:id,name,color',
            ])
            ->where('status', DependencyReservation::STATUS_APPROVED)
            ->where('ends_at', '>=', $dateFrom)
            ->where('starts_at', '<=', $dateTo)
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($typeId, fn ($query) => $query->whereHas('dependency', fn ($dependencyQuery) => $dependencyQuery->where('dependency_type_id', $typeId)))
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('starts_at')
            ->get()
            ->map(function (DependencyReservation $reservation) {
                return [
                    'id' => $reservation->id,
                    'title' => $reservation->title,
                    'start' => $reservation->starts_at?->format('Y-m-d\TH:i:s'),
                    'end' => $reservation->ends_at?->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => $reservation->event_color,
                    'borderColor' => $reservation->event_color,
                    'extendedProps' => [
                        'dependency_name' => $reservation->dependency?->name,
                        'staff_name' => $reservation->staff?->full_name,
                        'department_name' => $reservation->department?->name,
                        'status' => $reservation->status,
                        'activity' => $reservation->activity,
                        'observations' => $reservation->observations,
                    ],
                ];
            });

        return response()->json([
            'data' => $events,
        ]);
    }

    private function loadReservation(DependencyReservation $reservation): DependencyReservation
    {
        return $reservation->load([
            'dependency:id,dependency_type_id,responsible_staff_id,code,name,calendar_color,requires_approval,availability_status',
            'dependency.type:id,name,color',
            'dependency.responsibleStaff:id,full_name',
            'dependency.approvers:id,name,email,staff_id',
            'dependency.approvers.staff:id,full_name,rut',
            'staff:id,full_name,rut',
            'staff.departments:id,name,color',
            'department:id,name,color',
            'createdBy:id,name',
            'approvedBy:id,name',
            'cancelledBy:id,name',
            'collaborators:id,dependency_reservation_id,staff_id,external_email',
            'collaborators.staff:id,full_name,rut',
        ]);
    }

    private function assertDependencyReservable(MaintenanceDependency $dependency): void
    {
        if (
            in_array($dependency->availability_status, [
                MaintenanceDependency::AVAILABILITY_UNAVAILABLE,
                MaintenanceDependency::AVAILABILITY_MAINTENANCE,
                MaintenanceDependency::AVAILABILITY_BLOCKED,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'maintenance_dependency_id' => 'La dependencia no está disponible para reservas.',
            ]);
        }
    }

    private function ensureNoOverlap(int $dependencyId, Carbon $startsAt, Carbon $endsAt, ?int $ignoreId = null): void
    {
        $exists = DependencyReservation::query()
            ->overlapping($dependencyId, $startsAt, $endsAt, $ignoreId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_time' => 'Ya existe una reserva para la dependencia en el horario indicado.',
            ]);
        }
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function buildOccurrences(Carbon $startsAt, Carbon $endsAt, string $type, ?string $until): array
    {
        $occurrences = [[$startsAt->copy(), $endsAt->copy()]];

        if ($type === 'none' || !$until) {
            return $occurrences;
        }

        $untilDate = Carbon::parse($until)->endOfDay();
        $currentStart = $startsAt->copy();
        $currentEnd = $endsAt->copy();

        while (true) {
            $currentStart = match ($type) {
                'daily' => $currentStart->copy()->addDay(),
                'weekly' => $currentStart->copy()->addWeek(),
                'monthly' => $currentStart->copy()->addMonth(),
                default => $currentStart->copy(),
            };
            $currentEnd = match ($type) {
                'daily' => $currentEnd->copy()->addDay(),
                'weekly' => $currentEnd->copy()->addWeek(),
                'monthly' => $currentEnd->copy()->addMonth(),
                default => $currentEnd->copy(),
            };

            if ($currentStart->gt($untilDate)) {
                break;
            }

            $occurrences[] = [$currentStart->copy(), $currentEnd->copy()];
        }

        return $occurrences;
    }

    private function assertReservationEditable(Request $request, DependencyReservation $reservation): void
    {
        if (
            in_array($reservation->status, [
                DependencyReservation::STATUS_APPROVED,
                DependencyReservation::STATUS_FINISHED,
            ], true)
            && !$request->user()?->hasPermission('administrar_calendario')
        ) {
            abort(403, 'No tienes permisos para modificar reservas aprobadas o finalizadas.');
        }
    }

    private function ensureCanModerate(Request $request, DependencyReservation $reservation, string $permission): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if (
            $user->hasPermission('administrar_calendario')
            || $user->hasPermission($permission)
        ) {
            return;
        }

        $isAssignedApprover = $reservation->dependency()
            ->whereHas('approvers', fn ($query) => $query->where('users.id', $user->id))
            ->exists();

        if ($isAssignedApprover) {
            return;
        }

        abort(403, 'No tienes permisos para gestionar esta reserva.');
    }

    private function syncCollaborators(
        DependencyReservation $reservation,
        array $staffIds,
        array $externalEmails
    ): void {
        $rows = collect($staffIds)
            ->filter()
            ->unique()
            ->map(fn ($staffId) => [
                'staff_id' => (int) $staffId,
                'external_email' => null,
            ])
            ->values()
            ->all();

        $rows = array_merge(
            $rows,
            collect($externalEmails)
                ->filter()
                ->unique()
                ->map(fn ($email) => [
                    'staff_id' => null,
                    'external_email' => $email,
                ])
                ->values()
                ->all()
        );

        $reservation->collaborators()->delete();

        if ($rows === []) {
            return;
        }

        $reservation->collaborators()->createMany($rows);
    }

    private function syncFinishedReservations(): void
    {
        DependencyReservation::query()
            ->where('status', DependencyReservation::STATUS_APPROVED)
            ->where('ends_at', '<', now())
            ->update(['status' => DependencyReservation::STATUS_FINISHED]);
    }
}
