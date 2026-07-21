<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryMedicationAdministrationRequest;
use App\Http\Requests\Infirmary\SaveInfirmaryMedicationAuthorizationRequest;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Infirmary\InfirmaryMedicationMovement;
use App\Services\Infirmary\InfirmaryMedicationDailyStatusService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InfirmaryMedicationAuthorizationController extends Controller
{
    public function __construct(
        private readonly InfirmaryMedicationStockService $stockService,
        private readonly InfirmaryMedicationDailyStatusService $dailyStatusService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InfirmaryMedicationAuthorization::class);
        $this->stockService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $studentId = $request->query('student_profile_id');
        $medicationId = $request->query('medication_id');
        $status = trim((string) $request->query('status'));
        $dailyStatusFilter = trim((string) $request->query('daily_status'));
        $today = now(config('app.timezone'));
        $dayStart = $today->copy()->startOfDay();
        $dayEnd = $today->copy()->endOfDay();

        $query = InfirmaryMedicationAuthorization::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('diagnosis', 'like', "%{$search}%")
                        ->orWhere('physician_name', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('rut', 'like', "%{$search}%"))
                        ->orWhereHas('medication', fn ($medication) => $medication
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('commercial_name', 'like', "%{$search}%"));
                });
            })
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($medicationId, fn ($query) => $query->where('medication_id', $medicationId))
            ->when($status !== '', fn ($query) => $query->where('status', $status));

        $dailyRelations = [
            'schedules' => fn ($relation) => $relation->where('active', true)->orderBy('dose_order'),
            'administrations' => fn ($relation) => $relation->where(function ($administrations) use ($today, $dayStart, $dayEnd) {
                $administrations
                    ->whereDate('scheduled_for_date', $today->toDateString())
                    ->orWhere(function ($legacy) use ($dayStart, $dayEnd) {
                        $legacy
                            ->whereNull('scheduled_for_date')
                            ->whereBetween('administered_at', [$dayStart, $dayEnd]);
                    });
            }),
        ];

        if (in_array($dailyStatusFilter, ['pending', 'completed', 'exception', 'not_applicable'], true)) {
            $matchingIds = (clone $query)
                ->with($dailyRelations)
                ->get()
                ->filter(function (InfirmaryMedicationAuthorization $authorization) use ($today, $dailyStatusFilter) {
                    return $this->dailyStatusService->matchesFilter(
                        $this->dailyStatusService->forAuthorization($authorization, $today),
                        $dailyStatusFilter,
                    );
                })
                ->pluck('id');

            $query->whereIn('id', $matchingIds);
        }

        $items = $query
            ->with(array_merge([
                'student:id,first_name,last_name,rut',
                'medication:id,name,commercial_name,unit',
                'createdBy:id,name',
            ], $dailyRelations))
            ->withCount('administrations')
            ->latest('start_date')
            ->paginate((int) $request->query('per_page', 15));

        $items->getCollection()->each(function (InfirmaryMedicationAuthorization $authorization) use ($today) {
            $authorization->setAttribute(
                'daily_status',
                $this->dailyStatusService->forAuthorization($authorization, $today),
            );
        });

        $response = $items->toArray();
        $response['daily_status_date'] = $today->toDateString();

        return response()->json($response);
    }

    public function store(SaveInfirmaryMedicationAuthorizationRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryMedicationAuthorization::class);

        $routine = $this->routinePayload($request->validated());
        $authorization = DB::transaction(function () use ($routine, $request) {
            $authorization = InfirmaryMedicationAuthorization::query()->create(array_merge(
                $routine['attributes'],
                [
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]
            ));

            $this->syncSchedules($authorization, $routine['schedules']);

            return $authorization;
        });

        $this->stockService->refreshDynamicStatuses();

        $authorization = $authorization->fresh([
            'student:id,first_name,last_name,rut',
            'medication:id,name,commercial_name,unit',
            'schedules',
        ]);
        $authorization->setAttribute('daily_status', $this->dailyStatusService->forAuthorization($authorization));

        return response()->json([
            'message' => 'Rutina de suministro registrada correctamente.',
            'data' => $authorization,
        ], 201);
    }

    public function show(InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('view', $authorization);
        $this->stockService->refreshDynamicStatuses();

        $authorization->load([
            'student:id,first_name,last_name,rut,guardian_name,guardian_phone,guardian_email',
            'medication:id,name,commercial_name,unit,current_stock,status,expires_at',
            'schedules',
            'administrations.schedule',
            'administrations.medication:id,name,commercial_name,unit',
            'administrations.administeredBy:id,name',
            'documents.uploadedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
        $authorization->setAttribute('daily_status', $this->dailyStatusService->forAuthorization($authorization));

        return response()->json([
            'data' => $authorization,
        ]);
    }

    public function update(SaveInfirmaryMedicationAuthorizationRequest $request, InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('update', $authorization);

        $routine = $this->routinePayload($request->validated());

        DB::transaction(function () use ($routine, $authorization, $request) {
            $authorization->update(array_merge(
                $routine['attributes'],
                ['updated_by' => $request->user()?->id]
            ));
            $this->syncSchedules($authorization, $routine['schedules']);
        });

        $this->stockService->refreshDynamicStatuses();

        $authorization = $authorization->fresh([
            'student:id,first_name,last_name,rut',
            'medication:id,name,commercial_name,unit',
            'schedules',
        ]);
        $authorization->setAttribute('daily_status', $this->dailyStatusService->forAuthorization($authorization));

        return response()->json([
            'message' => 'Autorización actualizada correctamente.',
            'data' => $authorization,
        ]);
    }

    public function destroy(InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('delete', $authorization);

        foreach ($authorization->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $authorization->delete();

        return response()->json([
            'message' => 'Autorización eliminada correctamente.',
        ]);
    }

    public function storeAdministration(SaveInfirmaryMedicationAdministrationRequest $request, InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('update', $authorization);

        $payload = $request->validated();
        $medication = InfirmaryMedication::query()->findOrFail($payload['medication_id'] ?? $authorization->medication_id);
        $status = $payload['administration_status'] ?? InfirmaryMedicationAdministration::STATUS_ADMINISTRADA;
        $administeredAt = Carbon::parse($payload['administered_at'], config('app.timezone'));
        $scheduledForDate = $administeredAt->toDateString();
        $schedule = null;

        if (! empty($payload['schedule_id'])) {
            $schedule = $authorization->schedules()
                ->whereKey($payload['schedule_id'])
                ->where('active', true)
                ->first();

            if (! $schedule) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'La dosis seleccionada no pertenece a esta rutina.',
                ]);
            }

            $alreadyRegistered = InfirmaryMedicationAdministration::query()
                ->where('schedule_id', $schedule->id)
                ->whereDate('scheduled_for_date', $scheduledForDate)
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'Esta dosis ya fue registrada para la fecha seleccionada.',
                ]);
            }
        }

        $quantity = $status === InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA
            ? 0
            : (float) ($payload['quantity_administered'] ?? 0);
        $administration = InfirmaryMedicationAdministration::query()->create([
            'authorization_id' => $authorization->id,
            'schedule_id' => $schedule?->id,
            'attention_id' => $payload['attention_id'] ?? null,
            'medication_id' => $medication->id,
            'student_profile_id' => $payload['student_profile_id'] ?? $authorization->student_profile_id,
            'administered_at' => $administeredAt->format('Y-m-d H:i:s'),
            'scheduled_for_date' => $scheduledForDate,
            'administration_status' => $status,
            'administered_by_user_id' => $payload['administered_by_user_id'] ?? $request->user()?->id,
            'quantity_administered' => $quantity,
            'dose_amount' => $payload['dose_amount'] ?? $authorization->dose_amount,
            'dose_unit' => $payload['dose_unit'] ?? $authorization->dose_unit,
            'administration_route' => $payload['administration_route'] ?? $authorization->administration_route,
            'schedule_reference' => $payload['schedule_reference']
                ?? ($schedule?->scheduled_time
                    ? substr((string) $schedule->scheduled_time, 0, 5)
                    : ($schedule ? "Dosis {$schedule->dose_order}" : $authorization->schedule_text)),
            'non_administration_reason' => $status === InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA
                ? ($payload['non_administration_reason'] ?? null)
                : null,
            'source_type' => 'autorizacion',
            'observations' => $payload['observations'] ?? null,
        ]);

        if ($status === InfirmaryMedicationAdministration::STATUS_ADMINISTRADA && $quantity > 0) {
            $this->stockService->decreaseStock(
                $medication,
                InfirmaryMedicationMovement::TYPE_ADMINISTRACION,
                $quantity,
                $request->user(),
                'Administración de medicamento autorizada',
                null,
                $administration,
                $administeredAt,
            );
        }

        $freshAuthorization = $authorization->fresh([
            'schedules',
            'administrations.schedule',
            'administrations.medication:id,name,commercial_name,unit',
            'administrations.administeredBy:id,name',
        ]);
        $freshAuthorization->setAttribute(
            'daily_status',
            $this->dailyStatusService->forAuthorization($freshAuthorization),
        );

        return response()->json([
            'message' => $status === InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA
                ? 'No administración registrada correctamente.'
                : 'Administración registrada correctamente.',
            'data' => $administration->load(['schedule', 'medication:id,name,commercial_name,unit', 'administeredBy:id,name']),
            'authorization' => $freshAuthorization,
        ], 201);
    }

    /**
     * @return array{attributes: array<string, mixed>, schedules: array<int, array<string, mixed>>}
     */
    private function routinePayload(array $payload): array
    {
        $schedules = $payload['schedules'] ?? [];
        unset($payload['schedules']);

        $regimenType = $payload['regimen_type'] ?? InfirmaryMedicationAuthorization::REGIMEN_PERMANENTE;
        $startDate = ! empty($payload['start_date'])
            ? Carbon::parse($payload['start_date'])->startOfDay()
            : now()->startOfDay();

        if (! empty($payload['dose_amount']) && ! empty($payload['dose_unit'])) {
            $payload['dose'] = rtrim(rtrim(number_format((float) $payload['dose_amount'], 2, '.', ''), '0'), '.')
                .' '.$payload['dose_unit'];
        }

        if ($regimenType === InfirmaryMedicationAuthorization::REGIMEN_SOS) {
            $payload['daily_dose_count'] = null;
            $payload['schedule_mode'] = InfirmaryMedicationAuthorization::SCHEDULE_FLEXIBLE;
            $payload['frequency'] = 'S.O.S.';
            $payload['schedule_text'] = 'Según necesidad';
            $schedules = [];
        } else {
            $dailyDoseCount = max(1, min(12, (int) ($payload['daily_dose_count'] ?? 1)));
            $scheduleMode = $payload['schedule_mode'] ?? InfirmaryMedicationAuthorization::SCHEDULE_FIXED_TIME;
            $normalizedSchedules = collect($schedules)
                ->sortBy('dose_order')
                ->values()
                ->map(function (array $schedule, int $index) use ($scheduleMode) {
                    return [
                        'dose_order' => $index + 1,
                        'scheduled_time' => $scheduleMode === InfirmaryMedicationAuthorization::SCHEDULE_FIXED_TIME
                            ? substr((string) ($schedule['scheduled_time'] ?? ''), 0, 5)
                            : null,
                    ];
                })
                ->all();

            $payload['daily_dose_count'] = $dailyDoseCount;
            $payload['schedule_mode'] = $scheduleMode;
            $payload['frequency'] = $this->dailyFrequencyLabel($dailyDoseCount);
            $payload['schedule_text'] = $scheduleMode === InfirmaryMedicationAuthorization::SCHEDULE_FIXED_TIME
                ? collect($normalizedSchedules)->pluck('scheduled_time')->filter()->implode(' / ')
                : 'Sin horario fijo';
            $schedules = $normalizedSchedules;
        }

        if (in_array($regimenType, [
            InfirmaryMedicationAuthorization::REGIMEN_MESES,
            InfirmaryMedicationAuthorization::REGIMEN_SEMANAS,
            InfirmaryMedicationAuthorization::REGIMEN_DIAS,
        ], true)) {
            $quantity = max(1, (int) ($payload['duration_quantity'] ?? 1));
            $endDate = match ($regimenType) {
                InfirmaryMedicationAuthorization::REGIMEN_MESES => $startDate->copy()->addMonthsNoOverflow($quantity)->subDay(),
                InfirmaryMedicationAuthorization::REGIMEN_SEMANAS => $startDate->copy()->addWeeks($quantity)->subDay(),
                default => $startDate->copy()->addDays($quantity - 1),
            };

            $payload['end_date'] = $endDate->format('Y-m-d');
        }

        if (in_array($regimenType, [
            InfirmaryMedicationAuthorization::REGIMEN_PERMANENTE,
            InfirmaryMedicationAuthorization::REGIMEN_SOS,
        ], true)) {
            $payload['end_date'] = null;
            $payload['duration_quantity'] = null;
        }

        if ($regimenType === InfirmaryMedicationAuthorization::REGIMEN_FECHA_ESPECIFICA) {
            $payload['duration_quantity'] = null;
        }

        return [
            'attributes' => $payload,
            'schedules' => $schedules,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    private function syncSchedules(
        InfirmaryMedicationAuthorization $authorization,
        array $schedules,
    ): void {
        $doseOrders = [];

        foreach ($schedules as $schedule) {
            $doseOrder = (int) $schedule['dose_order'];
            $doseOrders[] = $doseOrder;
            $authorization->schedules()->updateOrCreate(
                ['dose_order' => $doseOrder],
                [
                    'scheduled_time' => $schedule['scheduled_time'] ?: null,
                    'active' => true,
                ],
            );
        }

        $authorization->schedules()
            ->when($doseOrders !== [], fn ($query) => $query->whereNotIn('dose_order', $doseOrders))
            ->when($doseOrders === [], fn ($query) => $query)
            ->delete();
    }

    private function dailyFrequencyLabel(int $dailyDoseCount): string
    {
        return $dailyDoseCount === 1
            ? 'Una vez al día'
            : "{$dailyDoseCount} veces al día";
    }
}
