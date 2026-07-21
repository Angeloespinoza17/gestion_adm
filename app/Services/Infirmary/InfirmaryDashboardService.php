<?php

namespace App\Services\Infirmary;

use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionFollowUp;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InfirmaryDashboardService
{
    private const ACCIDENT_CATEGORIES = ['accidente_menor', 'accidente_mayor'];

    public function __construct(
        private readonly InfirmaryMedicationDailyStatusService $dailyStatusService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        $previousRange = $this->previousRange($range);

        $attentions = $this->attentionQuery($range['from'], $range['to'], $filters);
        $previousAttentions = $this->attentionQuery($previousRange['from'], $previousRange['to'], $filters);
        $accidents = $this->accidentAttentionQuery($range['from'], $range['to'], $filters);
        $previousAccidents = $this->accidentAttentionQuery($previousRange['from'], $previousRange['to'], $filters);
        $administrations = $this->administrationQuery($range['from'], $range['to'], $filters);
        $previousAdministrations = $this->administrationQuery($previousRange['from'], $previousRange['to'], $filters);
        $calls = $this->callQuery($range['from'], $range['to'], $filters);
        $previousCalls = $this->callQuery($previousRange['from'], $previousRange['to'], $filters);

        $treatments = $this->treatmentRows($range['from'], $range['to'], $filters);
        $previousTreatments = $this->treatmentRows($previousRange['from'], $previousRange['to'], $filters);
        $referrals = $this->referralRows($range['from'], $range['to'], $filters, $treatments);
        $previousReferrals = $this->referralRows(
            $previousRange['from'],
            $previousRange['to'],
            $filters,
            $previousTreatments,
        );

        $currentMetrics = $this->metrics(
            $attentions,
            $accidents,
            $administrations,
            $calls,
            $referrals,
        );
        $previousMetrics = $this->metrics(
            $previousAttentions,
            $previousAccidents,
            $previousAdministrations,
            $previousCalls,
            $previousReferrals,
        );

        $attentionDates = (clone $attentions)->pluck('attended_at');
        $accidentDates = (clone $accidents)->pluck('occurred_at');
        $trend = $this->activityTrend($range, $attentionDates, $accidentDates);
        $categories = $this->groupedColumn($attentions, 'attention_category', 'Sin categoría');
        $courses = $this->groupedColumn($attentions, 'course_name_snapshot', 'Sin curso', 10);
        $hours = $this->hourDistribution($attentionDates);
        $accidentLocations = $this->groupedColumn($accidents, 'accident_location_type', 'Sin ubicación');
        $accidentDependencies = $this->accidentDependencies($accidents);
        $treatmentTypes = $this->arrayDistribution($treatments, 'treatment_types', 10);
        $treatmentCategories = $this->arrayDistribution($treatments, 'treatment_categories');
        $medications = $this->medicationDistribution($administrations);
        $referralDistribution = $this->rowsDistribution($referrals, 'label', 10);
        $administrationOutcomes = $this->administrationOutcomes($administrations);
        $healthProfile = $this->healthProfileStatistics($filters);

        return [
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'date_range' => $this->rangePayload($range, $previousRange),
            'applied_filters' => [
                'period' => $filters['period'] ?? 'mensual',
                'course_section_id' => $filters['course_section_id'] ?? null,
                'attention_category' => $filters['attention_category'] ?? null,
                'accident_location_type' => $filters['accident_location_type'] ?? null,
            ],
            'metrics' => $currentMetrics,
            'metric_comparisons' => $this->metricComparisons($currentMetrics, $previousMetrics),
            'insights' => $this->insights(
                $currentMetrics,
                $categories,
                $courses,
                $hours,
                $trend,
            ),
            'operational' => $this->operationalStatus(),
            'health_profile' => $healthProfile,
            'charts' => [
                'activity_trend' => $trend,
                'attentions_by_category' => $categories,
                'attentions_by_course' => $courses,
                'attentions_by_hour' => $hours,
                'accidents_by_location' => $accidentLocations,
                'accidents_by_dependency' => $accidentDependencies,
                'treatment_categories' => $treatmentCategories,
                'frequent_treatments' => $treatmentTypes,
                'medications_administered' => $medications,
                'referrals' => $referralDistribution,
                'administration_outcomes' => $administrationOutcomes,
            ],
            'recent' => [
                'attentions' => (clone $attentions)
                    ->latest('attended_at')
                    ->limit(6)
                    ->get([
                        'id',
                        'student_full_name_snapshot',
                        'course_name_snapshot',
                        'attended_at',
                        'attention_category',
                        'consultation_reason',
                    ]),
                'accidents' => (clone $accidents)
                    ->with('dependency:id,name')
                    ->latest('occurred_at')
                    ->limit(6)
                    ->get([
                        'id',
                        'dependency_id',
                        'student_full_name_snapshot',
                        'course_name_snapshot',
                        'occurred_at',
                        'attention_category',
                        'accident_location_type',
                        'accident_circumstance',
                    ]),
                'medication_alerts' => InfirmaryMedication::query()
                    ->where('inventory_type', InfirmaryMedication::INVENTORY_TYPE_MEDICATION)
                    ->whereIn('status', ['stock_bajo', 'agotado', 'proximo_a_vencer', 'vencido'])
                    ->orderBy('expires_at')
                    ->orderBy('current_stock')
                    ->limit(8)
                    ->get(['id', 'name', 'commercial_name', 'current_stock', 'minimum_stock', 'expires_at', 'status']),
            ],
        ];
    }

    /**
     * Estadísticas agregadas del perfil vigente. No retorna diagnósticos,
     * observaciones ni identificadores de estudiantes.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function healthProfileStatistics(array $filters): array
    {
        $students = StudentProfile::query()
            ->where('general_status', 'activo')
            ->when(
                ! empty($filters['course_section_id']),
                fn (Builder $query) => $query->whereHas(
                    'enrollments',
                    fn (Builder $enrollment) => $enrollment
                        ->where('course_section_id', $filters['course_section_id'])
                        ->where('enrollment_status', 'activo'),
                ),
            );

        $total = (clone $students)->count();
        $withHealthInformation = (clone $students)
            ->where(function (Builder $query) {
                $query
                    ->whereNotNull('health_insurance')
                    ->orWhereNotNull('blood_type')
                    ->orWhereNotNull('food_allergies')
                    ->orWhereNotNull('has_chronic_illness')
                    ->orWhereNotNull('has_medication_allergies')
                    ->orWhereNotNull('has_physical_restrictions')
                    ->orWhereNotNull('fit_for_physical_education');
            })
            ->count();

        return [
            'scope' => 'current',
            'total_students' => $total,
            'students_with_health_information' => $withHealthInformation,
            'health_information_coverage' => $this->percentage($withHealthInformation, $total),
            'chronic_illnesses' => (clone $students)->where('has_chronic_illness', true)->count(),
            'medication_allergies' => (clone $students)->where('has_medication_allergies', true)->count(),
            'food_allergies' => (clone $students)->whereNotNull('food_allergies')->where('food_allergies', '!=', '')->count(),
            'physical_restrictions' => (clone $students)->where('has_physical_restrictions', true)->count(),
            'not_fit_for_physical_education' => (clone $students)->where('fit_for_physical_education', false)->count(),
            'private_school_insurance' => (clone $students)->where('has_private_school_insurance', true)->count(),
            'health_insurance_distribution' => $this->profileDistribution($students, 'health_insurance'),
            'blood_type_distribution' => $this->profileDistribution($students, 'blood_type'),
        ];
    }

    private function profileDistribution(Builder $students, string $column): array
    {
        return (clone $students)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn (StudentProfile $row) => [
                'label' => $row->getAttribute('label'),
                'total' => (int) $row->getAttribute('total'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{period: string, from: Carbon, to: Carbon, days: int, granularity: string}
     */
    public function resolveDateRange(array $filters, ?CarbonInterface $clock = null): array
    {
        $now = $clock
            ? Carbon::instance($clock)->copy()->setTimezone(config('app.timezone'))
            : now(config('app.timezone'));
        $period = $filters['period'] ?? 'mensual';
        $to = $now->copy()->endOfDay();

        if ($period === 'personalizado') {
            $from = Carbon::parse($filters['from'], config('app.timezone'))->startOfDay();
            $to = Carbon::parse($filters['to'], config('app.timezone'))->endOfDay();
        } else {
            $from = match ($period) {
                'diario' => $now->copy()->startOfDay(),
                'semanal' => $now->copy()->startOfWeek(),
                'semestral' => $now->month <= 6
                    ? $now->copy()->startOfYear()
                    : $now->copy()->month(7)->startOfMonth(),
                'anual' => $now->copy()->startOfYear(),
                default => $now->copy()->startOfMonth(),
            };
        }

        $days = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'granularity' => match (true) {
                $days <= 45 => 'daily',
                $days <= 180 => 'weekly',
                default => 'monthly',
            },
        ];
    }

    /**
     * @param  array{period: string, from: Carbon, to: Carbon, days: int, granularity: string}  $range
     * @return array{from: Carbon, to: Carbon}
     */
    private function previousRange(array $range): array
    {
        $to = $range['from']->copy()->subSecond();

        return [
            'from' => $to->copy()->subDays($range['days'] - 1)->startOfDay(),
            'to' => $to,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function attentionQuery(CarbonInterface $from, CarbonInterface $to, array $filters): Builder
    {
        return $this->applyAttentionDimensions(
            InfirmaryAttention::query()->whereBetween('attended_at', [$from, $to]),
            $filters,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function accidentAttentionQuery(CarbonInterface $from, CarbonInterface $to, array $filters): Builder
    {
        return $this->applyAttentionDimensions(
            InfirmaryAttention::query()
                ->whereBetween('occurred_at', [$from, $to])
                ->whereIn('attention_category', self::ACCIDENT_CATEGORIES),
            $filters,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function administrationQuery(CarbonInterface $from, CarbonInterface $to, array $filters): Builder
    {
        $query = InfirmaryMedicationAdministration::query()
            ->whereNotNull('student_profile_id')
            ->whereBetween('administered_at', [$from, $to]);

        if (! empty($filters['course_section_id'])) {
            $query->whereHas('student.enrollments', fn (Builder $enrollment) => $enrollment
                ->where('course_section_id', $filters['course_section_id']));
        }

        if (! empty($filters['attention_category']) || ! empty($filters['accident_location_type'])) {
            $query->whereHas('attention', fn (Builder $attention) => $this
                ->applyAttentionDimensions($attention, $filters, false));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function callQuery(CarbonInterface $from, CarbonInterface $to, array $filters): Builder
    {
        $query = InfirmaryAttentionCall::query()
            ->whereNotNull('student_profile_id')
            ->whereBetween('called_at', [$from, $to]);

        if ($this->hasAttentionDimension($filters)) {
            $query->whereHas('attention', fn (Builder $attention) => $this
                ->applyAttentionDimensions($attention, $filters));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAttentionDimensions(Builder $query, array $filters, bool $includeCourse = true): Builder
    {
        return $query
            ->where('subject_type', InfirmaryAttention::SUBJECT_STUDENT)
            ->when(
                $includeCourse && ! empty($filters['course_section_id']),
                fn (Builder $builder) => $builder->where('course_section_id', $filters['course_section_id']),
            )
            ->when(
                ! empty($filters['attention_category']),
                fn (Builder $builder) => $builder->where('attention_category', $filters['attention_category']),
            )
            ->when(
                ! empty($filters['accident_location_type']),
                fn (Builder $builder) => $builder->where('accident_location_type', $filters['accident_location_type']),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasAttentionDimension(array $filters): bool
    {
        return ! empty($filters['course_section_id'])
            || ! empty($filters['attention_category'])
            || ! empty($filters['accident_location_type']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function treatmentRows(CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return InfirmaryAttentionTreatment::query()
            ->whereHas('attention', function (Builder $attention) use ($from, $to, $filters) {
                $this->applyAttentionDimensions(
                    $attention->whereBetween('attended_at', [$from, $to]),
                    $filters,
                );
            })
            ->get([
                'attention_id',
                'treatment_types',
                'treatment_categories',
                'derivation_type',
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function referralRows(
        CarbonInterface $from,
        CarbonInterface $to,
        array $filters,
        Collection $treatments,
    ): Collection {
        $treatmentRows = $treatments
            ->filter(fn (InfirmaryAttentionTreatment $treatment) => filled($treatment->derivation_type))
            ->map(fn (InfirmaryAttentionTreatment $treatment) => [
                'key' => 'treatment-'.$treatment->attention_id.'-'.$treatment->derivation_type,
                'attention_id' => $treatment->attention_id,
                'label' => $treatment->derivation_type,
            ]);

        $referralQuery = InfirmaryAttentionReferral::query()
            ->whereBetween('referred_at', [$from, $to])
            ->whereHas('attention', fn (Builder $attention) => $this
                ->applyAttentionDimensions($attention, $filters));

        $legacyRows = $referralQuery
            ->get(['id', 'attention_id', 'referral_type'])
            ->map(fn (InfirmaryAttentionReferral $referral) => [
                'key' => 'referral-'.$referral->id,
                'attention_id' => $referral->attention_id,
                'label' => $referral->referral_type,
            ]);

        return $treatmentRows->concat($legacyRows)->unique('key')->values();
    }

    private function metrics(
        Builder $attentions,
        Builder $accidents,
        Builder $administrations,
        Builder $calls,
        Collection $referrals,
    ): array {
        $attentionCount = (clone $attentions)->count();
        $administeredCount = $this->administrationCount($administrations, true);
        $notAdministeredCount = $this->administrationCount($administrations, false);
        $registeredMedicationCount = $administeredCount + $notAdministeredCount;
        $referralCount = $referrals->count();

        return [
            'attentions_total' => $attentionCount,
            'unique_students' => (clone $attentions)->distinct('student_profile_id')->count('student_profile_id'),
            'accidents_total' => (clone $accidents)->count(),
            'medications_administered_total' => $administeredCount,
            'medications_not_administered_total' => $notAdministeredCount,
            'referrals_total' => $referralCount,
            'calls_total' => (clone $calls)->count(),
            'average_attention_minutes' => round((float) (clone $attentions)->avg('attention_duration_minutes'), 1),
            'accident_rate' => $this->percentage((clone $accidents)->count(), $attentionCount),
            'referral_rate' => $this->percentage($referralCount, $attentionCount),
            'medication_adherence' => $this->percentage($administeredCount, $registeredMedicationCount),
        ];
    }

    private function administrationCount(Builder $query, bool $administered): int
    {
        $query = clone $query;

        if ($administered) {
            return $query
                ->where(function (Builder $status) {
                    $status
                        ->whereNull('administration_status')
                        ->orWhere('administration_status', InfirmaryMedicationAdministration::STATUS_ADMINISTRADA);
                })
                ->count();
        }

        return $query
            ->where('administration_status', InfirmaryMedicationAdministration::STATUS_NO_ADMINISTRADA)
            ->count();
    }

    private function percentage(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }

    private function metricComparisons(array $current, array $previous): array
    {
        $keys = [
            'attentions_total',
            'unique_students',
            'accidents_total',
            'medications_administered_total',
            'referrals_total',
            'calls_total',
            'average_attention_minutes',
            'medication_adherence',
        ];

        return collect($keys)->mapWithKeys(function (string $key) use ($current, $previous) {
            $currentValue = (float) ($current[$key] ?? 0);
            $previousValue = (float) ($previous[$key] ?? 0);
            $change = $previousValue === 0.0
                ? ($currentValue === 0.0 ? 0.0 : null)
                : round((($currentValue - $previousValue) / $previousValue) * 100, 1);

            return [$key => [
                'current' => $currentValue,
                'previous' => $previousValue,
                'change' => $change,
            ]];
        })->all();
    }

    private function groupedColumn(Builder $query, string $column, string $fallback, ?int $limit = null): array
    {
        $rows = (clone $query)
            ->select($column)
            ->selectRaw('COUNT(*) as aggregate_total')
            ->groupBy($column)
            ->get()
            ->map(fn ($row) => [
                'label' => filled($row->getAttribute($column)) ? (string) $row->getAttribute($column) : $fallback,
                'total' => (int) $row->aggregate_total,
            ])
            ->sortByDesc('total');

        return ($limit ? $rows->take($limit) : $rows)->values()->all();
    }

    private function rowsDistribution(Collection $rows, string $key, ?int $limit = null): array
    {
        $grouped = $rows
            ->groupBy(fn ($row) => filled(data_get($row, $key)) ? data_get($row, $key) : 'Sin información')
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'total' => $items->count(),
            ])
            ->sortByDesc('total');

        return ($limit ? $grouped->take($limit) : $grouped)->values()->all();
    }

    private function arrayDistribution(Collection $rows, string $column, ?int $limit = null): array
    {
        $counts = [];

        foreach ($rows as $row) {
            foreach (($row->{$column} ?: []) as $value) {
                if (! filled($value)) {
                    continue;
                }

                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        arsort($counts);
        $items = collect($counts)->map(fn (int $total, string $label) => [
            'label' => $label,
            'total' => $total,
        ]);

        return ($limit ? $items->take($limit) : $items)->values()->all();
    }

    private function accidentDependencies(Builder $accidents): array
    {
        return (clone $accidents)
            ->with('dependency:id,name')
            ->get(['id', 'dependency_id', 'accident_location_type'])
            ->groupBy(fn (InfirmaryAttention $attention) => $attention->accident_location_type === 'trayecto'
                ? 'Trayecto'
                : ($attention->dependency?->name ?: 'Sin dependencia'))
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'total' => $items->count(),
            ])
            ->sortByDesc('total')
            ->take(10)
            ->values()
            ->all();
    }

    private function medicationDistribution(Builder $administrations): array
    {
        $rows = (clone $administrations)
            ->where(function (Builder $status) {
                $status
                    ->whereNull('administration_status')
                    ->orWhere('administration_status', InfirmaryMedicationAdministration::STATUS_ADMINISTRADA);
            })
            ->select('medication_id')
            ->selectRaw('COUNT(*) as aggregate_total')
            ->groupBy('medication_id')
            ->orderByDesc('aggregate_total')
            ->limit(10)
            ->get();
        $medicationNames = InfirmaryMedication::query()
            ->whereIn('id', $rows->pluck('medication_id')->filter())
            ->get(['id', 'name', 'commercial_name'])
            ->mapWithKeys(fn (InfirmaryMedication $medication) => [
                $medication->id => $medication->commercial_name ?: $medication->name,
            ]);

        return $rows->map(fn ($row) => [
            'label' => $medicationNames[$row->medication_id] ?? 'Medicamento sin nombre',
            'total' => (int) $row->aggregate_total,
        ])->values()->all();
    }

    private function administrationOutcomes(Builder $administrations): array
    {
        return [
            [
                'label' => 'Administradas',
                'total' => $this->administrationCount($administrations, true),
            ],
            [
                'label' => 'No administradas',
                'total' => $this->administrationCount($administrations, false),
            ],
        ];
    }

    private function hourDistribution(Collection $dates): array
    {
        return $dates
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->hour)
            ->countBy()
            ->sortKeys()
            ->map(fn (int $total, int|string $hour) => [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'total' => $total,
            ])
            ->values()
            ->all();
    }

    private function activityTrend(array $range, Collection $attentionDates, Collection $accidentDates): array
    {
        $labels = $this->trendLabels($range);
        $attentionCounts = $this->dateCounts($attentionDates, $range['granularity']);
        $accidentCounts = $this->dateCounts($accidentDates, $range['granularity']);

        return [
            'labels' => $labels,
            'attentions' => collect($labels)->map(fn (string $label) => (int) ($attentionCounts[$label] ?? 0))->all(),
            'accidents' => collect($labels)->map(fn (string $label) => (int) ($accidentCounts[$label] ?? 0))->all(),
            'granularity' => $range['granularity'],
        ];
    }

    private function trendLabels(array $range): array
    {
        $labels = [];
        $cursor = $range['from']->copy()->startOfDay();
        $end = $range['to']->copy()->endOfDay();

        if ($range['granularity'] === 'weekly') {
            $cursor->startOfWeek();
        } elseif ($range['granularity'] === 'monthly') {
            $cursor->startOfMonth();
        }

        while ($cursor->lte($end)) {
            $labels[] = $this->dateBucket($cursor, $range['granularity']);
            $cursor = match ($range['granularity']) {
                'weekly' => $cursor->addWeek(),
                'monthly' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return array_values(array_unique($labels));
    }

    private function dateCounts(Collection $dates, string $granularity): Collection
    {
        return $dates
            ->filter()
            ->map(fn ($date) => $this->dateBucket(Carbon::parse($date), $granularity))
            ->countBy();
    }

    private function dateBucket(CarbonInterface $date, string $granularity): string
    {
        return match ($granularity) {
            'weekly' => Carbon::instance($date)->copy()->startOfWeek()->format('Y-m-d'),
            'monthly' => Carbon::instance($date)->copy()->format('Y-m'),
            default => Carbon::instance($date)->copy()->format('Y-m-d'),
        };
    }

    private function insights(
        array $metrics,
        array $categories,
        array $courses,
        array $hours,
        array $trend,
    ): array {
        $busiestIndex = collect($trend['attentions'])->search(max($trend['attentions'] ?: [0]));

        return [
            'top_category' => $categories[0] ?? ['label' => 'Sin datos', 'total' => 0],
            'top_course' => $courses[0] ?? ['label' => 'Sin datos', 'total' => 0],
            'peak_hour' => collect($hours)->sortByDesc('total')->first() ?? ['label' => 'Sin datos', 'total' => 0],
            'busiest_period' => [
                'label' => $busiestIndex === false ? 'Sin datos' : ($trend['labels'][$busiestIndex] ?? 'Sin datos'),
                'total' => $busiestIndex === false ? 0 : ($trend['attentions'][$busiestIndex] ?? 0),
                'granularity' => $trend['granularity'],
            ],
            'accident_rate' => $metrics['accident_rate'],
            'referral_rate' => $metrics['referral_rate'],
            'medication_adherence' => $metrics['medication_adherence'],
        ];
    }

    private function operationalStatus(): array
    {
        $today = now(config('app.timezone'));
        $authorizations = InfirmaryMedicationAuthorization::query()
            ->with([
                'schedules',
                'administrations' => fn ($query) => $query->where(function ($administrations) use ($today) {
                    $administrations
                        ->whereDate('scheduled_for_date', $today->toDateString())
                        ->orWhereBetween('administered_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()]);
                }),
            ])
            ->whereIn('status', [
                InfirmaryMedicationAuthorization::STATUS_VIGENTE,
                InfirmaryMedicationAuthorization::STATUS_PROXIMA_A_VENCER,
            ])
            ->get();
        $dailyStatuses = $authorizations->map(fn (InfirmaryMedicationAuthorization $authorization) => $this
            ->dailyStatusService
            ->forAuthorization($authorization, $today));

        return [
            'pending_medication_routines' => $dailyStatuses
                ->filter(fn (array $status) => $status['applicable'] && $status['pending_count'] > 0)
                ->count(),
            'overdue_doses_today' => $dailyStatuses->sum('overdue_count'),
            'medication_incidents_today' => $dailyStatuses->sum('not_administered_count'),
            'expired_medications' => InfirmaryMedication::query()
                ->where('inventory_type', InfirmaryMedication::INVENTORY_TYPE_MEDICATION)
                ->where('status', 'vencido')
                ->count(),
            'expiring_medications' => InfirmaryMedication::query()
                ->where('inventory_type', InfirmaryMedication::INVENTORY_TYPE_MEDICATION)
                ->where('status', 'proximo_a_vencer')
                ->count(),
            'critical_stock' => InfirmaryMedication::query()->whereIn('status', ['stock_bajo', 'agotado'])->count(),
            'open_accidents' => InfirmaryAccident::query()->where('case_status', '!=', 'cerrado')->count(),
            'pending_follow_ups' => InfirmaryAttentionFollowUp::query()
                ->where('status', '!=', 'cerrado')
                ->whereHas('attention', fn (Builder $attention) => $attention
                    ->where('subject_type', InfirmaryAttention::SUBJECT_STUDENT))
                ->count(),
            'pending_calls' => InfirmaryAttentionCall::query()
                ->whereNotNull('student_profile_id')
                ->where('call_status', 'pendiente')
                ->count(),
            'expiring_authorizations' => InfirmaryMedicationAuthorization::query()
                ->where('status', InfirmaryMedicationAuthorization::STATUS_PROXIMA_A_VENCER)
                ->count(),
        ];
    }

    private function rangePayload(array $range, array $previousRange): array
    {
        return [
            'period' => $range['period'],
            'from' => $range['from']->toDateString(),
            'to' => $range['to']->toDateString(),
            'label' => $range['from']->format('d/m/Y').' al '.$range['to']->format('d/m/Y'),
            'days' => $range['days'],
            'granularity' => $range['granularity'],
            'comparison_from' => $previousRange['from']->toDateString(),
            'comparison_to' => $previousRange['to']->toDateString(),
            'comparison_label' => $previousRange['from']->format('d/m/Y').' al '.$previousRange['to']->format('d/m/Y'),
        ];
    }
}
