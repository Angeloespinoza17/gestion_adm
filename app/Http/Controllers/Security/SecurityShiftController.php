<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\SaveSecurityShiftRequest;
use App\Http\Requests\Security\StoreSecurityRoundRequest;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityShift;
use App\Services\Security\SecurityAccessService;
use App\Services\Security\SecurityRoundService;
use App\Services\Security\SecurityShiftScheduleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SecurityShiftController extends Controller
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
        private readonly SecurityRoundService $roundService,
        private readonly SecurityShiftScheduleService $shiftScheduleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecurityShift::class);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $staffId = $request->query('staff_id');
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $templatesOnly = $request->boolean('templates_only');
        $now = Carbon::now(config('app.timezone'));

        $query = $this->accessService->visibleShiftsQuery($request->user())
            ->with([
                'staff:id,full_name,rut,cargo_id',
                'staff.cargo:id,name',
                'parentShift:id,staff_id,coverage_label',
            ])
            ->withCount([
                'rounds',
                'incidents',
                'incidents as pending_incidents_count' => fn ($builder) => $builder->whereHas('status', fn ($statusQuery) => $statusQuery->where('is_closed', false)),
                'incidents as critical_incidents_count' => fn ($builder) => $builder->where('priority', 'critica'),
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('coverage_label', 'like', "%{$search}%")
                        ->orWhere('general_observations', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($staffId, fn ($builder) => $builder->where('staff_id', $staffId))
            ->when($templatesOnly, fn ($builder) => $builder
                ->where('schedule_type', SecurityShift::SCHEDULE_WEEKLY)
                ->whereNull('parent_shift_id')
                ->where('status', '!=', SecurityShift::STATUS_CANCELADO)
                ->with(['generatedShifts' => fn ($generatedQuery) => $generatedQuery
                    ->select(['id', 'parent_shift_id', 'generated_for_date', 'status'])
                    ->whereDate('generated_for_date', '>=', $now->copy()->subDay()->toDateString())
                    ->whereDate('generated_for_date', '<=', $now->toDateString())]))
            ->when($from !== '', function ($builder) use ($from) {
                $builder->where(function ($dateQuery) use ($from) {
                    $dateQuery
                        ->whereDate('scheduled_start_at', '>=', $from)
                        ->orWhereDate('recurrence_starts_on', '>=', $from)
                        ->orWhereDate('generated_for_date', '>=', $from);
                });
            })
            ->when($to !== '', function ($builder) use ($to) {
                $builder->where(function ($dateQuery) use ($to) {
                    $dateQuery
                        ->whereDate('scheduled_end_at', '<=', $to)
                        ->orWhereDate('recurrence_ends_on', '<=', $to)
                        ->orWhereDate('generated_for_date', '<=', $to);
                });
            })
            ->when(
                $templatesOnly,
                fn ($builder) => $builder->orderBy('staff_id'),
                fn ($builder) => $builder->orderByDesc('scheduled_start_at')
            );

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);
        $canManageShifts = $this->accessService->canManageShifts($request->user());
        $canRegisterRounds = $this->accessService->canRegisterRounds($request->user());
        $this->decoratePaginator($paginator, $request, $now, $canManageShifts, $canRegisterRounds);

        return response()->json($paginator);
    }

    public function show(SecurityShift $securityShift): JsonResponse
    {
        $this->authorize('view', $securityShift);

        $shift = $securityShift->load([
            'staff:id,full_name,rut,cargo_id,institutional_email,phone',
            'staff.cargo:id,name',
            'parentShift:id,staff_id,coverage_label,schedule_type,weekdays,template_start_time,template_end_time,recurrence_starts_on,recurrence_ends_on',
            'generatedShifts' => fn ($query) => $query
                ->select(['id', 'parent_shift_id', 'generated_for_date', 'scheduled_start_at', 'scheduled_end_at', 'status'])
                ->withCount(['rounds', 'incidents'])
                ->limit(8),
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
            'startedBy:id,name,email',
            'closedBy:id,name,email',
            'rounds.recordedBy:id,name,email',
            'rounds.evidences',
            'rounds.sectors.dependency:id,code,name,sector,zone',
            'rounds.incidents.status:id,code,name,color,is_closed',
            'rounds.incidents.currentResponsible:id,name,email',
            'rounds.incidents.evidences',
            'rounds.incidents.comments.user:id,name,email',
            'rounds.incidents.comments.status:id,code,name,color',
            'rounds.incidents.comments.assignedTo:id,name,email',
            'rounds.incidents.assignments.user:id,name,email',
        ]);

        $registration = $this->shiftScheduleService->registrationState($shift);
        $registration['can_register'] = $registration['open']
            && $this->accessService->canRegisterRoundOnShift(request()->user(), $shift);
        $shift->setAttribute('registration', $registration);

        $recentRounds = collect();
        if ($shift->is_weekly_template) {
            $recentRounds = SecurityRound::query()
                ->whereHas('shift', fn ($query) => $query->where('parent_shift_id', $shift->id))
                ->with([
                    'shift:id,parent_shift_id,staff_id,scheduled_start_at,scheduled_end_at,status',
                    'recordedBy:id,name,email',
                    'sectors:id,security_round_id,sector_name,sector_state,observations,display_order',
                    'incidents:id,security_shift_id,security_round_id,status_id,priority,title,description,sector_name',
                ])
                ->latest('recorded_at')
                ->limit(12)
                ->get();
        }

        return response()->json([
            'data' => $shift,
            'recent_rounds' => $recentRounds,
        ]);
    }

    public function store(SaveSecurityShiftRequest $request): JsonResponse
    {
        $this->authorize('create', SecurityShift::class);

        $payload = $this->normalizeShiftPayload($request);
        $created = false;

        $shift = DB::transaction(function () use ($request, $payload, &$created) {
            $shift = null;
            if (($payload['schedule_type'] ?? null) === SecurityShift::SCHEDULE_WEEKLY) {
                $shift = SecurityShift::query()
                    ->where('staff_id', $payload['staff_id'])
                    ->where('schedule_type', SecurityShift::SCHEDULE_WEEKLY)
                    ->whereNull('parent_shift_id')
                    ->where('status', '!=', SecurityShift::STATUS_CANCELADO)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();
            }

            if ($shift) {
                $shift->update([
                    ...$payload,
                    'updated_by' => $request->user()->id,
                ]);

                return $shift;
            }

            $created = true;

            return SecurityShift::create([
                ...$payload,
                'status' => $request->validated()['status'] ?? SecurityShift::STATUS_PROGRAMADO,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => $created
                ? 'Días de trabajo asignados correctamente.'
                : 'Días de trabajo actualizados correctamente.',
            'data' => $shift->load(['staff:id,full_name']),
        ], $created ? 201 : 200);
    }

    public function update(SaveSecurityShiftRequest $request, SecurityShift $securityShift): JsonResponse
    {
        $this->authorize('update', $securityShift);

        $securityShift->update([
            ...$this->normalizeShiftPayload($request, $securityShift),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Turno actualizado correctamente.',
            'data' => $securityShift->fresh()->load(['staff:id,full_name']),
        ]);
    }

    public function start(Request $request, SecurityShift $securityShift): JsonResponse
    {
        $this->authorize('start', $securityShift);

        $activeShift = $this->shiftScheduleService->materializeOccurrence($securityShift, Carbon::now(config('app.timezone')), $request->user()->id);

        $activeShift->update([
            'status' => SecurityShift::STATUS_EN_CURSO,
            'started_at' => $activeShift->started_at ?: now(),
            'started_by_user_id' => $activeShift->started_by_user_id ?: $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Turno iniciado correctamente.',
            'data' => $activeShift->fresh()->load(['staff:id,full_name']),
        ]);
    }

    public function finish(Request $request, SecurityShift $securityShift): JsonResponse
    {
        $this->authorize('finish', $securityShift);

        $payload = $request->validate([
            'closing_observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([SecurityShift::STATUS_FINALIZADO, SecurityShift::STATUS_CANCELADO])],
        ]);

        if ($securityShift->is_weekly_template) {
            return response()->json([
                'message' => 'Debes cerrar la instancia diaria del turno, no la plantilla semanal.',
            ], 422);
        }

        $securityShift->update([
            'status' => $payload['status'] ?? SecurityShift::STATUS_FINALIZADO,
            'ended_at' => now(),
            'closed_by_user_id' => $request->user()->id,
            'closing_observations' => $payload['closing_observations'] ?? $securityShift->closing_observations,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Turno cerrado correctamente.',
            'data' => $securityShift->fresh(),
        ]);
    }

    public function storeRound(StoreSecurityRoundRequest $request, SecurityShift $securityShift): JsonResponse
    {
        $this->authorize('createRound', $securityShift);

        $payload = json_decode((string) $request->input('payload'), true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'El payload de la ronda no es válido.'], 422);
        }

        $now = Carbon::now(config('app.timezone'));
        $isAdministrativeEntry = filter_var($payload['administrative_entry'] ?? false, FILTER_VALIDATE_BOOL);

        if ($isAdministrativeEntry) {
            abort_unless($request->user()->isSuperAdmin(), 403);

            $administrativePayload = validator($payload, [
                'occurrence_date' => ['required', 'date_format:Y-m-d'],
                'recorded_at' => ['required', 'date'],
            ])->validate();
            $occurrenceDate = Carbon::createFromFormat('Y-m-d', $administrativePayload['occurrence_date'], config('app.timezone'));
            $recordedAt = Carbon::parse($administrativePayload['recorded_at'], config('app.timezone'));
            $window = $this->shiftScheduleService->scheduledOccurrenceWindow($securityShift, $occurrenceDate);

            if (! $recordedAt->betweenIncluded($window['starts_at'], $window['ends_at'])) {
                throw ValidationException::withMessages([
                    'recorded_at' => 'La hora debe estar entre las 20:00 de la noche elegida y las 07:30 del día siguiente.',
                ]);
            }

            if ($recordedAt->gt($now)) {
                throw ValidationException::withMessages([
                    'recorded_at' => 'No puedes agregar un registro administrativo con una hora futura.',
                ]);
            }

            $securityShift = $this->shiftScheduleService->materializeScheduledOccurrence(
                $securityShift,
                $occurrenceDate,
                $request->user()->id,
            );
            $payload['recorded_at'] = $recordedAt->toDateTimeString();
            $payload['nochero_confirmation_name'] = null;
        } else {
            $securityShift = $this->shiftScheduleService->materializeOccurrence($securityShift, $now, $request->user()->id);
            $payload['recorded_at'] = $now->toDateTimeString();
        }

        $files = collect($request->file('evidence_files', []))
            ->mapWithKeys(fn ($file, $key) => [(string) $key => $file])
            ->all();

        $round = $this->roundService->createRound($securityShift, $payload, $files, $request->user());

        return response()->json([
            'message' => $isAdministrativeEntry
                ? 'Registro administrativo agregado al turno correctamente.'
                : 'Ronda registrada correctamente. El acta fue generada automáticamente.',
            'data' => $round,
            'security_shift_id' => $securityShift->id,
        ], 201);
    }

    private function normalizeShiftPayload(SaveSecurityShiftRequest $request, ?SecurityShift $shift = null): array
    {
        $payload = $request->validated();
        $scheduleType = $payload['schedule_type'] ?? SecurityShift::SCHEDULE_SINGLE;

        $base = [
            'staff_id' => $payload['staff_id'],
            'schedule_type' => $scheduleType,
            'maintenance_dependency_id' => null,
            'coverage_label' => $payload['coverage_label'] ?: 'Todo el colegio',
            'general_observations' => $payload['general_observations'] ?? null,
            'closing_observations' => $payload['closing_observations'] ?? null,
            'status' => $payload['status'] ?? ($shift?->status ?: SecurityShift::STATUS_PROGRAMADO),
        ];

        if ($scheduleType === SecurityShift::SCHEDULE_WEEKLY) {
            $startTime = SecurityShiftScheduleService::REGISTRATION_START_TIME;
            $endTime = SecurityShiftScheduleService::REGISTRATION_END_TIME;
            $referenceStart = Carbon::parse($payload['recurrence_starts_on'].' '.$startTime, config('app.timezone'));
            $referenceEnd = Carbon::parse($payload['recurrence_starts_on'].' '.$endTime, config('app.timezone'));
            if ($referenceEnd->lte($referenceStart)) {
                $referenceEnd->addDay();
            }

            return [
                ...$base,
                'scheduled_start_at' => $referenceStart,
                'scheduled_end_at' => $referenceEnd,
                'weekdays' => array_values($payload['weekdays'] ?? []),
                'template_start_time' => $startTime,
                'template_end_time' => $endTime,
                'recurrence_starts_on' => $payload['recurrence_starts_on'],
                'recurrence_ends_on' => $payload['recurrence_ends_on'] ?? null,
            ];
        }

        return [
            ...$base,
            'scheduled_start_at' => $payload['scheduled_start_at'],
            'scheduled_end_at' => $payload['scheduled_end_at'],
            'weekdays' => null,
            'template_start_time' => null,
            'template_end_time' => null,
            'recurrence_starts_on' => null,
            'recurrence_ends_on' => null,
        ];
    }

    private function decoratePaginator(
        LengthAwarePaginator $paginator,
        Request $request,
        Carbon $now,
        bool $canManageShifts,
        bool $canRegisterRounds,
    ): void {
        $paginator->setCollection(
            $paginator->getCollection()->map(function (SecurityShift $shift) use ($request, $now, $canManageShifts, $canRegisterRounds) {
                $registration = $this->shiftScheduleService->registrationState($shift, $now);
                $registration['can_register'] = $registration['open']
                    && ($canManageShifts || ($canRegisterRounds && $this->accessService->isShiftOwner($request->user(), $shift)))
                    && in_array($shift->status, [SecurityShift::STATUS_PROGRAMADO, SecurityShift::STATUS_EN_CURSO], true);

                return $shift
                    ->setAttribute('registration', $registration)
                    ->setRelation('dependency', null);
            })
        );
    }
}
