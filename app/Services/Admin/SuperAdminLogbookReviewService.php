<?php

namespace App\Services\Admin;

use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Infirmary\InfirmaryDailyLog;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\PorterDailyLogEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SuperAdminLogbookReviewService
{
    private const SOURCES = [
        'inspectoria' => [
            'label' => 'Inspectoría',
            'description' => 'Novedades, asistencia, convivencia y seguimiento de jornada.',
            'icon' => 'bx-shield-quarter',
            'accent' => '#5b5bd6',
            'route' => '/inspectoria/bitacora',
        ],
        'porter' => [
            'label' => 'Portería',
            'description' => 'Incidencias, visitas, llaves y observaciones de acceso.',
            'icon' => 'bx-building-house',
            'accent' => '#0d9488',
            'route' => '/porter/daily-log',
        ],
        'infirmary' => [
            'label' => 'Enfermería',
            'description' => 'Registros clínico-operativos y continuidades de atención.',
            'icon' => 'bx-plus-medical',
            'accent' => '#dc5f73',
            'route' => '/infirmary/daily-log',
        ],
        'convivencia' => [
            'label' => 'Convivencia Escolar',
            'description' => 'Hechos diarios, medidas inmediatas y derivaciones asociadas.',
            'icon' => 'bx-happy-heart-eyes',
            'accent' => '#d97706',
            'route' => '/convivencia/bitacora',
        ],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function review(array $filters): array
    {
        $union = $this->buildUnion($filters);
        $summary = $this->summary(clone $union);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 10), 50);

        $paginator = DB::query()
            ->fromSub(clone $union, 'unified_logbooks')
            ->orderByDesc('occurred_at')
            ->orderBy('source')
            ->orderByDesc('source_id')
            ->paginate($perPage);

        $paginator->setCollection($this->hydratePage($paginator->getCollection()));

        return [
            ...$paginator->toArray(),
            'summary' => $summary,
            'sources' => $this->sourceCatalog($summary['by_source']),
            'catalogs' => [
                'priorities' => $this->options([
                    'baja' => 'Baja',
                    'media' => 'Media',
                    'alta' => 'Alta',
                    'urgente' => 'Urgente',
                ]),
                'statuses' => $this->options([
                    'registrado' => 'Registrado',
                    'en_seguimiento' => 'En seguimiento',
                    'revisado' => 'Revisado',
                    'destacado' => 'Destacado',
                    'convertido_caso' => 'Convertido en caso',
                    'convertido_derivacion' => 'Convertido en derivación',
                    'cerrado' => 'Cerrado',
                ]),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function buildUnion(array $filters): Builder
    {
        $queries = [
            'inspectoria' => $this->inspectoriaQuery($filters),
            'porter' => $this->porterQuery($filters),
            'infirmary' => $this->infirmaryQuery($filters),
            'convivencia' => $this->convivenciaQuery($filters),
        ];

        $source = (string) ($filters['source'] ?? '');
        if ($source !== '' && isset($queries[$source])) {
            return $queries[$source];
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    /** @param array<string, mixed> $filters */
    private function inspectoriaQuery(array $filters): Builder
    {
        $query = DB::table('inspectoria_daily_logs as logs')
            ->leftJoin('users as authors', 'authors.id', '=', 'logs.registered_by_user_id')
            ->leftJoin('student_profiles as students', 'students.id', '=', 'logs.student_profile_id')
            ->leftJoin('course_sections as courses', 'courses.id', '=', 'logs.course_section_id')
            ->selectRaw("'inspectoria' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.happened_at',
                'category' => 'logs.category',
                'priority' => 'logs.priority',
                'status' => 'logs.status',
                'title' => 'logs.title',
                'detail' => 'logs.detail',
                'author_user_id' => 'logs.registered_by_user_id',
                'author_name' => 'authors.name',
                'student_profile_id' => 'logs.student_profile_id',
                'course_section_id' => 'logs.course_section_id',
                'requires_follow_up' => 'logs.requires_follow_up',
                'is_sensitive' => '0',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.happened_at',
            'category' => 'logs.category',
            'priority' => 'logs.priority',
            'status' => 'logs.status',
            'search' => [
                'logs.title', 'logs.detail', 'logs.follow_up_note', 'logs.late_staff_name_snapshot',
                'authors.name', 'students.first_name', 'students.last_name', 'students.registered_name',
                'students.rut', 'courses.display_name',
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function porterQuery(array $filters): Builder
    {
        $query = DB::table('porter_daily_log_entries as logs')
            ->leftJoin('users as authors', 'authors.id', '=', 'logs.registered_by')
            ->selectRaw("'porter' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.logged_at',
                'category' => 'logs.category',
                'priority' => 'logs.priority',
                'status' => 'logs.status',
                'title' => 'logs.title',
                'detail' => 'logs.detail',
                'author_user_id' => 'logs.registered_by',
                'author_name' => 'authors.name',
                'student_profile_id' => 'NULL',
                'student_first_name' => 'NULL',
                'student_last_name' => 'NULL',
                'student_registered_name' => 'NULL',
                'student_rut' => 'NULL',
                'course_section_id' => 'NULL',
                'course_name' => 'NULL',
                'requires_follow_up' => '0',
                'is_sensitive' => '0',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.logged_at',
            'category' => 'logs.category',
            'priority' => 'logs.priority',
            'status' => 'logs.status',
            'search' => ['logs.title', 'logs.detail', 'logs.shift_label', 'authors.name'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function infirmaryQuery(array $filters): Builder
    {
        $query = DB::table('infirmary_daily_logs as logs')
            ->leftJoin('users as authors', 'authors.id', '=', 'logs.registered_by_user_id')
            ->leftJoin('student_profiles as students', 'students.id', '=', 'logs.student_profile_id')
            ->leftJoin('course_sections as courses', 'courses.id', '=', 'logs.course_section_id')
            ->selectRaw("'infirmary' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.happened_at',
                'category' => 'logs.category',
                'priority' => 'logs.priority',
                'status' => 'logs.status',
                'title' => 'logs.title',
                'detail' => 'logs.detail',
                'author_user_id' => 'logs.registered_by_user_id',
                'author_name' => 'authors.name',
                'student_profile_id' => 'logs.student_profile_id',
                'course_section_id' => 'logs.course_section_id',
                'requires_follow_up' => 'logs.requires_follow_up',
                'is_sensitive' => '1',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.happened_at',
            'category' => 'logs.category',
            'priority' => 'logs.priority',
            'status' => 'logs.status',
            'search' => [
                'logs.title', 'logs.detail', 'logs.action_taken', 'logs.follow_up_note', 'authors.name',
                'students.first_name', 'students.last_name', 'students.registered_name', 'students.rut',
                'courses.display_name',
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function convivenciaQuery(array $filters): Builder
    {
        $query = DB::table('convivencia_daily_logs as logs')
            ->leftJoin('users as authors', 'authors.id', '=', DB::raw('COALESCE(logs.inspector_user_id, logs.created_by)'))
            ->leftJoin('student_profiles as students', 'students.id', '=', 'logs.student_profile_id')
            ->leftJoin('course_sections as courses', 'courses.id', '=', 'logs.course_section_id')
            ->whereNull('logs.deleted_at')
            ->selectRaw("'convivencia' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.happened_at',
                'category' => 'logs.daily_log_type_label',
                'priority' => 'NULL',
                'status' => 'logs.status',
                'title' => "COALESCE(logs.daily_log_type_label, 'Hecho diario')",
                'detail' => 'logs.description',
                'author_user_id' => 'COALESCE(logs.inspector_user_id, logs.created_by)',
                'author_name' => 'authors.name',
                'student_profile_id' => 'logs.student_profile_id',
                'course_section_id' => 'logs.course_section_id',
                'requires_follow_up' => '0',
                'is_sensitive' => 'logs.is_sensitive',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.happened_at',
            'category' => 'logs.daily_log_type_label',
            'priority' => null,
            'status' => 'logs.status',
            'search' => [
                'logs.daily_log_type_label', 'logs.description', 'logs.immediate_action', 'logs.place',
                'logs.guardian_contact_note', 'authors.name', 'students.first_name', 'students.last_name',
                'students.registered_name', 'students.rut', 'courses.display_name',
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<int, mixed>
     */
    private function standardColumns(array $columns): array
    {
        $raw = static fn (string $expression, string $alias) => DB::raw("{$expression} as {$alias}");

        return [
            $raw($columns['id'], 'source_id'),
            $raw($columns['occurred_at'], 'occurred_at'),
            $raw($columns['category'], 'category'),
            $raw($columns['priority'], 'priority'),
            $raw($columns['status'], 'status'),
            $raw($columns['title'], 'title'),
            $raw($columns['detail'], 'detail'),
            $raw($columns['author_user_id'], 'author_user_id'),
            $raw($columns['author_name'], 'author_name'),
            $raw($columns['student_profile_id'], 'student_profile_id'),
            $raw($columns['student_first_name'] ?? 'students.first_name', 'student_first_name'),
            $raw($columns['student_last_name'] ?? 'students.last_name', 'student_last_name'),
            $raw($columns['student_registered_name'] ?? 'students.registered_name', 'student_registered_name'),
            $raw($columns['student_rut'] ?? 'students.rut', 'student_rut'),
            $raw($columns['course_section_id'], 'course_section_id'),
            $raw($columns['course_name'] ?? 'courses.display_name', 'course_name'),
            $raw($columns['requires_follow_up'], 'requires_follow_up'),
            $raw($columns['is_sensitive'], 'is_sensitive'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{date: string, category: string, priority: ?string, status: string, search: array<int, string>}  $columns
     */
    private function applyFilters(Builder $query, array $filters, array $columns): Builder
    {
        if (! empty($filters['date_from'])) {
            $query->where($columns['date'], '>=', $filters['date_from'].' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $exclusiveEnd = Carbon::createFromFormat('Y-m-d', $filters['date_to'])->addDay()->format('Y-m-d 00:00:00');
            $query->where($columns['date'], '<', $exclusiveEnd);
        }
        if (! empty($filters['category'])) {
            $query->where($columns['category'], $filters['category']);
        }
        if (! empty($filters['priority'])) {
            $columns['priority']
                ? $query->where($columns['priority'], $filters['priority'])
                : $query->whereRaw('1 = 0');
        }
        if (! empty($filters['status'])) {
            $query->where($columns['status'], $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($columns, $search): void {
                foreach ($columns['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $inner->{$method}($column, 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function summary(Builder $union): array
    {
        $today = today()->format('Y-m-d 00:00:00');
        $tomorrow = today()->addDay()->format('Y-m-d 00:00:00');
        $aggregate = DB::query()
            ->fromSub(clone $union, 'unified_logbooks')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN occurred_at >= ? AND occurred_at < ? THEN 1 ELSE 0 END) as today', [$today, $tomorrow])
            ->selectRaw("SUM(CASE WHEN requires_follow_up = 1 AND status <> 'cerrado' THEN 1 ELSE 0 END) as follow_up")
            ->selectRaw("SUM(CASE WHEN priority IN ('alta', 'urgente') AND status <> 'cerrado' THEN 1 ELSE 0 END) as high_priority_total")
            ->selectRaw("SUM(CASE WHEN source = 'inspectoria' THEN 1 ELSE 0 END) as inspectoria_total")
            ->selectRaw("SUM(CASE WHEN source = 'porter' THEN 1 ELSE 0 END) as porter_total")
            ->selectRaw("SUM(CASE WHEN source = 'infirmary' THEN 1 ELSE 0 END) as infirmary_total")
            ->selectRaw("SUM(CASE WHEN source = 'convivencia' THEN 1 ELSE 0 END) as convivencia_total")
            ->first();

        return [
            'total' => (int) ($aggregate?->total ?? 0),
            'today' => (int) ($aggregate?->today ?? 0),
            'follow_up' => (int) ($aggregate?->follow_up ?? 0),
            'high_priority' => (int) ($aggregate?->high_priority_total ?? 0),
            'by_source' => [
                'inspectoria' => (int) ($aggregate?->inspectoria_total ?? 0),
                'porter' => (int) ($aggregate?->porter_total ?? 0),
                'infirmary' => (int) ($aggregate?->infirmary_total ?? 0),
                'convivencia' => (int) ($aggregate?->convivencia_total ?? 0),
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function hydratePage(Collection $rows): Collection
    {
        $ids = $rows->groupBy('source')->map(fn (Collection $items) => $items->pluck('source_id')->map(fn ($id) => (int) $id)->all());
        $details = [];

        if ($ids->has('inspectoria')) {
            $details['inspectoria'] = InspectoriaDailyLog::query()
                ->with(['inspector:id,full_name', 'lateStaff:id,full_name', 'associatedCourses:id,display_name'])
                ->whereIn('id', $ids->get('inspectoria'))->get()->keyBy('id');
        }
        if ($ids->has('porter')) {
            $details['porter'] = PorterDailyLogEntry::query()
                ->whereIn('id', $ids->get('porter'))->get()->keyBy('id');
        }
        if ($ids->has('infirmary')) {
            $details['infirmary'] = InfirmaryDailyLog::query()
                ->whereIn('id', $ids->get('infirmary'))->get()->keyBy('id');
        }
        if ($ids->has('convivencia')) {
            $details['convivencia'] = ConvivenciaDailyLog::query()
                ->with(['case:id,folio,status', 'generatedDerivation:id,destination_label,status', 'inspectorStaff:id,full_name'])
                ->whereIn('id', $ids->get('convivencia'))->get()->keyBy('id');
        }

        return $rows->map(function (object $row) use ($details): array {
            $source = (string) $row->source;
            $record = $details[$source][(int) $row->source_id] ?? null;
            $registeredName = trim((string) ($row->student_registered_name ?? ''));
            $legalName = trim(implode(' ', array_filter([
                $row->student_first_name ?? null,
                $row->student_last_name ?? null,
            ])));

            return [
                'key' => $source.'-'.(int) $row->source_id,
                'source' => $source,
                'source_id' => (int) $row->source_id,
                'source_label' => self::SOURCES[$source]['label'],
                'occurred_at' => $row->occurred_at,
                'category' => $row->category,
                'priority' => $row->priority,
                'status' => $row->status,
                'title' => $row->title,
                'detail' => $row->detail,
                'author' => [
                    'id' => $row->author_user_id ? (int) $row->author_user_id : null,
                    'name' => $row->author_name ?: 'Sin responsable registrado',
                ],
                'student' => $row->student_profile_id ? [
                    'id' => (int) $row->student_profile_id,
                    'name' => $registeredName ?: ($legalName ?: 'Estudiante'),
                    'rut' => $row->student_rut,
                ] : null,
                'course' => $row->course_section_id ? [
                    'id' => (int) $row->course_section_id,
                    'name' => $row->course_name,
                ] : null,
                'requires_follow_up' => (bool) $row->requires_follow_up,
                'is_sensitive' => (bool) $row->is_sensitive,
                'extra' => $this->extraFor($source, $record),
            ];
        })->values();
    }

    /** @return array<string, mixed> */
    private function extraFor(string $source, mixed $record): array
    {
        if (! $record) {
            return [];
        }

        return match ($source) {
            'inspectoria' => [
                'follow_up_note' => $record->follow_up_note,
                'inspector' => $record->inspector?->full_name,
                'is_staff_lateness' => (bool) $record->is_staff_lateness,
                'late_staff' => $record->lateStaff?->full_name ?? $record->late_staff_name_snapshot,
                'lateness_minutes' => $record->lateness_minutes,
                'associated_courses' => $record->associatedCourses->pluck('display_name')->values()->all(),
            ],
            'porter' => [
                'shift_label' => $record->shift_label,
            ],
            'infirmary' => [
                'action_taken' => $record->action_taken,
                'follow_up_note' => $record->follow_up_note,
            ],
            'convivencia' => [
                'place' => $record->place,
                'immediate_action' => $record->immediate_action,
                'guardian_informed' => (bool) $record->guardian_informed,
                'guardian_contact_note' => $record->guardian_contact_note,
                'inspector' => $record->inspectorStaff?->full_name,
                'case_folio' => $record->case?->folio,
                'derivation_destination' => $record->generatedDerivation?->destination_label,
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function sourceCatalog(array $counts): array
    {
        return collect(self::SOURCES)
            ->map(fn (array $source, string $key) => [
                'value' => $key,
                ...$source,
                'count' => (int) ($counts[$key] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $values): array
    {
        return collect($values)
            ->map(fn (string $label, string $value) => compact('value', 'label'))
            ->values()
            ->all();
    }
}
