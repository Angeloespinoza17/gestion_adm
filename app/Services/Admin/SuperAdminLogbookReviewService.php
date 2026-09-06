<?php

namespace App\Services\Admin;

use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Infirmary\InfirmaryDailyLog;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\Operational\OperationalStaffLogEntry;
use App\Models\PorterDailyLogEntry;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityRound;
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
        'staff_logbook' => [
            'label' => 'Funcionarios',
            'description' => 'Registros personales, acuerdos, gestiones y seguimientos de funcionarios.',
            'icon' => 'bx-notepad',
            'accent' => '#2563eb',
            'route' => '/bitacora',
        ],
        'security_rounds' => [
            'label' => 'Nocheros',
            'description' => 'Rondas nocturnas, sectores revisados, actas y novedades de seguridad.',
            'icon' => 'bx-moon',
            'accent' => '#4338ca',
            'route' => '/security/shifts',
        ],
        'security_incidents' => [
            'label' => 'Incidencias nocturnas',
            'description' => 'Alertas detectadas en ronda, responsables, compromisos y estado de resolución.',
            'icon' => 'bx-error-alt',
            'accent' => '#be3652',
            'route' => '/security/incidents',
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
                    'critica' => 'Crítica',
                ]),
                'statuses' => $this->options([
                    'registrado' => 'Registrado',
                    'en_seguimiento' => 'En seguimiento',
                    'revisado' => 'Revisado',
                    'destacado' => 'Destacado',
                    'convertido_caso' => 'Convertido en caso',
                    'convertido_derivacion' => 'Convertido en derivación',
                    'cerrado' => 'Cerrado',
                    'sin_novedad' => 'Sin novedad',
                    'observado' => 'Observado',
                    'requiere_atencion' => 'Requiere atención',
                    'pendiente' => 'Pendiente',
                    'en_revision' => 'En revisión',
                    'derivada' => 'Derivada',
                    'resuelta' => 'Resuelta',
                    'descartada' => 'Descartada',
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
            'staff_logbook' => $this->staffLogbookQuery($filters),
            'security_rounds' => $this->securityRoundsQuery($filters),
            'security_incidents' => $this->securityIncidentsQuery($filters),
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

    /** @param array<string, mixed> $filters */
    private function staffLogbookQuery(array $filters): Builder
    {
        $query = DB::table('operational_staff_log_entries as logs')
            ->leftJoin('users as authors', 'authors.id', '=', 'logs.owner_user_id')
            ->selectRaw("'staff_logbook' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.occurred_at',
                'category' => 'logs.category',
                'priority' => 'NULL',
                'status' => "'registrado'",
                'title' => 'logs.title',
                'detail' => 'logs.details',
                'author_user_id' => 'logs.owner_user_id',
                'author_name' => 'COALESCE(authors.name, logs.owner_name_snapshot)',
                'student_profile_id' => 'NULL',
                'student_first_name' => 'NULL',
                'student_last_name' => 'NULL',
                'student_registered_name' => 'NULL',
                'student_rut' => 'NULL',
                'course_section_id' => 'NULL',
                'course_name' => 'NULL',
                'requires_follow_up' => "CASE WHEN logs.category = 'follow_up' THEN 1 ELSE 0 END",
                'is_sensitive' => '0',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.occurred_at',
            'category' => 'logs.category',
            'priority' => null,
            'status' => null,
            'search' => ['logs.title', 'logs.details', 'logs.custom_category', 'logs.owner_name_snapshot', 'authors.name'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function securityRoundsQuery(array $filters): Builder
    {
        $incidentRollup = DB::table('security_incidents as security_entries')
            ->leftJoin('security_incident_statuses as incident_statuses', 'incident_statuses.id', '=', 'security_entries.status_id')
            ->whereNotNull('security_entries.security_round_id')
            ->groupBy('security_entries.security_round_id')
            ->select('security_entries.security_round_id')
            ->selectRaw("CASE MAX(CASE security_entries.priority WHEN 'critica' THEN 4 WHEN 'alta' THEN 3 WHEN 'media' THEN 2 ELSE 1 END) WHEN 4 THEN 'critica' WHEN 3 THEN 'alta' WHEN 2 THEN 'media' ELSE 'baja' END as priority")
            ->selectRaw('COUNT(security_entries.id) as incident_total')
            ->selectRaw('SUM(CASE WHEN incident_statuses.is_closed = 0 THEN 1 ELSE 0 END) as open_incident_total');

        $query = DB::table('security_rounds as logs')
            ->join('security_shifts as shifts', 'shifts.id', '=', 'logs.security_shift_id')
            ->join('staff as night_staff', 'night_staff.id', '=', 'shifts.staff_id')
            ->leftJoin('users as authors', 'authors.id', '=', 'logs.recorded_by_user_id')
            ->leftJoinSub($incidentRollup, 'incident_rollup', fn ($join) => $join->on('incident_rollup.security_round_id', '=', 'logs.id'))
            ->selectRaw("'security_rounds' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.recorded_at',
                'category' => 'logs.overall_status',
                'priority' => 'incident_rollup.priority',
                'status' => 'logs.overall_status',
                'title' => "'Ronda nocturna'",
                'detail' => "COALESCE(logs.observations, 'Recorrido de seguridad registrado sin observaciones generales.')",
                'author_user_id' => 'logs.recorded_by_user_id',
                'author_name' => 'COALESCE(authors.name, logs.nochero_confirmation_name, night_staff.full_name)',
                'student_profile_id' => 'NULL',
                'student_first_name' => 'NULL',
                'student_last_name' => 'NULL',
                'student_registered_name' => 'NULL',
                'student_rut' => 'NULL',
                'course_section_id' => 'NULL',
                'course_name' => 'NULL',
                'requires_follow_up' => "CASE WHEN COALESCE(incident_rollup.open_incident_total, 0) > 0 OR logs.overall_status = 'requiere_atencion' THEN 1 ELSE 0 END",
                'is_sensitive' => '0',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.recorded_at',
            'category' => 'logs.overall_status',
            'priority' => 'incident_rollup.priority',
            'status' => 'logs.overall_status',
            'search' => [
                'logs.act_number', 'logs.observations', 'logs.nochero_confirmation_name',
                'night_staff.full_name', 'authors.name', 'shifts.coverage_label',
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function securityIncidentsQuery(array $filters): Builder
    {
        $query = DB::table('security_incidents as logs')
            ->join('security_shifts as shifts', 'shifts.id', '=', 'logs.security_shift_id')
            ->join('staff as night_staff', 'night_staff.id', '=', 'shifts.staff_id')
            ->join('security_incident_statuses as incident_statuses', 'incident_statuses.id', '=', 'logs.status_id')
            ->leftJoin('security_rounds as rounds', 'rounds.id', '=', 'logs.security_round_id')
            ->leftJoin('users as authors', 'authors.id', '=', 'logs.reported_by_user_id')
            ->leftJoin('users as responsibles', 'responsibles.id', '=', 'logs.current_responsible_user_id')
            ->selectRaw("'security_incidents' as source")
            ->addSelect($this->standardColumns([
                'id' => 'logs.id',
                'occurred_at' => 'logs.created_at',
                'category' => "COALESCE(logs.sector_name, 'Novedad de seguridad')",
                'priority' => 'logs.priority',
                'status' => 'incident_statuses.code',
                'title' => 'logs.title',
                'detail' => 'logs.description',
                'author_user_id' => 'logs.reported_by_user_id',
                'author_name' => 'COALESCE(authors.name, night_staff.full_name)',
                'student_profile_id' => 'NULL',
                'student_first_name' => 'NULL',
                'student_last_name' => 'NULL',
                'student_registered_name' => 'NULL',
                'student_rut' => 'NULL',
                'course_section_id' => 'NULL',
                'course_name' => 'NULL',
                'requires_follow_up' => 'CASE WHEN incident_statuses.is_closed = 0 THEN 1 ELSE 0 END',
                'is_sensitive' => '0',
            ]));

        return $this->applyFilters($query, $filters, [
            'date' => 'logs.created_at',
            'category' => 'logs.sector_name',
            'priority' => 'logs.priority',
            'status' => 'incident_statuses.code',
            'search' => [
                'logs.title', 'logs.description', 'logs.sector_name', 'rounds.act_number',
                'night_staff.full_name', 'authors.name', 'responsibles.name', 'incident_statuses.name',
                'shifts.coverage_label',
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
     * @param  array{date: string, category: string, priority: ?string, status: ?string, search: array<int, string>}  $columns
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
            if ($columns['status']) {
                $query->where($columns['status'], $filters['status']);
            } elseif ($filters['status'] !== 'registrado') {
                $query->whereRaw('1 = 0');
            }
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
            ->selectRaw("SUM(CASE WHEN priority IN ('alta', 'urgente', 'critica') AND status <> 'cerrado' THEN 1 ELSE 0 END) as high_priority_total")
            ->selectRaw("SUM(CASE WHEN source = 'inspectoria' THEN 1 ELSE 0 END) as inspectoria_total")
            ->selectRaw("SUM(CASE WHEN source = 'porter' THEN 1 ELSE 0 END) as porter_total")
            ->selectRaw("SUM(CASE WHEN source = 'infirmary' THEN 1 ELSE 0 END) as infirmary_total")
            ->selectRaw("SUM(CASE WHEN source = 'convivencia' THEN 1 ELSE 0 END) as convivencia_total")
            ->selectRaw("SUM(CASE WHEN source = 'staff_logbook' THEN 1 ELSE 0 END) as staff_logbook_total")
            ->selectRaw("SUM(CASE WHEN source = 'security_rounds' THEN 1 ELSE 0 END) as security_rounds_total")
            ->selectRaw("SUM(CASE WHEN source = 'security_incidents' THEN 1 ELSE 0 END) as security_incidents_total")
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
                'staff_logbook' => (int) ($aggregate?->staff_logbook_total ?? 0),
                'security_rounds' => (int) ($aggregate?->security_rounds_total ?? 0),
                'security_incidents' => (int) ($aggregate?->security_incidents_total ?? 0),
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
        if ($ids->has('staff_logbook')) {
            $details['staff_logbook'] = OperationalStaffLogEntry::query()
                ->with('staff.cargo:id,name')
                ->whereIn('id', $ids->get('staff_logbook'))->get()->keyBy('id');
        }
        if ($ids->has('security_rounds')) {
            $details['security_rounds'] = SecurityRound::query()
                ->with([
                    'shift:id,staff_id,scheduled_start_at,scheduled_end_at,status,coverage_label',
                    'shift.staff:id,full_name',
                    'recordedBy:id,name,email',
                    'sectors:id,security_round_id,sector_name,sector_state,observations,display_order',
                    'incidents:id,security_round_id,status_id,current_responsible_user_id,priority,title,description,sector_name',
                    'incidents.status:id,code,name,color,is_closed',
                    'incidents.currentResponsible:id,name,email',
                ])
                ->withCount(['sectors', 'incidents', 'evidences'])
                ->whereIn('id', $ids->get('security_rounds'))->get()->keyBy('id');
        }
        if ($ids->has('security_incidents')) {
            $details['security_incidents'] = SecurityIncident::query()
                ->with([
                    'shift:id,staff_id,scheduled_start_at,scheduled_end_at,status,coverage_label',
                    'shift.staff:id,full_name',
                    'round:id,security_shift_id,round_number,recorded_at,act_number',
                    'status:id,code,name,color,is_closed',
                    'reportedBy:id,name,email',
                    'currentResponsible:id,name,email',
                ])
                ->withCount(['comments', 'evidences', 'assignments'])
                ->whereIn('id', $ids->get('security_incidents'))->get()->keyBy('id');
        }

        return $rows->map(function (object $row) use ($details): array {
            $source = (string) $row->source;
            $record = $details[$source][(int) $row->source_id] ?? null;
            $registeredName = trim((string) ($row->student_registered_name ?? ''));
            $legalName = trim(implode(' ', array_filter([
                $row->student_first_name ?? null,
                $row->student_last_name ?? null,
            ])));

            $title = $row->title;
            $detail = $row->detail;
            if ($source === 'security_rounds' && $record instanceof SecurityRound) {
                $nightStaffName = $record->shift?->staff?->full_name ?: $record->nochero_confirmation_name;
                $title = 'Ronda nocturna #'.$record->round_number.($nightStaffName ? ' · '.$nightStaffName : '');
                if (! trim((string) $record->observations)) {
                    $detail = 'Recorrido registrado en '.$record->sectors_count.' '.($record->sectors_count === 1 ? 'sector' : 'sectores')
                        .($record->incidents_count ? ', con '.$record->incidents_count.' '.($record->incidents_count === 1 ? 'novedad.' : 'novedades.') : ', sin novedades.');
                }
            }

            return [
                'key' => $source.'-'.(int) $row->source_id,
                'source' => $source,
                'source_id' => (int) $row->source_id,
                'source_label' => self::SOURCES[$source]['label'],
                'occurred_at' => $row->occurred_at,
                'category' => $source === 'staff_logbook' && $record instanceof OperationalStaffLogEntry
                    ? $record->categoryLabel()
                    : $row->category,
                'priority' => $row->priority,
                'status' => $row->status,
                'title' => $title,
                'detail' => $detail,
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
            'staff_logbook' => [
                'staff_position' => $record->staff?->cargo?->name,
                'was_edited' => $record->created_at && $record->updated_at
                    ? ! $record->created_at->equalTo($record->updated_at)
                    : false,
            ],
            'security_rounds' => [
                'round_number' => $record->round_number,
                'act_number' => $record->act_number,
                'nochero_name' => $record->shift?->staff?->full_name ?: $record->nochero_confirmation_name,
                'confirmation_name' => $record->nochero_confirmation_name,
                'shift_status' => $record->shift?->status,
                'shift_window' => $record->shift
                    ? trim(($record->shift->scheduled_start_at?->format('d-m-Y H:i') ?: '').' a '.($record->shift->scheduled_end_at?->format('d-m-Y H:i') ?: ''))
                    : null,
                'coverage_label' => $record->shift?->coverage_label,
                'sector_count' => (int) $record->sectors_count,
                'incident_count' => (int) $record->incidents_count,
                'evidence_count' => (int) $record->evidences_count,
                'geolocation_recorded' => $record->latitude !== null && $record->longitude !== null,
                'location_accuracy' => $record->location_accuracy,
                'sectors' => $record->sectors->map(fn ($sector) => [
                    'name' => $sector->sector_name,
                    'state' => $sector->sector_state,
                    'observations' => $sector->observations,
                ])->values()->all(),
                'incidents' => $record->incidents->map(fn ($incident) => [
                    'id' => $incident->id,
                    'title' => $incident->title,
                    'description' => $incident->description,
                    'priority' => $incident->priority,
                    'sector' => $incident->sector_name,
                    'status' => $incident->status?->name,
                    'is_closed' => (bool) $incident->status?->is_closed,
                    'responsible' => $incident->currentResponsible?->name,
                ])->values()->all(),
            ],
            'security_incidents' => [
                'round_number' => $record->round?->round_number,
                'act_number' => $record->round?->act_number,
                'nochero_name' => $record->shift?->staff?->full_name,
                'shift_status' => $record->shift?->status,
                'shift_window' => $record->shift
                    ? trim(($record->shift->scheduled_start_at?->format('d-m-Y H:i') ?: '').' a '.($record->shift->scheduled_end_at?->format('d-m-Y H:i') ?: ''))
                    : null,
                'coverage_label' => $record->shift?->coverage_label,
                'sector_name' => $record->sector_name,
                'status_label' => $record->status?->name,
                'status_color' => $record->status?->color,
                'is_closed' => (bool) $record->status?->is_closed,
                'requires_immediate_attention' => (bool) $record->requires_immediate_attention,
                'response_due_at' => $record->response_due_at?->format('Y-m-d H:i'),
                'responded_at' => $record->responded_at?->format('Y-m-d H:i'),
                'resolved_at' => $record->resolved_at?->format('Y-m-d H:i'),
                'responsible' => $record->currentResponsible?->name,
                'response_summary' => $record->response_summary,
                'closure_evidence_notes' => $record->closure_evidence_notes,
                'comments_count' => (int) $record->comments_count,
                'evidence_count' => (int) $record->evidences_count,
                'assignments_count' => (int) $record->assignments_count,
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
