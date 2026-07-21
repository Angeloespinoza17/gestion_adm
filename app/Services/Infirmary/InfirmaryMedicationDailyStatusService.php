<?php

namespace App\Services\Infirmary;

use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class InfirmaryMedicationDailyStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function forAuthorization(
        InfirmaryMedicationAuthorization $authorization,
        ?CarbonInterface $date = null,
    ): array {
        $now = $date
            ? Carbon::instance($date)->copy()
            : now(config('app.timezone'));
        $day = $now->copy()->startOfDay();

        if ($authorization->regimen_type === InfirmaryMedicationAuthorization::REGIMEN_SOS) {
            return $this->notApplicableStatus($day, 'sos', 'S.O.S.', 'No genera dosis diaria pendiente.');
        }

        if ($day->isWeekend()) {
            return $this->notApplicableStatus($day, 'weekend', 'Fin de semana', 'La rutina se controla de lunes a viernes.');
        }

        if (! $this->isActiveOn($authorization, $day)) {
            return $this->notApplicableStatus($day, 'inactive', 'Fuera de vigencia', 'La rutina no está activa en esta fecha.');
        }

        $schedules = $this->schedulesFor($authorization);
        $expectedCount = max(1, $schedules->count() ?: (int) ($authorization->daily_dose_count ?: 1));

        if ($schedules->isEmpty()) {
            $schedules = collect(range(1, $expectedCount))->map(fn (int $doseOrder) => (object) [
                'id' => null,
                'dose_order' => $doseOrder,
                'scheduled_time' => null,
            ]);
        }

        $administrations = $this->administrationsFor($authorization, $day);
        $assignedAdministrationIds = [];
        $slots = [];

        foreach ($schedules as $schedule) {
            $administration = $administrations->first(function (InfirmaryMedicationAdministration $item) use ($schedule) {
                return $schedule->id && (int) $item->schedule_id === (int) $schedule->id;
            });

            if ($administration) {
                $assignedAdministrationIds[] = $administration->id;
            }

            $slots[] = $this->slotPayload($schedule, $administration, $day, $now);
        }

        $legacyAdministrations = $administrations
            ->reject(fn (InfirmaryMedicationAdministration $item) => in_array($item->id, $assignedAdministrationIds, true))
            ->values();

        foreach ($slots as $index => $slot) {
            if ($slot['registered'] || ! isset($legacyAdministrations[0])) {
                continue;
            }

            $administration = $legacyAdministrations->shift();
            $slots[$index] = $this->slotPayload($schedules->get($index), $administration, $day, $now);
        }

        $registeredCount = collect($slots)->where('registered', true)->count();
        $administeredCount = collect($slots)
            ->where('administration_status', InfirmaryMedicationAdministration::STATUS_ADMINISTRADA)
            ->count();
        $notAdministeredCount = collect($slots)
            ->where('administration_status', InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA)
            ->count();
        $pendingCount = max(0, $expectedCount - $registeredCount);
        $overdueCount = collect($slots)->where('overdue', true)->count();
        $nextPending = collect($slots)->firstWhere('registered', false);

        if ($pendingCount === 0 && $notAdministeredCount > 0) {
            $state = 'exception';
            $label = "Con incidencia {$registeredCount}/{$expectedCount}";
            $variant = 'danger';
        } elseif ($pendingCount === 0) {
            $state = 'completed';
            $label = "Completa {$registeredCount}/{$expectedCount}";
            $variant = 'success';
        } elseif ($registeredCount > 0) {
            $state = 'partial';
            $label = "Parcial {$registeredCount}/{$expectedCount}";
            $variant = 'warning';
        } else {
            $state = 'pending';
            $label = "Pendiente 0/{$expectedCount}";
            $variant = 'warning';
        }

        $detail = match (true) {
            $overdueCount > 0 => $overdueCount === 1 ? '1 dosis atrasada' : "{$overdueCount} dosis atrasadas",
            $pendingCount > 0 && ! empty($nextPending['scheduled_time']) => "Próxima: {$nextPending['scheduled_time']}",
            $pendingCount > 0 => $pendingCount === 1 ? 'Falta registrar 1 dosis' : "Faltan registrar {$pendingCount} dosis",
            $notAdministeredCount > 0 => $notAdministeredCount === 1 ? '1 dosis no administrada' : "{$notAdministeredCount} dosis no administradas",
            default => 'Todas las dosis del día fueron administradas.',
        };

        return [
            'date' => $day->toDateString(),
            'applicable' => true,
            'state' => $state,
            'label' => $label,
            'variant' => $variant,
            'detail' => $detail,
            'expected_count' => $expectedCount,
            'registered_count' => $registeredCount,
            'administered_count' => $administeredCount,
            'not_administered_count' => $notAdministeredCount,
            'pending_count' => $pendingCount,
            'overdue_count' => $overdueCount,
            'next_pending_schedule_id' => $nextPending['schedule_id'] ?? null,
            'slots' => $slots,
        ];
    }

    public function matchesFilter(array $dailyStatus, ?string $filter): bool
    {
        return match ($filter) {
            'pending' => in_array($dailyStatus['state'] ?? null, ['pending', 'partial'], true),
            'completed' => ($dailyStatus['state'] ?? null) === 'completed',
            'exception' => ($dailyStatus['state'] ?? null) === 'exception',
            'not_applicable' => ! ($dailyStatus['applicable'] ?? false),
            default => true,
        };
    }

    private function isActiveOn(InfirmaryMedicationAuthorization $authorization, CarbonInterface $day): bool
    {
        if (! in_array($authorization->status, [
            InfirmaryMedicationAuthorization::STATUS_VIGENTE,
            InfirmaryMedicationAuthorization::STATUS_PROXIMA_A_VENCER,
        ], true)) {
            return false;
        }

        if ($authorization->start_date && $authorization->start_date->startOfDay()->greaterThan($day)) {
            return false;
        }

        return ! $authorization->end_date || ! $authorization->end_date->startOfDay()->lessThan($day);
    }

    private function schedulesFor(InfirmaryMedicationAuthorization $authorization): Collection
    {
        $schedules = $authorization->relationLoaded('schedules')
            ? $authorization->schedules
            : $authorization->schedules()->get();

        return $schedules
            ->where('active', true)
            ->sortBy('dose_order')
            ->values();
    }

    private function administrationsFor(
        InfirmaryMedicationAuthorization $authorization,
        CarbonInterface $day,
    ): Collection {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        if ($authorization->relationLoaded('administrations')) {
            return $authorization->administrations
                ->filter(function (InfirmaryMedicationAdministration $item) use ($day, $start, $end) {
                    if ($item->scheduled_for_date) {
                        return $item->scheduled_for_date->isSameDay($day);
                    }

                    return $item->administered_at?->betweenIncluded($start, $end) ?? false;
                })
                ->sortBy('administered_at')
                ->values();
        }

        return $authorization->administrations()
            ->where(function ($query) use ($day, $start, $end) {
                $query
                    ->whereDate('scheduled_for_date', $day->toDateString())
                    ->orWhere(function ($legacy) use ($start, $end) {
                        $legacy
                            ->whereNull('scheduled_for_date')
                            ->whereBetween('administered_at', [$start, $end]);
                    });
            })
            ->oldest('administered_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function slotPayload(
        object $schedule,
        ?InfirmaryMedicationAdministration $administration,
        CarbonInterface $day,
        CarbonInterface $now,
    ): array {
        $time = $schedule->scheduled_time
            ? substr((string) $schedule->scheduled_time, 0, 5)
            : null;
        $registered = $administration !== null;
        $overdue = false;

        if (! $registered && $time) {
            $scheduledAt = Carbon::parse($day->toDateString().' '.$time, $now->timezone);
            $overdue = $now->greaterThan($scheduledAt);
        }

        return [
            'schedule_id' => $schedule->id,
            'dose_order' => (int) $schedule->dose_order,
            'scheduled_time' => $time,
            'label' => $time ? "Dosis {$schedule->dose_order} · {$time}" : "Dosis {$schedule->dose_order}",
            'registered' => $registered,
            'overdue' => $overdue,
            'administration_id' => $administration?->id,
            'administration_status' => $administration?->administration_status,
            'administered_at' => $administration?->administered_at?->toIso8601String(),
            'non_administration_reason' => $administration?->non_administration_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notApplicableStatus(
        CarbonInterface $day,
        string $state,
        string $label,
        string $detail,
    ): array {
        return [
            'date' => $day->toDateString(),
            'applicable' => false,
            'state' => $state,
            'label' => $label,
            'variant' => 'secondary',
            'detail' => $detail,
            'expected_count' => 0,
            'registered_count' => 0,
            'administered_count' => 0,
            'not_administered_count' => 0,
            'pending_count' => 0,
            'overdue_count' => 0,
            'next_pending_schedule_id' => null,
            'slots' => [],
        ];
    }
}
