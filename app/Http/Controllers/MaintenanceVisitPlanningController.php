<?php

namespace App\Http\Controllers;

use App\Models\Attendance\SchoolDay;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceVisitPlanningBatch;
use App\Models\Staff;
use App\Services\Maintenance\MaintenanceVisitPlanningService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaintenanceVisitPlanningController extends Controller
{
    public function __construct(private readonly MaintenanceVisitPlanningService $planningService) {}

    public function preview(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $configuration = $this->validatedConfiguration($request);

        return response()->json([
            'message' => 'Propuesta generada. Revisa y ajusta las visitas antes de confirmar.',
            'data' => $this->planningService->preview($configuration),
            'configuration' => $configuration,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $idempotency = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $existingBatch = MaintenanceVisitPlanningBatch::query()
            ->where('idempotency_key', $idempotency['idempotency_key'])
            ->first();
        if ($existingBatch) {
            return $this->idempotentResponse($request, $existingBatch);
        }

        $configuration = $this->validatedConfiguration($request);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1000'],
            'rows.*.maintenance_dependency_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('is_maintenance_location', true)
                    ->where('active', true),
            ],
            'rows.*.responsible_staff_id' => [
                'required',
                'integer',
                Rule::exists('staff', 'id')->where('active', true)->where('can_receive_maintenance_orders', true),
            ],
            'rows.*.visit_date' => ['required', 'date', 'after_or_equal:configuration.start_date', 'before_or_equal:configuration.end_date'],
            'rows.*.visit_time' => ['required', 'date_format:H:i'],
            'rows.*.visit_type' => ['required', 'string', Rule::in($this->visitTypes())],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['idempotency_key'] = $idempotency['idempotency_key'];

        $staff = Staff::query()
            ->whereIn('id', collect($validated['rows'])->pluck('responsible_staff_id')->unique())
            ->where('active', true)
            ->where('can_receive_maintenance_orders', true)
            ->pluck('full_name', 'id');

        $rows = collect($validated['rows'])
            ->map(function (array $row) use ($staff): array {
                $row['responsible_staff_id'] = (int) $row['responsible_staff_id'];
                $row['maintenance_dependency_id'] = (int) $row['maintenance_dependency_id'];
                $row['responsible'] = (string) $staff->get($row['responsible_staff_id']);
                $row['notes'] = isset($row['notes']) && trim((string) $row['notes']) !== '' ? trim((string) $row['notes']) : null;

                return $row;
            })
            ->values()
            ->all();
        $this->validateRowsAgainstConfiguration($rows, $configuration);

        $result = DB::transaction(function () use ($request, $validated, $configuration, $rows): array {
            $duplicateBatch = MaintenanceVisitPlanningBatch::query()
                ->where('idempotency_key', $validated['idempotency_key'])
                ->lockForUpdate()
                ->first();
            if ($duplicateBatch) {
                return ['batch' => $duplicateBatch, 'created' => false];
            }

            $conflicts = $this->planningService->conflictsForRows($rows, true);
            if ($conflicts !== []) {
                $errors = [];
                foreach ($conflicts as $conflict) {
                    $errors['rows.'.$conflict['index']][] = $conflict['message'];
                }

                throw ValidationException::withMessages($errors);
            }

            $batch = MaintenanceVisitPlanningBatch::query()->create([
                'idempotency_key' => $validated['idempotency_key'],
                'period_start' => $configuration['start_date'],
                'period_end' => $configuration['end_date'],
                'status' => 'confirmed',
                'proposed_count' => count($rows),
                'confirmed_count' => count($rows),
                'configuration' => $configuration,
                'created_by_user_id' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            $now = now();
            MaintenanceVisit::query()->insert(collect($rows)->map(fn (array $row) => [
                'maintenance_dependency_id' => $row['maintenance_dependency_id'],
                'responsible' => $row['responsible'],
                'responsible_staff_id' => $row['responsible_staff_id'],
                'planning_batch_id' => $batch->id,
                'visit_date' => $row['visit_date'],
                'visit_time' => $row['visit_time'],
                'visit_type' => $row['visit_type'],
                'status' => 'Programada',
                'notes' => $row['notes'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return ['batch' => $batch, 'created' => true];
        }, 3);

        /** @var MaintenanceVisitPlanningBatch $batch */
        $batch = $result['batch'];
        if (! $result['created']) {
            return $this->idempotentResponse($request, $batch);
        }

        return response()->json([
            'message' => count($rows).' visitas programadas correctamente.',
            'data' => $this->batchPayload($batch),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function validatedConfiguration(Request $request): array
    {
        $validated = $request->validate([
            'configuration' => ['required', 'array'],
            'configuration.start_date' => ['required', 'date'],
            'configuration.end_date' => ['required', 'date', 'after_or_equal:configuration.start_date'],
            'configuration.responsible_staff_id' => [
                'required',
                'integer',
                Rule::exists('staff', 'id')->where('active', true)->where('can_receive_maintenance_orders', true),
            ],
            'configuration.dependency_ids' => ['nullable', 'array', 'max:500'],
            'configuration.dependency_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('is_maintenance_location', true)
                    ->where('active', true),
            ],
            'configuration.frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'configuration.interval_value' => ['required', 'integer', 'min:1', 'max:12'],
            'configuration.weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'configuration.weekdays.*' => ['required', 'integer', 'distinct', 'between:1,7'],
            'configuration.month_day' => ['required', 'integer', 'between:1,31'],
            'configuration.max_visits_per_day' => ['required', 'integer', 'between:1,12'],
            'configuration.visits_per_dependency' => ['required', 'integer', 'between:1,12'],
            'configuration.start_time' => ['required', 'date_format:H:i'],
            'configuration.slot_minutes' => ['required', 'integer', 'between:15,240'],
            'configuration.visit_type' => ['required', 'string', Rule::in($this->visitTypes())],
            'configuration.notes' => ['nullable', 'string', 'max:2000'],
            'configuration.exclude_weekends' => ['required', 'boolean'],
            'configuration.exclude_non_school_days' => ['required', 'boolean'],
        ]);

        $configuration = $validated['configuration'];
        $days = CarbonImmutable::parse($configuration['start_date'])
            ->diffInDays(CarbonImmutable::parse($configuration['end_date']));
        if ($days > 366) {
            throw ValidationException::withMessages([
                'configuration.end_date' => 'El período máximo para una propuesta es de 366 días.',
            ]);
        }

        $dependencyIds = collect($configuration['dependency_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if ($dependencyIds->isEmpty()) {
            $dependencyIds = $this->planningService->dependencies()->pluck('id');
        }
        if ($dependencyIds->isEmpty()) {
            throw ValidationException::withMessages([
                'configuration.dependency_ids' => 'No existen dependencias activas disponibles para planificar.',
            ]);
        }

        $requested = $dependencyIds->count() * (int) $configuration['visits_per_dependency'];
        if ($requested > 1000) {
            throw ValidationException::withMessages([
                'configuration.visits_per_dependency' => 'La propuesta no puede superar 1.000 visitas. Reduce dependencias o repeticiones.',
            ]);
        }

        $configuration['dependency_ids'] = $dependencyIds->all();
        $configuration['responsible_staff_id'] = (int) $configuration['responsible_staff_id'];
        $configuration['weekdays'] = collect($configuration['weekdays'])->map(fn ($day) => (int) $day)->sort()->values()->all();
        $configuration['notes'] = isset($configuration['notes']) && trim((string) $configuration['notes']) !== ''
            ? trim((string) $configuration['notes'])
            : null;

        return $configuration;
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->active && $request->user()?->isSuperAdmin(), 403);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $configuration
     */
    private function validateRowsAgainstConfiguration(array $rows, array $configuration): void
    {
        $errors = [];

        if ($configuration['exclude_weekends']) {
            foreach ($rows as $index => $row) {
                if (CarbonImmutable::parse($row['visit_date'])->isWeekend()) {
                    $errors['rows.'.$index.'.visit_date'][] = 'La fecha cae en fin de semana y la propuesta los excluye.';
                }
            }
        }

        if ($configuration['exclude_non_school_days']) {
            $excludedDates = SchoolDay::query()
                ->where('status', 'confirmed')
                ->where('is_school_day', false)
                ->whereIn('date', collect($rows)->pluck('visit_date')->unique())
                ->pluck('date')
                ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
                ->flip();

            foreach ($rows as $index => $row) {
                if ($excludedDates->has($row['visit_date'])) {
                    $errors['rows.'.$index.'.visit_date'][] = 'La fecha está marcada como no lectiva en el calendario escolar.';
                }
            }
        }

        $maxPerDay = (int) $configuration['max_visits_per_day'];
        collect($rows)
            ->groupBy('visit_date')
            ->filter(fn ($visits) => $visits->count() > $maxPerDay)
            ->each(function ($visits, string $date) use (&$errors, $rows, $maxPerDay): void {
                foreach ($rows as $index => $row) {
                    if ($row['visit_date'] === $date) {
                        $errors['rows.'.$index.'.visit_date'][] = "La fecha supera el máximo configurado de {$maxPerDay} visitas.";
                    }
                }
            });

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function idempotentResponse(Request $request, MaintenanceVisitPlanningBatch $batch): JsonResponse
    {
        abort_unless((int) $batch->created_by_user_id === (int) $request->user()->id, 409, 'La clave de confirmación ya fue utilizada.');

        return response()->json([
            'message' => 'Esta planificación ya había sido confirmada; no se duplicaron visitas.',
            'data' => $this->batchPayload($batch),
        ]);
    }

    /** @return array<string, mixed> */
    private function batchPayload(MaintenanceVisitPlanningBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'period_start' => $batch->period_start?->format('Y-m-d'),
            'period_end' => $batch->period_end?->format('Y-m-d'),
            'status' => $batch->status,
            'confirmed_count' => (int) $batch->confirmed_count,
            'confirmed_at' => $batch->confirmed_at?->toIso8601String(),
        ];
    }

    /** @return array<int, string> */
    private function visitTypes(): array
    {
        return ['Inspección', 'Mantención', 'Reunión', 'Otro'];
    }
}
