<?php

namespace App\Services\Maintenance;

use App\Models\Attendance\SchoolDay;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MaintenanceVisitPlanningService
{
    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public function preview(array $configuration): array
    {
        $dependencies = $this->dependencies($configuration['dependency_ids'] ?? []);
        $responsible = $this->staff()->firstWhere('id', (int) $configuration['responsible_staff_id']);

        $start = CarbonImmutable::parse($configuration['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($configuration['end_date'])->startOfDay();
        $excludedDates = $this->excludedSchoolDates($start, $end, (bool) ($configuration['exclude_non_school_days'] ?? true));
        $candidateDates = $this->candidateDates($configuration, $start, $end, $excludedDates);
        $startTime = CarbonImmutable::createFromFormat('H:i', (string) $configuration['start_time']);
        $slotMinutes = (int) $configuration['slot_minutes'];
        $maxPerDay = (int) $configuration['max_visits_per_day'];
        $visitsPerDependency = (int) $configuration['visits_per_dependency'];

        $existingVisits = $this->existingVisits(
            $start,
            $end,
            $dependencies->pluck('id')->all(),
            [(int) $responsible->id],
            [(string) $responsible->full_name]
        );

        $occupiedDependencies = [];
        $occupiedResponsibleSlots = [];
        foreach ($existingVisits as $visit) {
            $date = CarbonImmutable::parse($visit->visit_date)->toDateString();
            $occupiedDependencies[$this->dependencyDateKey((int) $visit->maintenance_dependency_id, $date)] = true;

            if ($visit->visit_time && (
                (int) $visit->responsible_staff_id === (int) $responsible->id
                || $visit->responsible === $responsible->full_name
            )) {
                $occupiedResponsibleSlots[$this->staffSlotKey(
                    (int) $responsible->id,
                    $date,
                    $this->normalizeTime($visit->visit_time)
                )] = true;
            }
        }

        $jobs = collect();
        for ($round = 0; $round < $visitsPerDependency; $round++) {
            foreach ($dependencies as $dependency) {
                $jobs->push($dependency);
            }
        }

        $rows = [];
        $generatedPerDay = [];
        $dateCursor = 0;
        $conflictsAvoided = 0;

        foreach ($jobs as $jobIndex => $dependency) {
            $scheduled = false;
            $candidateCount = count($candidateDates);

            for ($dateOffset = 0; $dateOffset < $candidateCount; $dateOffset++) {
                $date = $candidateDates[($dateCursor + $dateOffset) % max(1, $candidateCount)];
                $dateString = $date->toDateString();

                if (($generatedPerDay[$dateString] ?? 0) >= $maxPerDay) {
                    continue;
                }

                $dependencyKey = $this->dependencyDateKey((int) $dependency->id, $dateString);
                if (isset($occupiedDependencies[$dependencyKey])) {
                    $conflictsAvoided++;

                    continue;
                }

                for ($slot = 0; $slot < $maxPerDay; $slot++) {
                    $visitTime = $startTime->addMinutes($slot * $slotMinutes);
                    if ($visitTime->day !== $startTime->day) {
                        break;
                    }

                    $timeString = $visitTime->format('H:i');
                    $staffSlotKey = $this->staffSlotKey((int) $responsible->id, $dateString, $timeString);
                    if (isset($occupiedResponsibleSlots[$staffSlotKey])) {
                        $conflictsAvoided++;

                        continue;
                    }

                    $rows[] = [
                        'row_key' => sprintf('proposal-%04d', $jobIndex + 1),
                        'included' => true,
                        'maintenance_dependency_id' => (int) $dependency->id,
                        'dependency' => $this->dependencyPayload($dependency),
                        'responsible_staff_id' => (int) $responsible->id,
                        'responsible' => (string) $responsible->full_name,
                        'visit_date' => $dateString,
                        'visit_time' => $timeString,
                        'visit_type' => (string) $configuration['visit_type'],
                        'notes' => $configuration['notes'] ?? null,
                    ];

                    $generatedPerDay[$dateString] = ($generatedPerDay[$dateString] ?? 0) + 1;
                    $occupiedDependencies[$dependencyKey] = true;
                    $occupiedResponsibleSlots[$staffSlotKey] = true;
                    $dateCursor = ($dateCursor + $dateOffset + 1) % max(1, $candidateCount);
                    $scheduled = true;
                    break 2;
                }
            }

            if (! $scheduled && $candidateCount === 0) {
                break;
            }
        }

        $unscheduled = max(0, $jobs->count() - count($rows));
        $warnings = [];
        if ($candidateDates === []) {
            $warnings[] = 'No existen fechas disponibles con las reglas seleccionadas.';
        }
        if ($unscheduled > 0) {
            $warnings[] = "{$unscheduled} visitas no cupieron en el período. Amplía las fechas, días habilitados o el máximo diario.";
        }
        if ($excludedDates->isEmpty() && ($configuration['exclude_non_school_days'] ?? true)) {
            $warnings[] = 'No hay días no lectivos confirmados en el calendario escolar para este período.';
        }

        return [
            'rows' => $rows,
            'summary' => [
                'proposed' => count($rows),
                'requested' => $jobs->count(),
                'unscheduled' => $unscheduled,
                'dependencies' => $dependencies->count(),
                'scheduled_days' => count($generatedPerDay),
                'available_dates' => count($candidateDates),
                'conflicts_avoided' => $conflictsAvoided,
                'excluded_school_dates' => $excludedDates->count(),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{index: int, message: string}>
     */
    public function conflictsForRows(array $rows, bool $lockForUpdate = false): array
    {
        if ($rows === []) {
            return [];
        }

        $dates = collect($rows)->pluck('visit_date')->filter()->sort()->values();
        $dependencyIds = collect($rows)->pluck('maintenance_dependency_id')->map(fn ($id) => (int) $id)->unique()->values();
        $staffIds = collect($rows)->pluck('responsible_staff_id')->map(fn ($id) => (int) $id)->unique()->values();
        $responsibleNames = collect($rows)->pluck('responsible')->filter()->unique()->values();

        $query = MaintenanceVisit::query()
            ->where('status', '!=', 'Cancelada')
            ->whereBetween('visit_date', [$dates->first(), $dates->last()])
            ->where(function ($query) use ($dependencyIds, $staffIds, $responsibleNames) {
                $query->whereIn('maintenance_dependency_id', $dependencyIds)
                    ->orWhereIn('responsible_staff_id', $staffIds)
                    ->orWhereIn('responsible', $responsibleNames);
            })
            ->select([
                'id', 'maintenance_dependency_id', 'responsible_staff_id', 'responsible',
                'visit_date', 'visit_time', 'status',
            ]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $existing = $query->get();
        $dependencyDates = [];
        $staffSlots = [];

        foreach ($existing as $visit) {
            $date = CarbonImmutable::parse($visit->visit_date)->toDateString();
            $dependencyDates[$this->dependencyDateKey((int) $visit->maintenance_dependency_id, $date)] = true;
            if ($visit->visit_time) {
                $time = $this->normalizeTime($visit->visit_time);
                if ($visit->responsible_staff_id) {
                    $staffSlots[$this->staffSlotKey((int) $visit->responsible_staff_id, $date, $time)] = true;
                }
                $staffSlots[$this->nameSlotKey((string) $visit->responsible, $date, $time)] = true;
            }
        }

        $conflicts = [];
        foreach ($rows as $index => $row) {
            $dependencyKey = $this->dependencyDateKey((int) $row['maintenance_dependency_id'], $row['visit_date']);
            $staffKey = $this->staffSlotKey((int) $row['responsible_staff_id'], $row['visit_date'], $row['visit_time']);
            $nameKey = $this->nameSlotKey((string) $row['responsible'], $row['visit_date'], $row['visit_time']);

            if (isset($dependencyDates[$dependencyKey])) {
                $conflicts[] = ['index' => $index, 'message' => 'La dependencia ya tiene una visita ese día.'];

                continue;
            }
            if (isset($staffSlots[$staffKey]) || isset($staffSlots[$nameKey])) {
                $conflicts[] = ['index' => $index, 'message' => 'El funcionario ya tiene una visita en ese horario.'];

                continue;
            }

            $dependencyDates[$dependencyKey] = true;
            $staffSlots[$staffKey] = true;
            $staffSlots[$nameKey] = true;
        }

        return $conflicts;
    }

    /** @return Collection<int, MaintenanceDependency> */
    public function dependencies(array $ids = []): Collection
    {
        return MaintenanceDependency::query()
            ->maintenanceLocations()
            ->where('active', true)
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone']);
    }

    /** @return Collection<int, Staff> */
    public function staff(): Collection
    {
        return Staff::query()
            ->where('active', true)
            ->where('can_receive_maintenance_orders', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }

    /** @return array<int, CarbonImmutable> */
    private function candidateDates(array $configuration, CarbonImmutable $start, CarbonImmutable $end, Collection $excludedDates): array
    {
        $frequency = (string) $configuration['frequency'];
        $interval = (int) $configuration['interval_value'];
        $weekdays = collect($configuration['weekdays'] ?? [1, 2, 3, 4, 5])->map(fn ($day) => (int) $day);
        $excludeWeekends = (bool) ($configuration['exclude_weekends'] ?? true);
        $excludedLookup = $excludedDates->flip();
        $dates = [];

        if ($frequency === 'monthly') {
            $month = $start->startOfMonth();
            $monthOffset = 0;
            while ($month->lte($end->startOfMonth())) {
                if ($monthOffset % $interval === 0) {
                    $day = min((int) $configuration['month_day'], $month->daysInMonth);
                    $candidate = $month->day($day);
                    for ($adjustment = 0; $adjustment < 7 && $candidate->month === $month->month; $adjustment++) {
                        if ($candidate->betweenIncluded($start, $end)
                            && $this->isEligibleDate($candidate, $weekdays, $excludeWeekends, $excludedLookup, false)) {
                            $dates[] = $candidate;
                            break;
                        }
                        $candidate = $candidate->addDay();
                    }
                }
                $month = $month->addMonth();
                $monthOffset++;
            }

            return $dates;
        }

        $effectiveWeekInterval = $frequency === 'biweekly' ? $interval * 2 : $interval;
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $frequencyMatch = match ($frequency) {
                'daily' => $start->diffInDays($date) % $interval === 0,
                'weekly', 'biweekly' => intdiv($start->startOfWeek()->diffInDays($date->startOfWeek()), 7) % $effectiveWeekInterval === 0,
                default => false,
            };

            if ($frequencyMatch && $this->isEligibleDate($date, $weekdays, $excludeWeekends, $excludedLookup, true)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function isEligibleDate(
        CarbonImmutable $date,
        Collection $weekdays,
        bool $excludeWeekends,
        Collection $excludedLookup,
        bool $enforceWeekdays
    ): bool {
        if ($excludeWeekends && $date->isWeekend()) {
            return false;
        }
        if ($enforceWeekdays && $weekdays->isNotEmpty() && ! $weekdays->contains($date->isoWeekday())) {
            return false;
        }

        return ! $excludedLookup->has($date->toDateString());
    }

    private function excludedSchoolDates(CarbonImmutable $start, CarbonImmutable $end, bool $enabled): Collection
    {
        if (! $enabled) {
            return collect();
        }

        return SchoolDay::query()
            ->where('status', 'confirmed')
            ->where('is_school_day', false)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();
    }

    private function existingVisits(
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $dependencyIds,
        array $staffIds,
        array $responsibleNames
    ): Collection {
        return MaintenanceVisit::query()
            ->where('status', '!=', 'Cancelada')
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query) use ($dependencyIds, $staffIds, $responsibleNames) {
                $query->whereIn('maintenance_dependency_id', $dependencyIds)
                    ->orWhereIn('responsible_staff_id', $staffIds)
                    ->orWhereIn('responsible', $responsibleNames);
            })
            ->get([
                'maintenance_dependency_id', 'responsible_staff_id', 'responsible',
                'visit_date', 'visit_time',
            ]);
    }

    private function dependencyPayload(MaintenanceDependency $dependency): array
    {
        return [
            'id' => (int) $dependency->id,
            'code' => (string) $dependency->code,
            'name' => (string) $dependency->name,
            'distribution' => $dependency->distribution,
            'sector' => $dependency->sector,
            'zone' => $dependency->zone,
        ];
    }

    private function dependencyDateKey(int $dependencyId, string $date): string
    {
        return $dependencyId.'|'.$date;
    }

    private function staffSlotKey(int $staffId, string $date, string $time): string
    {
        return $staffId.'|'.$date.'|'.$time;
    }

    private function nameSlotKey(string $name, string $date, string $time): string
    {
        return mb_strtolower(trim($name)).'|'.$date.'|'.$time;
    }

    private function normalizeTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }
}
