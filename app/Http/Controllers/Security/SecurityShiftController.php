<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\SaveSecurityShiftRequest;
use App\Http\Requests\Security\StoreSecurityRoundRequest;
use App\Models\Security\SecurityShift;
use App\Services\Security\SecurityAccessService;
use App\Services\Security\SecurityRoundService;
use App\Services\Security\SecurityShiftScheduleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SecurityShiftController extends Controller
{
    public function __construct(
        private readonly SecurityAccessService $accessService,
        private readonly SecurityRoundService $roundService,
        private readonly SecurityShiftScheduleService $shiftScheduleService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecurityShift::class);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $staffId = $request->query('staff_id');
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

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
            ->orderByDesc('scheduled_start_at');

        $paginator = $query->paginate((int) $request->query('per_page', 15));
        $this->decoratePaginator($paginator);

        return response()->json($paginator);
    }

    public function show(SecurityShift $securityShift): JsonResponse
    {
        $this->authorize('view', $securityShift);

        $shift = $securityShift->load([
                'staff:id,full_name,rut,cargo_id,institutional_email,phone',
                'staff.cargo:id,name',
                'parentShift:id,staff_id,coverage_label,schedule_type,weekdays,template_start_time,template_end_time,recurrence_starts_on,recurrence_ends_on',
                'generatedShifts:id,parent_shift_id,generated_for_date,scheduled_start_at,scheduled_end_at,status',
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

        return response()->json([
            'data' => $shift,
        ]);
    }

    public function store(SaveSecurityShiftRequest $request): JsonResponse
    {
        $this->authorize('create', SecurityShift::class);

        $shift = SecurityShift::create([
            ...$this->normalizeShiftPayload($request),
            'status' => $request->validated()['status'] ?? SecurityShift::STATUS_PROGRAMADO,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Turno de nochero creado correctamente.',
            'data' => $shift->load(['staff:id,full_name']),
        ], 201);
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
        if (!is_array($payload)) {
            return response()->json(['message' => 'El payload de la ronda no es válido.'], 422);
        }

        if ($securityShift->is_weekly_template) {
            $securityShift = $this->shiftScheduleService->materializeOccurrence($securityShift, Carbon::now(config('app.timezone')), $request->user()->id);
        }

        $files = collect($request->file('evidence_files', []))
            ->mapWithKeys(fn ($file, $key) => [(string) $key => $file])
            ->all();

        $round = $this->roundService->createRound($securityShift, $payload, $files, $request->user());

        return response()->json([
            'message' => 'Ronda registrada correctamente. El acta fue generada automáticamente.',
            'data' => $round,
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
            $referenceStart = Carbon::parse($payload['recurrence_starts_on'] . ' ' . $payload['template_start_time'], config('app.timezone'));
            $referenceEnd = Carbon::parse($payload['recurrence_starts_on'] . ' ' . $payload['template_end_time'], config('app.timezone'));
            if ($referenceEnd->lte($referenceStart)) {
                $referenceEnd->addDay();
            }

            return [
                ...$base,
                'scheduled_start_at' => $referenceStart,
                'scheduled_end_at' => $referenceEnd,
                'weekdays' => array_values($payload['weekdays'] ?? []),
                'template_start_time' => $payload['template_start_time'],
                'template_end_time' => $payload['template_end_time'],
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

    private function decoratePaginator(LengthAwarePaginator $paginator): void
    {
        $paginator->setCollection(
            $paginator->getCollection()->map(function (SecurityShift $shift) {
                return $shift->setRelation('dependency', null);
            })
        );
    }
}
