<?php

namespace App\Http\Controllers\Spaces;

use App\Http\Controllers\Controller;
use App\Models\DependencyReservation;
use App\Models\DependencyType;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SpaceStatisticsController extends Controller
{
    private const GRANULARITIES = ['day', 'week', 'month', 'first_half_year', 'second_half_year', 'year'];
    private const USAGE_STATUSES = [
        DependencyReservation::STATUS_APPROVED,
        DependencyReservation::STATUS_FINISHED,
    ];

    public function catalogs(): JsonResponse
    {
        $this->syncFinishedReservations();

        return response()->json([
            'dependencies' => MaintenanceDependency::query()
                ->reservableSpaces()
                ->orderBy('name')
                ->get(['id', 'name', 'dependency_type_id', 'calendar_color', 'capacity_max']),
            'dependency_types' => DependencyType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'staff' => Staff::query()
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'granularities' => [
                ['value' => 'day', 'label' => 'Diaria'],
                ['value' => 'week', 'label' => 'Semanal'],
                ['value' => 'month', 'label' => 'Mensual'],
                ['value' => 'first_half_year', 'label' => 'Primer semestre (ene-jun)'],
                ['value' => 'second_half_year', 'label' => 'Segundo semestre (jul-dic)'],
                ['value' => 'year', 'label' => 'Anual'],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->syncFinishedReservations();

        $validated = $request->validate([
            'granularity' => ['nullable', Rule::in(self::GRANULARITIES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'dependency_id' => ['nullable', 'integer'],
            'dependency_type_id' => ['nullable', 'integer'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->startOfDay()
            : now()->startOfMonth();
        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->endOfDay()
            : now()->endOfMonth();

        if ($dateTo->lt($dateFrom)) {
            return response()->json([
                'message' => 'La fecha final no puede ser anterior a la fecha inicial.',
                'errors' => [
                    'date_to' => ['La fecha final no puede ser anterior a la fecha inicial.'],
                ],
            ], 422);
        }

        $granularity = (string) ($validated['granularity'] ?? 'week');
        $dependencyId = $validated['dependency_id'] ?? null;
        $dependencyTypeId = $validated['dependency_type_id'] ?? null;
        $staffId = $validated['staff_id'] ?? null;

        $reservations = DependencyReservation::query()
            ->with([
                'dependency:id,name,dependency_type_id,calendar_color,capacity_max',
                'dependency.type:id,name,color',
                'staff:id,full_name',
            ])
            ->where('ends_at', '>=', $dateFrom)
            ->where('starts_at', '<=', $dateTo)
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($dependencyTypeId, fn ($query) => $query->whereHas('dependency', fn ($dependencyQuery) => $dependencyQuery->where('dependency_type_id', $dependencyTypeId)))
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->orderBy('starts_at')
            ->get();

        $usageReservations = $reservations->filter(
            fn (DependencyReservation $reservation) => in_array($reservation->status, self::USAGE_STATUSES, true)
        )->values();

        $totalUsageHours = $this->sumOverlapHours($usageReservations, $dateFrom, $dateTo);

        return response()->json([
            'filters' => [
                'granularity' => $granularity,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'dependency_id' => $dependencyId,
                'dependency_type_id' => $dependencyTypeId,
                'staff_id' => $staffId,
            ],
            'summary' => $this->buildSummary($reservations, $usageReservations, $dateFrom, $dateTo),
            'time_series' => $this->buildTimeSeries($usageReservations, $dateFrom, $dateTo, $granularity),
            'by_dependency' => $this->buildUsageBreakdown($usageReservations, $dateFrom, $dateTo, 'dependency', $totalUsageHours),
            'by_dependency_type' => $this->buildUsageBreakdown($usageReservations, $dateFrom, $dateTo, 'dependency_type', $totalUsageHours),
            'by_requester' => $this->buildUsageBreakdown($usageReservations, $dateFrom, $dateTo, 'requester', $totalUsageHours),
            'by_status' => $this->buildStatusBreakdown($reservations),
            'selected_dependency' => $dependencyId
                ? $this->buildSelectedDependencyDetail($reservations, $usageReservations, (int) $dependencyId, $dateFrom, $dateTo)
                : null,
        ]);
    }

    private function buildSummary(
        Collection $reservations,
        Collection $usageReservations,
        Carbon $dateFrom,
        Carbon $dateTo
    ): array {
        $usageHours = $this->sumOverlapHours($usageReservations, $dateFrom, $dateTo);
        $usageCount = $usageReservations->count();

        return [
            'total_reservations' => $reservations->count(),
            'total_usage_hours' => $usageHours,
            'approved_count' => $reservations->where('status', DependencyReservation::STATUS_APPROVED)->count(),
            'finished_count' => $reservations->where('status', DependencyReservation::STATUS_FINISHED)->count(),
            'pending_count' => $reservations->where('status', DependencyReservation::STATUS_PENDING)->count(),
            'rejected_count' => $reservations->where('status', DependencyReservation::STATUS_REJECTED)->count(),
            'cancelled_count' => $reservations->where('status', DependencyReservation::STATUS_CANCELLED)->count(),
            'active_spaces' => $usageReservations
                ->pluck('maintenance_dependency_id')
                ->filter()
                ->unique()
                ->count(),
            'active_requesters' => $usageReservations
                ->pluck('staff_id')
                ->filter()
                ->unique()
                ->count(),
            'average_duration_hours' => $usageCount > 0
                ? round($usageHours / $usageCount, 2)
                : 0,
        ];
    }

    private function buildTimeSeries(
        Collection $usageReservations,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $granularity
    ): array {
        $buckets = collect($this->initializeBuckets($dateFrom, $dateTo, $granularity))
            ->mapWithKeys(fn (array $bucket) => [
                $bucket['key'] => [
                    'key' => $bucket['key'],
                    'label' => $bucket['label'],
                    'hours_used' => 0,
                    'reservation_ids' => [],
                ],
            ]);

        foreach ($usageReservations as $reservation) {
            [$segmentStart, $segmentEnd] = $this->clipReservationToRange($reservation, $dateFrom, $dateTo);

            if (!$segmentStart || !$segmentEnd || !$segmentStart->lt($segmentEnd)) {
                continue;
            }

            $cursor = $segmentStart->copy();

            while ($cursor->lt($segmentEnd)) {
                $bucketKey = $this->bucketKey($cursor, $granularity);
                if (!$buckets->has($bucketKey)) {
                    break;
                }

                $bucketEnd = $this->bucketBoundary($cursor, $granularity);
                $currentSegmentEnd = $bucketEnd->lt($segmentEnd)
                    ? $bucketEnd
                    : $segmentEnd->copy();

                $minutes = $cursor->diffInMinutes($currentSegmentEnd);
                if ($minutes > 0) {
                    $bucket = $buckets->get($bucketKey);
                    $bucket['hours_used'] += round($minutes / 60, 2);
                    $bucket['reservation_ids'][$reservation->id] = true;
                    $buckets->put($bucketKey, $bucket);
                }

                $cursor = $currentSegmentEnd->copy();
            }
        }

        return $buckets
            ->map(fn (array $bucket) => [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'hours_used' => round($bucket['hours_used'], 2),
                'reservations_count' => count($bucket['reservation_ids']),
            ])
            ->values()
            ->all();
    }

    private function buildUsageBreakdown(
        Collection $usageReservations,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $mode,
        float $totalUsageHours
    ): array {
        $rows = $usageReservations
            ->groupBy(function (DependencyReservation $reservation) use ($mode) {
                return match ($mode) {
                    'dependency_type' => $reservation->dependency?->type?->id ?: 'none',
                    'requester' => $reservation->staff_id ?: 'none',
                    default => $reservation->maintenance_dependency_id ?: 'none',
                };
            })
            ->map(function (Collection $items) use ($dateFrom, $dateTo, $mode, $totalUsageHours) {
                /** @var DependencyReservation $first */
                $first = $items->first();
                $hoursUsed = $this->sumOverlapHours($items, $dateFrom, $dateTo);

                $label = match ($mode) {
                    'dependency_type' => $first->dependency?->type?->name ?: 'Sin tipo',
                    'requester' => $first->staff?->full_name ?: 'Sin solicitante',
                    default => $first->dependency?->name ?: 'Sin dependencia',
                };

                $secondary = match ($mode) {
                    'dependency_type' => null,
                    'requester' => $first->dependency?->type?->name,
                    default => $first->dependency?->type?->name,
                };

                return [
                    'label' => $label,
                    'secondary_label' => $secondary,
                    'reservations_count' => $items->count(),
                    'hours_used' => $hoursUsed,
                    'share_percent' => $totalUsageHours > 0 ? round(($hoursUsed / $totalUsageHours) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('hours_used')
            ->values()
            ->all();

        return $rows;
    }

    private function buildStatusBreakdown(Collection $reservations): array
    {
        return collect([
            DependencyReservation::STATUS_PENDING,
            DependencyReservation::STATUS_APPROVED,
            DependencyReservation::STATUS_FINISHED,
            DependencyReservation::STATUS_REJECTED,
            DependencyReservation::STATUS_CANCELLED,
        ])
            ->map(fn (string $status) => [
                'status' => $status,
                'label' => ucfirst($status),
                'count' => $reservations->where('status', $status)->count(),
            ])
            ->values()
            ->all();
    }

    private function buildSelectedDependencyDetail(
        Collection $reservations,
        Collection $usageReservations,
        int $dependencyId,
        Carbon $dateFrom,
        Carbon $dateTo
    ): ?array {
        $selectedReservations = $reservations
            ->where('maintenance_dependency_id', $dependencyId)
            ->values();

        if ($selectedReservations->isEmpty()) {
            $dependency = MaintenanceDependency::query()
                ->reservableSpaces()
                ->find($dependencyId);

            return $dependency ? [
                'name' => $dependency->name,
                'type_name' => $dependency->type?->name,
                'capacity_max' => $dependency->capacity_max,
                'total_reservations' => 0,
                'hours_used' => 0,
                'average_duration_hours' => 0,
                'top_requester' => null,
                'status_breakdown' => [],
            ] : null;
        }

        /** @var DependencyReservation $first */
        $first = $selectedReservations->first();
        $selectedUsage = $usageReservations
            ->where('maintenance_dependency_id', $dependencyId)
            ->values();
        $hoursUsed = $this->sumOverlapHours($selectedUsage, $dateFrom, $dateTo);
        $topRequester = $selectedUsage
            ->groupBy('staff_id')
            ->map(fn (Collection $items) => [
                'name' => $items->first()?->staff?->full_name ?: 'Sin solicitante',
                'reservations_count' => $items->count(),
            ])
            ->sortByDesc('reservations_count')
            ->values()
            ->first();

        return [
            'name' => $first->dependency?->name,
            'type_name' => $first->dependency?->type?->name,
            'capacity_max' => $first->dependency?->capacity_max,
            'total_reservations' => $selectedReservations->count(),
            'hours_used' => $hoursUsed,
            'average_duration_hours' => $selectedUsage->count() > 0
                ? round($hoursUsed / $selectedUsage->count(), 2)
                : 0,
            'top_requester' => $topRequester,
            'status_breakdown' => $this->buildStatusBreakdown($selectedReservations),
        ];
    }

    private function initializeBuckets(Carbon $dateFrom, Carbon $dateTo, string $granularity): array
    {
        $buckets = [];
        $cursor = $this->bucketStart($dateFrom, $granularity);
        $last = $this->bucketStart($dateTo, $granularity);

        while ($cursor->lte($last)) {
            $buckets[] = [
                'key' => $this->bucketKey($cursor, $granularity),
                'label' => $this->bucketLabel($cursor, $granularity),
            ];

            $cursor = $this->bucketBoundary($cursor, $granularity);
        }

        return $buckets;
    }

    private function bucketStart(Carbon $date, string $granularity): Carbon
    {
        return match ($granularity) {
            'day' => $date->copy()->startOfDay(),
            'month' => $date->copy()->startOfMonth(),
            'first_half_year' => $date->copy()->startOfYear(),
            'second_half_year' => $date->copy()->month(7)->startOfMonth(),
            'year' => $date->copy()->startOfYear(),
            default => $date->copy()->startOfWeek(Carbon::MONDAY),
        };
    }

    private function bucketBoundary(Carbon $date, string $granularity): Carbon
    {
        return match ($granularity) {
            'day' => $date->copy()->startOfDay()->addDay(),
            'month' => $date->copy()->startOfMonth()->addMonth(),
            'first_half_year' => $date->copy()->startOfYear()->addMonths(6),
            'second_half_year' => $date->copy()->month(7)->startOfMonth()->addMonths(6),
            'year' => $date->copy()->startOfYear()->addYear(),
            default => $date->copy()->startOfWeek(Carbon::MONDAY)->addWeek(),
        };
    }

    private function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => $date->copy()->startOfDay()->format('Y-m-d'),
            'month' => $date->copy()->startOfMonth()->format('Y-m'),
            'first_half_year' => sprintf('%s-S1', $date->year),
            'second_half_year' => sprintf('%s-S2', $date->year),
            'year' => $date->copy()->startOfYear()->format('Y'),
            default => $date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
        };
    }

    private function bucketLabel(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => $date->format('d-m-Y'),
            'month' => $date->format('m-Y'),
            'first_half_year' => $this->firstHalfYearLabel($date),
            'second_half_year' => $this->secondHalfYearLabel($date),
            'year' => $date->copy()->startOfYear()->format('Y'),
            default => sprintf(
                '%s al %s',
                $date->copy()->startOfWeek(Carbon::MONDAY)->format('d-m-Y'),
                $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->format('d-m-Y')
            ),
        };
    }

    private function firstHalfYearLabel(Carbon $date): string
    {
        $year = $date->year;

        return "1er semestre {$year} (01-01-{$year} al 30-06-{$year})";
    }

    private function secondHalfYearLabel(Carbon $date): string
    {
        $year = $date->year;

        return "2do semestre {$year} (01-07-{$year} al 31-12-{$year})";
    }

    private function sumOverlapHours(Collection $reservations, Carbon $dateFrom, Carbon $dateTo): float
    {
        return round(
            $reservations->sum(fn (DependencyReservation $reservation) => $this->overlapMinutes($reservation, $dateFrom, $dateTo) / 60),
            2
        );
    }

    private function overlapMinutes(DependencyReservation $reservation, Carbon $dateFrom, Carbon $dateTo): int
    {
        [$segmentStart, $segmentEnd] = $this->clipReservationToRange($reservation, $dateFrom, $dateTo);

        if (!$segmentStart || !$segmentEnd || !$segmentStart->lt($segmentEnd)) {
            return 0;
        }

        return $segmentStart->diffInMinutes($segmentEnd);
    }

    private function clipReservationToRange(
        DependencyReservation $reservation,
        Carbon $dateFrom,
        Carbon $dateTo
    ): array {
        $reservationStart = $reservation->starts_at instanceof Carbon
            ? $reservation->starts_at->copy()
            : Carbon::parse((string) $reservation->starts_at);
        $reservationEnd = $reservation->ends_at instanceof Carbon
            ? $reservation->ends_at->copy()
            : Carbon::parse((string) $reservation->ends_at);

        $segmentStart = $reservationStart->lt($dateFrom)
            ? $dateFrom->copy()
            : $reservationStart;
        $segmentEnd = $reservationEnd->gt($dateTo)
            ? $dateTo->copy()
            : $reservationEnd;

        return [$segmentStart, $segmentEnd];
    }

    private function syncFinishedReservations(): void
    {
        DependencyReservation::query()
            ->where('status', DependencyReservation::STATUS_APPROVED)
            ->where('ends_at', '<', now())
            ->update(['status' => DependencyReservation::STATUS_FINISHED]);
    }
}
