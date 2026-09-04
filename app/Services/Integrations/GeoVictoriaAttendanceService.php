<?php

namespace App\Services\Integrations;

use App\Exceptions\GeoVictoriaException;
use App\Models\Staff;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeoVictoriaAttendanceService
{
    public function __construct(
        private readonly GeoVictoriaClient $client,
    ) {}

    /** @param array<string, mixed> $filters */
    public function usersCatalog(array $filters = []): array
    {
        if (! empty($filters['refresh'])) {
            $this->clearUsersCache();
        }

        $catalog = $this->catalog();
        $users = collect($catalog['users']);
        $search = Str::lower(trim((string) ($filters['search'] ?? '')));
        $status = (string) ($filters['status'] ?? '');

        if ($search !== '') {
            $users = $users->filter(function (array $user) use ($search): bool {
                return Str::contains(Str::lower(implode(' ', Arr::only($user, [
                    'rut', 'name', 'email', 'group', 'position', 'status',
                ]))), $search);
            });
        }

        if ($status !== '') {
            $enabled = $status === 'active';
            $users = $users->where('enabled', $enabled);
        }

        return [
            'data' => $users
                ->map(fn (array $user): array => Arr::except($user, ['provider_identifier']))
                ->values()
                ->all(),
            'summary' => $catalog['summary'],
            'fetched_at' => $catalog['fetched_at'],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'matched' => $users->count(),
            ],
        ];
    }

    /** @param array<string, mixed> $query */
    public function attendance(array $query): array
    {
        $staffIds = array_values(array_unique(array_map('intval', $query['staff_ids'])));
        $knownStaff = collect($this->catalog()['users'])->keyBy('id');
        $selectedStaff = collect($staffIds)
            ->map(fn (int $staffId): ?array => $knownStaff->get($staffId))
            ->filter();
        $unknown = array_values(array_diff($staffIds, $selectedStaff->pluck('id')->all()));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'staff_ids' => 'Uno o más funcionarios ya no están disponibles en el sistema. Actualiza el catálogo.',
            ]);
        }

        if ($selectedStaff->contains(fn (array $staff): bool => ! $staff['geovictoria_linked'])) {
            throw ValidationException::withMessages([
                'staff_ids' => 'Uno o más funcionarios no tienen una coincidencia exacta de RUT en GeoVictoria.',
            ]);
        }

        $providerIds = $selectedStaff->pluck('provider_identifier')->filter()->unique()->values()->all();
        $staffByRut = $selectedStaff->keyBy(fn (array $staff): string => $this->normalizeRut($staff['rut']));

        $start = Carbon::createFromFormat('Y-m-d', (string) $query['date_from'], config('app.timezone'))->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', (string) $query['date_to'], config('app.timezone'))->endOfDay();
        $payload = $this->client->attendanceBook($start->format('YmdHis'), $end->format('YmdHis'), $providerIds);

        return $this->normalizeAttendanceBook($payload, $staffIds, $staffByRut, $start, $end);
    }

    /** @param array<string, mixed> $query */
    public function reports(array $query): array
    {
        $knownStaff = collect($this->catalog()['users'])->keyBy('id');

        if (($query['scope'] ?? '') === 'selected') {
            $staffIds = array_values(array_unique(array_map('intval', $query['staff_ids'] ?? [])));
            $selectedStaff = collect($staffIds)
                ->map(fn (int $staffId): ?array => $knownStaff->get($staffId))
                ->filter();
            $unknown = array_values(array_diff($staffIds, $selectedStaff->pluck('id')->all()));

            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'staff_ids' => 'Uno o más funcionarios ya no están disponibles en el sistema. Actualiza el catálogo.',
                ]);
            }

            if ($selectedStaff->contains(fn (array $staff): bool => ! $staff['geovictoria_linked'])) {
                throw ValidationException::withMessages([
                    'staff_ids' => 'Uno o más funcionarios no tienen una coincidencia exacta de RUT en GeoVictoria.',
                ]);
            }
        } else {
            $selectedStaff = $knownStaff
                ->filter(fn (array $staff): bool => $staff['geovictoria_linked'])
                ->values();
            $staffIds = $selectedStaff->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        }

        if ($selectedStaff->isEmpty()) {
            throw ValidationException::withMessages([
                'staff_ids' => 'No hay funcionarios vinculados disponibles para generar el reporte.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d', (string) $query['date_from'], config('app.timezone'))->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', (string) $query['date_to'], config('app.timezone'))->endOfDay();
        $batchSize = min(50, max(10, (int) config('services.geovictoria.report_batch_size', 40)));
        $rows = collect();
        $people = collect();
        $batches = 0;

        foreach ($selectedStaff->chunk($batchSize) as $batch) {
            $providerIds = $batch->pluck('provider_identifier')->filter()->unique()->values()->all();
            $staffByRut = $batch->keyBy(fn (array $staff): string => $this->normalizeRut($staff['rut']));
            $payload = $this->client->attendanceBook(
                $start->format('YmdHis'),
                $end->format('YmdHis'),
                $providerIds,
            );
            $normalized = $this->normalizeAttendanceBook(
                $payload,
                $batch->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                $staffByRut,
                $start,
                $end,
            );

            $rows->push(...$normalized['data']);
            $people->push(...$normalized['people']);
            $batches++;
        }

        $rows = $rows->sortBy([['date', 'desc'], ['user.name', 'asc']])->values();
        $people = $people->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $this->buildReports(
            $rows,
            $people,
            count($staffIds),
            $start,
            $end,
            max(0, (int) ($query['tolerance_minutes'] ?? 0)),
            (string) $query['scope'],
            $batches,
        );
    }

    public function clearUsersCache(): void
    {
        Cache::forget($this->usersCacheKey());
    }

    /** @return array{users: array<int, array<string, mixed>>, summary: array<string, mixed>, fetched_at: string} */
    private function catalog(): array
    {
        $providerAvailable = true;
        $providerStatus = null;

        try {
            $providerUsers = collect(Cache::remember(
                $this->usersCacheKey(),
                now()->addMinutes(max(1, (int) config('services.geovictoria.users_cache_minutes', 10))),
                fn (): array => collect($this->client->users())
                    ->map(fn (array $user): array => $this->normalizeUser($user))
                    ->filter(fn (array $user): bool => $user['identifier'] !== '' && $user['reportable'])
                    ->values()
                    ->all(),
            ));
        } catch (GeoVictoriaException $exception) {
            $providerUsers = collect();
            $providerAvailable = false;
            $providerStatus = $exception->providerStatus;
        }

        $providerByRut = $providerUsers
            ->groupBy(fn (array $user): string => $this->normalizeRut($user['identifier']))
            ->filter(fn (Collection $matches, mixed $rut): bool => (string) $rut !== '' && $matches->count() === 1)
            ->map(fn (Collection $matches): array => $matches->first());

        $staff = Staff::query()
            ->select([
                'id', 'full_name', 'rut', 'institutional_email', 'personal_email',
                'cargo_id', 'status', 'active',
            ])
            ->with([
                'cargo:id,name',
                'departments:id,name',
            ])
            ->get()
            ->map(function (Staff $staff) use ($providerByRut): array {
                $provider = $providerByRut->get($this->normalizeRut($staff->rut));
                $departments = $staff->departments->pluck('name')->filter()->unique()->values();

                return [
                    'id' => $staff->id,
                    'identifier' => (string) $staff->rut,
                    'rut' => (string) $staff->rut,
                    'name' => (string) $staff->full_name,
                    'email' => (string) ($staff->institutional_email ?: $staff->personal_email),
                    'group' => $departments->implode(', '),
                    'position' => (string) ($staff->cargo?->name ?? ''),
                    'status' => (string) ($staff->status ?: ($staff->active ? 'activo' : 'inactivo')),
                    'enabled' => (bool) $staff->active,
                    'geovictoria_linked' => $provider !== null,
                    'provider_enabled' => $provider !== null ? (bool) $provider['enabled'] : false,
                    'provider_identifier' => $provider['identifier'] ?? null,
                ];
            })
            ->sortBy([
                ['enabled', 'desc'],
                ['name', 'asc'],
            ], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $localRuts = $staff->pluck('rut')
            ->map(fn (string $rut): string => $this->normalizeRut($rut))
            ->filter()
            ->unique();
        $providerRuts = $providerUsers->pluck('identifier')
            ->map(fn (string $rut): string => $this->normalizeRut($rut))
            ->filter()
            ->unique();

        return [
            'users' => $staff->all(),
            'summary' => [
                'total' => $staff->count(),
                'active' => $staff->where('enabled', true)->count(),
                'inactive' => $staff->where('enabled', false)->count(),
                'linked' => $staff->where('geovictoria_linked', true)->count(),
                'unlinked' => $staff->where('geovictoria_linked', false)->count(),
                'provider_total' => $providerRuts->count(),
                'provider_only' => $providerRuts->diff($localRuts)->count(),
                'provider_available' => $providerAvailable,
                'provider_status' => $providerStatus,
            ],
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeUser(array $user): array
    {
        $firstName = trim((string) $this->value($user, ['Name', 'name']));
        $lastName = trim((string) $this->value($user, ['LastName', 'lastName', 'last_name']));

        return [
            'identifier' => trim((string) $this->value($user, ['Identifier', 'identifier'])),
            'name' => trim($firstName.' '.$lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => trim((string) $this->value($user, ['Email', 'email'])),
            'group' => trim((string) $this->value($user, ['GroupDescription', 'groupDescription', 'group'])),
            'position' => trim((string) $this->value($user, ['positionName', 'PositionName'])),
            'enabled' => $this->boolValue($this->value($user, ['Enabled', 'enabled'], true)),
            'reportable' => ! $this->boolValue($this->value($user, ['IsHiddenForReports', 'isHiddenForReports'], false)),
        ];
    }

    /**
     * @param  array<int, int>  $requestedStaffIds
     * @param  Collection<string, array<string, mixed>>  $staffByRut
     * @return array<string, mixed>
     */
    private function normalizeAttendanceBook(
        array $payload,
        array $requestedStaffIds,
        Collection $staffByRut,
        Carbon $start,
        Carbon $end,
    ): array {
        $rows = [];
        $people = [];

        foreach ($this->arrayValue($payload, ['Users', 'users']) as $user) {
            if (! is_array($user)) {
                continue;
            }

            $providerUser = $this->normalizeUser($user);
            $institutionalUser = $staffByRut->get($this->normalizeRut($providerUser['identifier']));

            if ($institutionalUser === null) {
                continue;
            }

            $normalizedUser = Arr::except($institutionalUser, ['provider_identifier']);
            $intervals = $this->arrayValue($user, ['PlannedInterval', 'plannedInterval', 'planned_intervals']);
            $people[] = [
                ...$normalizedUser,
                'worked_days' => (int) $this->value($user, ['WorkedDays', 'workedDays'], 0),
                'days_attended' => (int) $this->value($user, ['DaysAttended', 'daysAttended'], 0),
                'absences' => (int) $this->value($user, ['Absences', 'absences'], 0),
                'total_worked_hours' => (string) $this->value($user, ['TotalWorkedHours', 'totalWorkedHours'], ''),
                'interval_count' => count($intervals),
            ];

            foreach ($intervals as $index => $interval) {
                if (! is_array($interval)) {
                    continue;
                }

                $punches = collect($this->arrayValue($interval, ['Punches', 'punches']))
                    ->filter(fn (mixed $punch): bool => is_array($punch))
                    ->map(fn (array $punch): array => [
                        'type' => (string) $this->value($punch, ['Type', 'type']),
                        'date' => (string) $this->value($punch, ['Date', 'date']),
                        'origin' => (string) $this->value($punch, ['Origin', 'origin']),
                        'upload_date' => (string) $this->value($punch, ['UploadDate', 'uploadDate']),
                    ])
                    ->values()
                    ->all();

                $shifts = collect($this->arrayValue($interval, ['Shifts', 'shifts']))
                    ->filter(fn (mixed $shift): bool => is_array($shift))
                    ->map(fn (array $shift): array => [
                        'name' => (string) $this->value($shift, ['ShiftDisplay', 'shiftDisplay']),
                        'begins' => (string) $this->value($shift, ['Begins', 'begins']),
                        'ends' => (string) $this->value($shift, ['Ends', 'ends']),
                        'start_time' => (string) $this->value($shift, ['StartTime', 'startTime']),
                        'exit_time' => (string) $this->value($shift, ['ExitTime', 'exitTime']),
                        'delay' => (string) $this->value($shift, ['Delay', 'delay']),
                        'early_leave' => (string) $this->value($shift, ['EarlyLeave', 'earlyLeave']),
                        'break_delay' => (string) $this->value($shift, ['BreakDelay', 'breakDelay']),
                    ])
                    ->values()
                    ->all();

                $timeOffs = collect($this->arrayValue($interval, ['TimeOffs', 'timeOffs']))
                    ->filter(fn (mixed $timeOff): bool => is_array($timeOff))
                    ->map(fn (array $timeOff): array => [
                        'type' => (string) $this->value($timeOff, ['TimeOffTypeDescription', 'timeOffTypeDescription']),
                        'starts' => (string) $this->value($timeOff, ['Starts', 'starts']),
                        'ends' => (string) $this->value($timeOff, ['Ends', 'ends']),
                        'origin' => (string) $this->value($timeOff, ['TimeOffOrigin', 'timeOffOrigin']),
                    ])
                    ->values()
                    ->all();

                $row = [
                    'key' => $normalizedUser['identifier'].'-'.((string) $this->value($interval, ['Date', 'date'], $index)),
                    'date' => (string) $this->value($interval, ['Date', 'date']),
                    'user' => $normalizedUser,
                    'worked_hours' => (string) $this->value($interval, ['WorkedHours', 'workedHours']),
                    'non_worked_hours' => (string) $this->value($interval, ['NonWorkedHours', 'nonWorkedHours']),
                    'absent' => $this->boolValue($this->value($interval, ['Absent', 'absent'], false)),
                    'holiday' => $this->boolValue($this->value($interval, ['Holiday', 'holiday'], false)),
                    'worked' => $this->boolValue($this->value($interval, ['Worked', 'worked'], false)),
                    'punches' => $punches,
                    'shifts' => $shifts,
                    'time_offs' => $timeOffs,
                ];

                $rows[] = [
                    ...$row,
                    ...$this->calculateAttendanceVariance($row),
                ];
            }
        }

        $rows = collect($rows)->sortBy([['date', 'desc'], ['user.name', 'asc']])->values();
        $workedMinutes = $rows->sum(fn (array $row): int => $this->durationToMinutes($row['worked_hours']));
        $weekly = $this->weeklyConsolidation($rows);

        return [
            'data' => $rows->all(),
            'people' => $people,
            'weekly' => $weekly,
            'summary' => [
                'requested_users' => count($requestedStaffIds),
                'returned_users' => count($people),
                'days' => $rows->count(),
                'punches' => $rows->sum(fn (array $row): int => count($row['punches'])),
                'absences' => $rows->where('absent', true)->count(),
                'worked_minutes' => $workedMinutes,
                'worked_time_label' => $this->minutesLabel($workedMinutes),
                'entry_early_minutes' => $rows->sum(fn (array $row): int => max(0, -(int) ($row['attendance_variance']['entry_delta_minutes'] ?? 0))),
                'entry_late_minutes' => $rows->sum(fn (array $row): int => max(0, (int) ($row['attendance_variance']['entry_delta_minutes'] ?? 0))),
                'exit_early_minutes' => $rows->sum(fn (array $row): int => max(0, -(int) ($row['attendance_variance']['exit_delta_minutes'] ?? 0))),
                'exit_after_minutes' => $rows->sum(fn (array $row): int => max(0, (int) ($row['attendance_variance']['exit_delta_minutes'] ?? 0))),
            ],
            'period' => [
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
            ],
            'queried_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The provider only supplies raw shifts and punches. These derived values are
     * intentionally calculated after AttendanceBook responds, never persisted.
     *
     * @param  array<string, mixed>  $row
     * @return array{schedule: array<string, mixed>, attendance_variance: array<string, mixed>}
     */
    private function calculateAttendanceVariance(array $row): array
    {
        $intervalDate = $this->providerDate($row['date'] ?? null)?->startOfDay();
        $scheduledRanges = collect($row['shifts'] ?? [])
            ->filter(fn (mixed $shift): bool => is_array($shift) && ! $this->isBreakShift($shift))
            ->map(function (array $shift) use ($intervalDate): ?array {
                $start = $this->scheduleDate($shift['start_time'] ?: $shift['begins'], $intervalDate);
                $end = $this->scheduleDate($shift['exit_time'] ?: $shift['ends'], $intervalDate);

                if ($start === null || $end === null) {
                    return null;
                }

                if ($start->format('H:i') === '00:00' && $end->format('H:i') === '00:00') {
                    return null;
                }

                if ($end->lessThanOrEqualTo($start)) {
                    $end->addDay();
                }

                return ['start' => $start, 'end' => $end];
            })
            ->filter();

        $scheduledStart = $scheduledRanges->min(fn (array $range): int => $range['start']->getTimestamp());
        $scheduledEnd = $scheduledRanges->max(fn (array $range): int => $range['end']->getTimestamp());
        $scheduledStart = $scheduledStart !== null
            ? Carbon::createFromTimestamp($scheduledStart, config('app.timezone'))
            : null;
        $scheduledEnd = $scheduledEnd !== null
            ? Carbon::createFromTimestamp($scheduledEnd, config('app.timezone'))
            : null;

        $punches = collect($row['punches'] ?? [])
            ->map(function (mixed $punch): ?array {
                if (! is_array($punch) || ($date = $this->providerDate($punch['date'] ?? null)) === null) {
                    return null;
                }

                return [
                    'date' => $date,
                    'kind' => $this->punchKind((string) ($punch['type'] ?? '')),
                ];
            })
            ->filter()
            ->sortBy(fn (array $punch): int => $punch['date']->getTimestamp())
            ->values();

        $entry = $punches->firstWhere('kind', 'entry')['date'] ?? null;
        $exit = $punches->where('kind', 'exit')->last()['date'] ?? null;
        $hasTypedPunch = $punches->contains(fn (array $punch): bool => $punch['kind'] !== null);

        if (! $hasTypedPunch) {
            $entry = $punches->first()['date'] ?? null;
            $exit = $punches->count() > 1 ? ($punches->last()['date'] ?? null) : null;
        }

        $entryDelta = $scheduledStart !== null && $entry !== null
            ? $scheduledStart->diffInMinutes($entry, false)
            : null;
        $exitDelta = $scheduledEnd !== null && $exit !== null
            ? $scheduledEnd->diffInMinutes($exit, false)
            : null;

        return [
            'schedule' => [
                'start_at' => $scheduledStart?->toIso8601String(),
                'end_at' => $scheduledEnd?->toIso8601String(),
            ],
            'attendance_variance' => [
                'entry_at' => $entry?->toIso8601String(),
                'exit_at' => $exit?->toIso8601String(),
                'entry_delta_minutes' => $entryDelta,
                'exit_delta_minutes' => $exitDelta,
                'entry_label' => $this->varianceLabel($entryDelta),
                'exit_label' => $this->varianceLabel($exitDelta),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function weeklyConsolidation(Collection $rows): array
    {
        return $rows
            ->filter(fn (array $row): bool => $this->providerDate($row['date'] ?? null) !== null)
            ->groupBy(function (array $row): string {
                $weekStart = $this->providerDate($row['date'])->startOfWeek(Carbon::MONDAY);

                return $row['user']['id'].'|'.$weekStart->toDateString();
            })
            ->map(function (Collection $weekRows): array {
                $first = $weekRows->first();
                $weekStart = $this->providerDate($first['date'])->startOfWeek(Carbon::MONDAY);
                $workedMinutes = $weekRows->sum(fn (array $row): int => $this->durationToMinutes((string) $row['worked_hours']));
                $entryDeltas = $weekRows->pluck('attendance_variance.entry_delta_minutes')->filter(fn (mixed $value): bool => $value !== null);
                $exitDeltas = $weekRows->pluck('attendance_variance.exit_delta_minutes')->filter(fn (mixed $value): bool => $value !== null);

                return [
                    'key' => $first['user']['id'].'-'.$weekStart->toDateString(),
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                    'user' => $first['user'],
                    'days' => $weekRows->count(),
                    'worked_days' => $weekRows->where('worked', true)->count(),
                    'absences' => $weekRows->where('absent', true)->count(),
                    'days_with_entry' => $entryDeltas->count(),
                    'days_with_exit' => $exitDeltas->count(),
                    'worked_minutes' => $workedMinutes,
                    'worked_time_label' => $this->minutesLabel($workedMinutes),
                    'entry_early_minutes' => $entryDeltas->sum(fn (mixed $value): int => max(0, -(int) $value)),
                    'entry_late_minutes' => $entryDeltas->sum(fn (mixed $value): int => max(0, (int) $value)),
                    'exit_early_minutes' => $exitDeltas->sum(fn (mixed $value): int => max(0, -(int) $value)),
                    'exit_after_minutes' => $exitDeltas->sum(fn (mixed $value): int => max(0, (int) $value)),
                ];
            })
            ->sortBy([
                ['week_start', 'desc'],
                ['user.name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, array<string, mixed>>  $people
     * @return array<string, mixed>
     */
    private function buildReports(
        Collection $rows,
        Collection $people,
        int $requestedUsers,
        Carbon $start,
        Carbon $end,
        int $toleranceMinutes,
        string $scope,
        int $batches,
    ): array {
        $scheduledRows = $rows->filter(fn (array $row): bool => $this->hasCalculatedSchedule($row));
        $lateRows = $rows->filter(function (array $row) use ($toleranceMinutes): bool {
            $delta = $row['attendance_variance']['entry_delta_minutes'] ?? null;

            return $delta !== null && (int) $delta > $toleranceMinutes;
        });
        $absenceRows = $rows->where('absent', true);
        $workedMinutes = $rows->sum(fn (array $row): int => $this->durationToMinutes((string) $row['worked_hours']));

        $balances = $people->map(function (array $person) use ($rows, $toleranceMinutes): array {
            $personRows = $rows->where('user.id', $person['id']);
            $entryDeltas = $personRows->pluck('attendance_variance.entry_delta_minutes')
                ->filter(fn (mixed $value): bool => $value !== null)
                ->map(fn (mixed $value): int => (int) $value);
            $exitDeltas = $personRows->pluck('attendance_variance.exit_delta_minutes')
                ->filter(fn (mixed $value): bool => $value !== null)
                ->map(fn (mixed $value): int => (int) $value);
            $entryEarly = $entryDeltas->sum(fn (int $value): int => max(0, -$value));
            $entryLate = $entryDeltas->filter(fn (int $value): bool => $value > $toleranceMinutes)->sum();
            $exitEarly = $exitDeltas->sum(fn (int $value): int => max(0, -$value));
            $exitAfter = $exitDeltas->sum(fn (int $value): int => max(0, $value));
            $worked = $personRows->sum(fn (array $row): int => $this->durationToMinutes((string) $row['worked_hours']));
            $net = $entryEarly + $exitAfter - $entryLate - $exitEarly;

            return [
                'user' => $person,
                'days' => $personRows->count(),
                'worked_days' => $personRows->where('worked', true)->count(),
                'scheduled_days' => $personRows->filter(fn (array $row): bool => $this->hasCalculatedSchedule($row))->count(),
                'worked_minutes' => $worked,
                'worked_time_label' => $this->minutesLabel($worked),
                'entry_early_occurrences' => $entryDeltas->filter(fn (int $value): bool => $value < 0)->count(),
                'entry_early_minutes' => $entryEarly,
                'entry_late_occurrences' => $entryDeltas->filter(fn (int $value): bool => $value > $toleranceMinutes)->count(),
                'entry_late_minutes' => $entryLate,
                'exit_early_occurrences' => $exitDeltas->filter(fn (int $value): bool => $value < 0)->count(),
                'exit_early_minutes' => $exitEarly,
                'exit_after_occurrences' => $exitDeltas->filter(fn (int $value): bool => $value > 0)->count(),
                'exit_after_minutes' => $exitAfter,
                'absences' => $personRows->where('absent', true)->count(),
                'net_minutes' => $net,
                'net_label' => $this->signedMinutesLabel($net),
            ];
        })->sortByDesc('entry_late_minutes')->values();

        $tardiness = $lateRows
            ->groupBy('user.id')
            ->map(function (Collection $personRows): array {
                $first = $personRows->first();
                $minutes = $personRows->sum(fn (array $row): int => (int) $row['attendance_variance']['entry_delta_minutes']);
                $maximum = $personRows->max(fn (array $row): int => (int) $row['attendance_variance']['entry_delta_minutes']);

                return [
                    'user' => $first['user'],
                    'occurrences' => $personRows->count(),
                    'minutes' => $minutes,
                    'average_minutes' => (int) round($minutes / max(1, $personRows->count())),
                    'maximum_minutes' => $maximum,
                    'details' => $personRows->sortByDesc('date')->map(fn (array $row): array => [
                        'date' => $row['date'],
                        'scheduled_entry_at' => $row['schedule']['start_at'] ?? null,
                        'entry_at' => $row['attendance_variance']['entry_at'] ?? null,
                        'minutes' => (int) $row['attendance_variance']['entry_delta_minutes'],
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('minutes')
            ->values();

        $absences = $absenceRows
            ->groupBy('user.id')
            ->map(function (Collection $personRows): array {
                $first = $personRows->first();
                $justified = $personRows->filter(fn (array $row): bool => $row['holiday'] || count($row['time_offs']) > 0)->count();

                return [
                    'user' => $first['user'],
                    'occurrences' => $personRows->count(),
                    'justified' => $justified,
                    'without_justification' => $personRows->count() - $justified,
                    'non_worked_minutes' => $personRows->sum(fn (array $row): int => $this->durationToMinutes((string) $row['non_worked_hours'])),
                    'details' => $personRows->sortByDesc('date')->map(fn (array $row): array => [
                        'date' => $row['date'],
                        'scheduled_entry_at' => $row['schedule']['start_at'] ?? null,
                        'scheduled_exit_at' => $row['schedule']['end_at'] ?? null,
                        'non_worked_hours' => $row['non_worked_hours'],
                        'justified' => $row['holiday'] || count($row['time_offs']) > 0,
                        'justifications' => collect($row['time_offs'])->pluck('type')->filter()->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('occurrences')
            ->values();

        $missingEntry = $scheduledRows->filter(fn (array $row): bool => ! $row['absent']
            && ! $row['holiday']
            && count($row['time_offs']) === 0
            && empty($row['attendance_variance']['entry_at']))->count();
        $missingExit = $scheduledRows->filter(fn (array $row): bool => ! $row['absent']
            && ! $row['holiday']
            && count($row['time_offs']) === 0
            && empty($row['attendance_variance']['exit_at']))->count();
        $summary = [
            'requested_users' => $requestedUsers,
            'returned_users' => $people->count(),
            'days' => $rows->count(),
            'scheduled_days' => $scheduledRows->count(),
            'worked_days' => $rows->where('worked', true)->count(),
            'worked_minutes' => $workedMinutes,
            'worked_time_label' => $this->minutesLabel($workedMinutes),
            'punches' => $rows->sum(fn (array $row): int => count($row['punches'])),
            'tardy_people' => $tardiness->count(),
            'tardiness_occurrences' => $lateRows->count(),
            'tardiness_minutes' => $lateRows->sum(fn (array $row): int => (int) $row['attendance_variance']['entry_delta_minutes']),
            'entry_early_minutes' => $balances->sum('entry_early_minutes'),
            'exit_early_minutes' => $balances->sum('exit_early_minutes'),
            'exit_after_minutes' => $balances->sum('exit_after_minutes'),
            'absent_people' => $absences->count(),
            'absences' => $absenceRows->count(),
            'justified_absences' => $absences->sum('justified'),
            'absences_without_justification' => $absences->sum('without_justification'),
            'missing_entry' => $missingEntry,
            'missing_exit' => $missingExit,
        ];

        $byGroup = $balances
            ->groupBy(fn (array $balance): string => trim((string) ($balance['user']['group'] ?: $balance['user']['position'])) ?: 'Sin clasificación')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'people' => $group->count(),
                'worked_minutes' => $group->sum('worked_minutes'),
                'worked_time_label' => $this->minutesLabel($group->sum('worked_minutes')),
                'tardiness_occurrences' => $group->sum('entry_late_occurrences'),
                'tardiness_minutes' => $group->sum('entry_late_minutes'),
                'absences' => $group->sum('absences'),
            ])
            ->sortByDesc('tardiness_minutes')
            ->values();

        return [
            'summary' => $summary,
            'reports' => [
                'tardiness' => $tardiness->all(),
                'balances' => $balances->all(),
                'absences' => $absences->all(),
                'by_group' => $byGroup->all(),
            ],
            'coverage' => [
                'requested_users' => $requestedUsers,
                'returned_users' => $people->count(),
                'scheduled_days' => $scheduledRows->count(),
                'days_with_entry' => $rows->whereNotNull('attendance_variance.entry_at')->count(),
                'days_with_exit' => $rows->whereNotNull('attendance_variance.exit_at')->count(),
                'batches' => $batches,
            ],
            'filters' => [
                'scope' => $scope,
                'tolerance_minutes' => $toleranceMinutes,
            ],
            'period' => [
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
            ],
            'queried_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $row */
    private function hasCalculatedSchedule(array $row): bool
    {
        return ! empty($row['schedule']['start_at']) && ! empty($row['schedule']['end_at']);
    }

    /** @param array<string, mixed> $shift */
    private function isBreakShift(array $shift): bool
    {
        $name = Str::lower(Str::ascii((string) ($shift['name'] ?? '')));

        return Str::contains($name, ['break', 'colacion', 'descanso']);
    }

    private function punchKind(string $type): ?string
    {
        $normalized = Str::lower(Str::ascii($type));

        if (Str::contains($normalized, ['ingreso', 'entrada', 'entry'])) {
            return 'entry';
        }

        if (Str::contains($normalized, ['salida', 'exit'])) {
            return 'exit';
        }

        return null;
    }

    private function scheduleDate(mixed $value, ?Carbon $intervalDate): ?Carbon
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $matches)) {
            if ($intervalDate === null) {
                return null;
            }

            return $intervalDate->copy()->setTime((int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0));
        }

        return $this->providerDate($raw);
    }

    private function providerDate(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        try {
            if (preg_match('/^\/Date\((-?\d+)/', $raw, $matches)) {
                return Carbon::createFromTimestamp((int) floor(((int) $matches[1]) / 1000), config('app.timezone'));
            }

            if (preg_match('/^\d{14}$/', $raw)) {
                return Carbon::createFromFormat('YmdHis', $raw, config('app.timezone'));
            }

            if (preg_match('/^\d{8}$/', $raw)) {
                return Carbon::createFromFormat('Ymd', $raw, config('app.timezone'))->startOfDay();
            }

            return Carbon::parse($raw, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function varianceLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes === 0) {
            return 'A la hora';
        }

        return sprintf('%d min %s', abs($minutes), $minutes < 0 ? 'antes' : 'después');
    }

    private function value(array $source, array $keys, mixed $default = ''): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return $source[$key];
            }
        }

        return $default;
    }

    /** @return array<int, mixed> */
    private function arrayValue(array $source, array $keys): array
    {
        $value = $this->value($source, $keys, []);

        return is_array($value) ? array_values($value) : [];
    }

    private function boolValue(mixed $value): bool
    {
        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'si', 'sí'], true);
    }

    private function durationToMinutes(string $duration): int
    {
        if (! preg_match('/^(\d{1,4}):(\d{2})(?::\d{2})?$/', trim($duration), $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function minutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 h';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? sprintf('%d h %02d min', $hours, $remaining) : sprintf('%d h', $hours);
    }

    private function signedMinutesLabel(int $minutes): string
    {
        if ($minutes === 0) {
            return '0 min';
        }

        $sign = $minutes > 0 ? '+' : '-';
        $absolute = abs($minutes);
        $hours = intdiv($absolute, 60);
        $remaining = $absolute % 60;

        if ($hours === 0) {
            return sprintf('%s%d min', $sign, $remaining);
        }

        return $remaining > 0
            ? sprintf('%s%d h %02d min', $sign, $hours, $remaining)
            : sprintf('%s%d h', $sign, $hours);
    }

    private function normalizeRut(mixed $rut): string
    {
        return Str::lower((string) preg_replace('/[^0-9kK]/', '', trim((string) $rut)));
    }

    private function usersCacheKey(): string
    {
        return 'geovictoria.users.'.hash('sha256', implode('|', [
            (string) config('services.geovictoria.base_url'),
            (string) config('services.geovictoria.api_key'),
        ]));
    }
}
