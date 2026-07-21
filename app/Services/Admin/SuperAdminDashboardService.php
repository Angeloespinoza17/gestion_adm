<?php

namespace App\Services\Admin;

use App\Models\SystemModule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SuperAdminDashboardService
{
    private const PENDING_STATUSES = [
        'abierta',
        'activo',
        'activa',
        'asignado',
        'asignada',
        'borrador',
        'en_curso',
        'en_ejecucion',
        'en_proceso',
        'en_revision',
        'en_seguimiento',
        'ingresado',
        'observado',
        'pendiente',
        'pendiente_direccion',
        'pendiente_jefatura',
        'pendiente_regularizacion',
        'pendiente_rrhh',
        'planificado',
        'planificada',
        'programado',
        'reportada',
        'solicitada',
    ];

    private const FINAL_STATUSES = [
        'anulado',
        'anulada',
        'archivado',
        'archivada',
        'cancelado',
        'cancelada',
        'cerrado',
        'cerrada',
        'completado',
        'completada',
        'devuelto',
        'devuelta',
        'ejecutado',
        'ejecutada',
        'eliminado',
        'eliminada',
        'finalizado',
        'finalizada',
        'pagado',
        'pagada',
        'rechazado',
        'rechazada',
        'resuelto',
        'resuelta',
        'retirado',
        'retirada',
        'terminado',
        'terminada',
    ];

    private const ALERT_STATUSES = [
        'alta',
        'alta_confidencialidad',
        'atrasado',
        'critico',
        'crítico',
        'emergencia',
        'observado',
        'por_reponer',
        'por_vencer',
        'requiere_atencion',
        'urgente',
        'vencido',
        'vencida',
    ];

    private const STATUS_COLUMNS = [
        'status',
        'state',
        'case_status',
        'general_status',
        'availability_status',
        'review_status',
        'attendance_status',
        'payroll_status',
        'follow_up_status',
        'overall_status',
        'compliance_status',
        'validation_status',
        'approval_status',
        'priority',
        'criticality',
    ];

    private const RECENT_DATE_COLUMNS = [
        'updated_at',
        'created_at',
        'attended_at',
        'administered_at',
        'called_at',
        'entered_at',
        'exited_at',
        'received_at',
        'delivered_at',
        'logged_at',
        'occurred_at',
        'recorded_at',
        'scheduled_start_at',
        'scheduled_end_at',
        'submitted_at',
        'followed_at',
        'requested_at',
        'borrowed_at',
        'withdrawn_at',
        'start_at',
        'starts_at',
        'ends_at',
        'moved_at',
        'movement_date',
        'effective_date',
        'performed_at',
        'maintenance_date',
        'changed_at',
        'issued_at',
        'registered_at',
        'activated_at',
        'due_at',
        'due_date',
        'reported_at',
    ];

    private const OVERDUE_DATE_COLUMNS = [
        'due_at',
        'due_date',
        'expires_at',
        'end_date',
        'next_review_at',
        'expected_return_at',
        'pickup_at',
        'response_due_at',
        'medical_authorization_expires_at',
        'guardian_authorization_expires_at',
        'warranty_expires_at',
    ];

    public function build(int $periodDays = 30): array
    {
        $periodDays = $this->normalizePeriodDays($periodDays);
        $from = CarbonImmutable::now()->subDays($periodDays);
        $modules = $this->moduleSummaries($from);
        $generalMetrics = $this->generalMetrics($modules, $periodDays);

        return [
            'meta' => [
                'generated_at' => CarbonImmutable::now()->toDateTimeString(),
                'period_days' => $periodDays,
                'period_from' => $from->toDateString(),
                'period_to' => CarbonImmutable::now()->toDateString(),
            ],
            'metrics' => $generalMetrics,
            'charts' => [
                'records_by_module' => $this->chartRows($modules, 'total_records'),
                'recent_by_module' => $this->chartRows($modules, 'recent_records'),
                'attention_by_module' => $this->chartRows($modules, 'attention_records'),
                'operational_by_module' => $this->chartRows($modules, 'operational_rows_count'),
                'today_by_module' => $this->quickMetricChart($modules, 'today'),
                'follow_up_by_module' => $this->quickMetricChart($modules, 'follow_up'),
            ],
            'alerts' => $this->insights($modules),
            'modules' => $modules->values()->all(),
        ];
    }

    public function buildReport(string $scope = 'general', ?string $moduleSlug = null, int $periodDays = 30, array $filters = []): ?array
    {
        $data = $this->build($periodDays);

        if ($scope === 'module') {
            $module = collect($data['modules'])->firstWhere('slug', $moduleSlug);

            return $module ? $this->moduleReport($data, $module) : null;
        }

        return $this->generalReport($data, $filters);
    }

    private function moduleSummaries(CarbonImmutable $from): Collection
    {
        $definitions = $this->moduleDefinitions();
        $rootModules = SystemModule::query()
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query
                    ->orderBy('sort_order')
                    ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'active', 'sort_order']);
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order', 'active', 'parent_id']);

        return $rootModules->map(function (SystemModule $module) use ($definitions, $from) {
            $definition = $definitions[$module->slug] ?? ['tables' => []];
            $tables = collect($definition['tables'] ?? [])
                ->map(fn (array $table) => $this->tableMetrics($table['table'], $table['label'] ?? $this->humanize($table['table']), $from))
                ->filter(fn (?array $table) => $table !== null)
                ->values();

            $total = $tables->sum('total');
            $pending = $tables->sum('pending');
            $overdue = $tables->sum('overdue');
            $alerts = $tables->sum('alerts');
            $recent = $tables->sum('recent');
            $active = $tables->sum('active');
            $attention = $pending + $overdue + $alerts;
            $statusSummary = $this->mergeStatusSummaries($tables);
            $operationalSections = $this->operationalSections($module->slug, $from);
            $operationalRowsCount = collect($operationalSections)->sum(fn (array $section) => count($section['rows'] ?? []));
            $quickReport = $this->quickReport($module->slug, $from, $operationalSections);

            return [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'icon' => $module->icon ?: ($definition['icon'] ?? 'bx-grid-alt'),
                'route' => $this->moduleRoute($module),
                'active' => (bool) $module->active,
                'children_count' => $module->children->count(),
                'children' => $module->children->map(fn (SystemModule $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'route' => $child->frontend_route,
                    'active' => (bool) $child->active,
                ])->values()->all(),
                'metrics' => [
                    'total_records' => $total,
                    'active_records' => $active,
                    'recent_records' => $recent,
                    'pending_records' => $pending,
                    'overdue_records' => $overdue,
                    'alert_records' => $alerts,
                    'attention_records' => $attention,
                    'completion_rate' => $total > 0 ? max(0, round((($total - $pending - $overdue) / $total) * 100, 1)) : 0,
                ],
                'health_status' => $this->healthStatus($total, $pending, $overdue, $alerts),
                'tables' => $tables->all(),
                'tables_count' => $tables->count(),
                'status_summary' => $statusSummary,
                'operational_sections' => $operationalSections,
                'operational_rows_count' => $operationalRowsCount,
                'quick_report' => $quickReport,
                'report_metrics' => $quickReport['metrics'],
                'report_summary' => $quickReport['summary'],
                'report_title' => $quickReport['title'],
                'operational_summary' => collect($operationalSections)
                    ->map(fn (array $section) => [
                        'title' => $section['title'],
                        'count' => count($section['rows'] ?? []),
                        'route' => $section['route'] ?? null,
                    ])
                    ->values()
                    ->all(),
                'last_activity_at' => $this->latestActivity($tables),
            ];
        });
    }

    private function tableMetrics(string $table, string $label, CarbonImmutable $from): ?array
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        try {
            $columns = Schema::getColumnListing($table);
            $query = $this->baseQuery($table);
            $statusColumn = $this->firstExistingColumn($columns, self::STATUS_COLUMNS);
            $recentColumn = $this->firstExistingColumn($columns, self::RECENT_DATE_COLUMNS);
            $activeColumn = $this->firstExistingColumn($columns, ['active', 'is_active']);

            $overdue = $this->countOverdue($table, $columns, $statusColumn);
            $alertStatusCount = $statusColumn ? $this->countStatusValues($table, $statusColumn, self::ALERT_STATUSES) : 0;

            return [
                'table' => $table,
                'label' => $label,
                'total' => (int) (clone $query)->count(),
                'active' => $activeColumn ? (int) (clone $query)->where($activeColumn, true)->count() : 0,
                'recent' => $recentColumn ? (int) (clone $query)->where($recentColumn, '>=', $from->toDateTimeString())->count() : 0,
                'pending' => $statusColumn ? $this->countStatusValues($table, $statusColumn, self::PENDING_STATUSES) : 0,
                'overdue' => $overdue,
                'alerts' => $alertStatusCount + $overdue,
                'status_column' => $statusColumn,
                'status_breakdown' => $statusColumn ? $this->statusBreakdown($table, $statusColumn) : [],
                'recent_column' => $recentColumn,
                'last_activity_at' => $this->maxDateValue($table, $recentColumn),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function generalMetrics(Collection $modules, int $periodDays): array
    {
        $quickMetrics = $modules->flatMap(fn (array $module) => $module['report_metrics'] ?? []);

        return [
            'period_days' => $periodDays,
            'modules_total' => $this->safeCount('system_modules'),
            'modules_active' => $this->safeCount('system_modules', ['active' => true]),
            'users_total' => $this->safeCount('users'),
            'users_active' => $this->safeCount('users', ['active' => true]),
            'roles_total' => $this->safeCount('roles'),
            'permissions_total' => $this->safeCount('permissions'),
            'students_total' => $this->safeCount('student_profiles'),
            'staff_total' => $this->safeCount('staff'),
            'records_total' => (int) $modules->sum(fn (array $module) => $module['metrics']['total_records']),
            'recent_records' => (int) $modules->sum(fn (array $module) => $module['metrics']['recent_records']),
            'pending_records' => (int) $modules->sum(fn (array $module) => $module['metrics']['pending_records']),
            'overdue_records' => (int) $modules->sum(fn (array $module) => $module['metrics']['overdue_records']),
            'alert_records' => (int) $modules->sum(fn (array $module) => $module['metrics']['alert_records']),
            'operational_sections_total' => (int) $modules->sum(fn (array $module) => count($module['operational_sections'] ?? [])),
            'operational_rows_total' => (int) $modules->sum(fn (array $module) => $module['operational_rows_count'] ?? 0),
            'modules_with_operational_view' => (int) $modules->filter(fn (array $module) => count($module['operational_sections'] ?? []) > 0)->count(),
            'modules_with_reports' => (int) $modules->filter(fn (array $module) => count($module['report_metrics'] ?? []) > 0)->count(),
            'quick_indicators_total' => (int) $quickMetrics->count(),
            'today_activity_total' => (int) $quickMetrics->filter(fn (array $metric) => ($metric['scope'] ?? null) === 'today')->sum('value'),
            'follow_up_total' => (int) $quickMetrics->filter(fn (array $metric) => ($metric['scope'] ?? null) === 'follow_up')->sum('value'),
        ];
    }

    private function generalReport(array $data, array $filters = []): array
    {
        $modules = $this->filterReportModules(collect($data['modules']), $filters);
        $quickMetrics = $modules->flatMap(fn (array $module) => $module['report_metrics'] ?? []);

        return [
            'meta' => $data['meta'],
            'scope' => 'general',
            'title' => 'Reporte general de gestión superadmin',
            'subtitle' => 'Consolidado de informes rápidos, actividad de hoy y puntos que requieren seguimiento por área.',
            'filters' => [
                'module_slug' => $filters['module_slug'] ?? null,
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'sections' => [
                [
                    'title' => 'Resumen ejecutivo',
                    'headers' => ['Indicador', 'Valor'],
                    'rows' => [
                        ['Módulos incluidos', $modules->count()],
                        ['Áreas con informe rápido', $modules->filter(fn (array $module) => count($module['report_metrics'] ?? []) > 0)->count()],
                        ['Indicadores operativos disponibles', $quickMetrics->count()],
                        ['Actividad registrada hoy', $quickMetrics->filter(fn (array $metric) => ($metric['scope'] ?? null) === 'today')->sum('value')],
                        ['Puntos que requieren seguimiento', $quickMetrics->filter(fn (array $metric) => ($metric['scope'] ?? null) === 'follow_up')->sum('value')],
                        ['Secciones de detalle operativo', $modules->sum(fn (array $module) => count($module['operational_sections'] ?? []))],
                    ],
                ],
                [
                    'title' => 'Indicadores por módulo',
                    'headers' => ['Módulo', 'Indicador', 'Valor', 'Detalle'],
                    'rows' => $modules
                        ->flatMap(fn (array $module) => collect($module['report_metrics'] ?? [])->map(fn (array $metric) => [
                            $module['name'],
                            $metric['label'],
                            $metric['value'],
                            $metric['detail'],
                        ]))
                        ->values()
                        ->all(),
                ],
                [
                    'title' => 'Detalle operativo destacado',
                    'headers' => ['Módulo', 'Sección', 'Detalle'],
                    'rows' => $modules
                        ->flatMap(fn (array $module) => collect($module['operational_sections'] ?? [])
                            ->flatMap(fn (array $section) => collect($section['rows'] ?? [])->map(fn (array $row) => [
                                $module['name'],
                                $section['title'],
                                implode(' | ', $row['cells'] ?? []),
                            ])))
                        ->take(80)
                        ->values()
                        ->all(),
                ],
                [
                    'title' => 'Alertas relevantes',
                    'headers' => ['Módulo', 'Tipo', 'Cantidad', 'Detalle'],
                    'rows' => collect($this->insights($modules))->map(fn (array $alert) => [
                        $alert['module'],
                        $alert['label'],
                        $alert['value'],
                        $alert['detail'],
                    ])->values()->all(),
                ],
            ],
        ];
    }

    private function filterReportModules(Collection $modules, array $filters): Collection
    {
        $moduleSlug = $filters['module_slug'] ?? null;
        $status = $filters['status'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        return $modules
            ->filter(fn (array $module) => ! $moduleSlug || $module['slug'] === $moduleSlug)
            ->filter(fn (array $module) => ! $status || $module['health_status'] === $status)
            ->filter(function (array $module) use ($search) {
                if ($search === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', [
                    $module['name'] ?? '',
                    $module['report_summary'] ?? '',
                    $module['report_title'] ?? '',
                ]));

                return Str::contains($haystack, Str::lower($search));
            })
            ->values();
    }

    private function moduleReport(array $data, array $module): array
    {
        return [
            'meta' => $data['meta'],
            'scope' => 'module',
            'module' => $module,
            'title' => 'Reporte de módulo: ' . $module['name'],
            'subtitle' => $module['report_summary'] ?: 'Informe rápido e información relevante del módulo seleccionado.',
            'sections' => array_merge([
                [
                    'title' => $module['report_title'] ?: 'Informe rápido',
                    'headers' => ['Indicador', 'Valor', 'Detalle'],
                    'rows' => collect($module['report_metrics'] ?? [])->map(fn (array $metric) => [
                        $metric['label'],
                        $metric['value'],
                        $metric['detail'],
                    ])->values()->all(),
                ],
            ], collect($module['operational_sections'] ?? [])->map(fn (array $section) => [
                'title' => $section['title'],
                'headers' => $section['headers'],
                'rows' => collect($section['rows'] ?? [])->map(fn (array $row) => $row['cells'] ?? [])->values()->all(),
            ])->values()->all()),
        ];
    }

    private function insights(Collection $modules): array
    {
        return $modules
            ->flatMap(function (array $module) {
                $items = [];

                if ($module['metrics']['overdue_records'] > 0) {
                    $items[] = [
                        'module' => $module['name'],
                        'slug' => $module['slug'],
                        'type' => 'overdue',
                        'severity' => 'danger',
                        'label' => 'Situaciones vencidas',
                        'value' => $module['metrics']['overdue_records'],
                        'detail' => 'Requiere revisión por fechas comprometidas o vencimientos.',
                    ];
                }

                if ($module['metrics']['alert_records'] > 0) {
                    $items[] = [
                        'module' => $module['name'],
                        'slug' => $module['slug'],
                        'type' => 'alerts',
                        'severity' => 'warning',
                        'label' => 'Alertas operativas',
                        'value' => $module['metrics']['alert_records'],
                        'detail' => 'Incluye estados críticos, urgentes, observados o por vencer.',
                    ];
                }

                if ($module['metrics']['pending_records'] > 0) {
                    $items[] = [
                        'module' => $module['name'],
                        'slug' => $module['slug'],
                        'type' => 'pending',
                        'severity' => 'info',
                        'label' => 'Pendientes',
                        'value' => $module['metrics']['pending_records'],
                        'detail' => 'Situaciones abiertas, solicitadas, en revisión o en proceso.',
                    ];
                }

                return $items;
            })
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->all();
    }

    private function chartRows(Collection $modules, string $metric): array
    {
        return $modules
            ->map(fn (array $module) => [
                'label' => $module['name'],
                'value' => $module['metrics'][$metric] ?? $module[$metric] ?? 0,
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function quickMetricChart(Collection $modules, string $scope): array
    {
        return $modules
            ->map(fn (array $module) => [
                'label' => $module['name'],
                'value' => (int) collect($module['report_metrics'] ?? [])
                    ->filter(fn (array $metric) => ($metric['scope'] ?? null) === $scope)
                    ->sum('value'),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function quickReport(string $slug, CarbonImmutable $from, array $operationalSections): array
    {
        return match ($slug) {
            'porter' => $this->porterQuickReport($from),
            'spaces' => $this->spacesQuickReport($from),
            'maintenance' => $this->maintenanceQuickReport($from),
            'infirmary' => $this->infirmaryQuickReport($from),
            'biblioteca' => $this->libraryQuickReport($from),
            'security' => $this->securityQuickReport($from),
            'tasks' => $this->tasksQuickReport($from),
            'staff_permissions' => $this->staffPermissionsQuickReport($from),
            'students' => $this->studentsQuickReport($from),
            'inventory' => $this->inventoryQuickReport($from),
            default => $this->genericQuickReport($operationalSections),
        };
    }

    private function porterQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $visitsToday = $this->countRows('porter_visits', fn ($query) => $query->whereDate('entered_at', $today));
        $openVisits = $this->countRows('porter_visits', fn ($query) => $query->whereIn('status', ['en_curso', 'ingresado']));
        $providersToday = $this->countRows('porter_external_service_entries', fn ($query) => $query->whereDate('entered_at', $today));
        $pendingItems = $this->countRows('porter_received_items', fn ($query) => $query
            ->whereNull('delivered_at')
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $goodsToday = $this->countRows('porter_goods_movements', fn ($query) => $query->whereDate('moved_at', $today));
        $withdrawalsToday = $this->countRows('porter_student_withdrawals', fn ($query) => $query->whereDate('withdrawn_at', $today));
        $priorityLogs = $this->countRows('porter_daily_log_entries', fn ($query) => $query
            ->where('logged_at', '>=', $from->toDateTimeString())
            ->whereIn(DB::raw('LOWER(priority)'), ['alta', 'urgente', 'critica', 'crítica']));

        return $this->quickReportPayload(
            'Informe rápido de portería',
            "Hoy se registran {$visitsToday} visitas, {$providersToday} proveedores y {$withdrawalsToday} retiros de estudiantes.",
            [
                $this->quickMetric('porter_visits_today', 'Visitas hoy', $visitsToday, 'Personas registradas en control de visitas durante el día.', 'bx-log-in-circle', 'primary', 'today'),
                $this->quickMetric('porter_open_visits', 'Visitas en curso', $openVisits, 'Personas que figuran ingresadas y sin salida registrada.', 'bx-door-open', 'warning', 'follow_up'),
                $this->quickMetric('porter_providers_today', 'Proveedores hoy', $providersToday, 'Servicios externos o proveedores que ingresaron hoy.', 'bx-hard-hat', 'info', 'today'),
                $this->quickMetric('porter_pending_items', 'Paquetes pendientes', $pendingItems, 'Objetos recibidos en portería sin entrega registrada.', 'bx-package', 'warning', 'follow_up'),
                $this->quickMetric('porter_goods_today', 'Mercadería hoy', $goodsToday, 'Movimientos de carga o mercadería registrados hoy.', 'bx-transfer', 'success', 'today'),
                $this->quickMetric('porter_withdrawals_today', 'Retiros hoy', $withdrawalsToday, 'Estudiantes retirados desde portería durante el día.', 'bx-user-minus', 'secondary', 'today'),
                $this->quickMetric('porter_priority_logs', 'Novedades prioritarias', $priorityLogs, 'Bitácoras de portería con prioridad alta o urgente en el período.', 'bx-error-circle', 'danger', 'follow_up'),
            ]
        );
    }

    private function spacesQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();
        $now = CarbonImmutable::now()->toDateTimeString();

        $reservationsToday = $this->countRows('dependency_reservations', fn ($query) => $query->whereDate('starts_at', $today));
        $upcoming = $this->countRows('dependency_reservations', fn ($query) => $query
            ->where('starts_at', '>=', $now)
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $pending = $this->countRows('dependency_reservations', fn ($query) => $query->whereIn(DB::raw('LOWER(status)'), self::PENDING_STATUSES));
        $dependenciesToday = $this->distinctCount('dependency_reservations', 'maintenance_dependency_id', fn ($query) => $query->whereDate('starts_at', $today));

        return $this->quickReportPayload(
            'Informe rápido de espacios',
            "Hoy hay {$reservationsToday} reservas en {$dependenciesToday} espacios; {$pending} solicitudes requieren revisión.",
            [
                $this->quickMetric('spaces_today', 'Reservas hoy', $reservationsToday, 'Espacios agendados para el día.', 'bx-calendar-event', 'primary', 'today'),
                $this->quickMetric('spaces_dependencies_today', 'Espacios usados hoy', $dependenciesToday, 'Dependencias distintas con reservas hoy.', 'bx-building-house', 'success', 'today'),
                $this->quickMetric('spaces_upcoming', 'Próximas reservas', $upcoming, 'Reservas futuras no cerradas ni canceladas.', 'bx-calendar-week', 'info', 'period'),
                $this->quickMetric('spaces_pending', 'Reservas por aprobar', $pending, 'Solicitudes pendientes, programadas o en revisión.', 'bx-time-five', 'warning', 'follow_up'),
            ]
        );
    }

    private function maintenanceQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $open = $this->countRows('maintenance_work_orders', fn ($query) => $query->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $overdue = $this->countRows('maintenance_work_orders', fn ($query) => $query
            ->whereDate('due_date', '<', $today)
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $dueToday = $this->countRows('maintenance_work_orders', fn ($query) => $query
            ->whereDate('due_date', $today)
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $highPriority = $this->countRows('maintenance_work_orders', fn ($query) => $query
            ->whereIn(DB::raw('LOWER(priority)'), ['alta', 'urgente', 'critica', 'crítica'])
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));

        return $this->quickReportPayload(
            'Informe rápido de mantención',
            "{$open} órdenes abiertas; {$overdue} vencidas y {$dueToday} con vencimiento hoy.",
            [
                $this->quickMetric('maintenance_open', 'Órdenes abiertas', $open, 'Solicitudes de mantención aún no finalizadas.', 'bx-wrench', 'warning', 'follow_up'),
                $this->quickMetric('maintenance_overdue', 'Vencidas', $overdue, 'Órdenes abiertas con fecha comprometida vencida.', 'bx-error', 'danger', 'follow_up'),
                $this->quickMetric('maintenance_due_today', 'Vencen hoy', $dueToday, 'Órdenes que deben resolverse durante el día.', 'bx-time-five', 'info', 'today'),
                $this->quickMetric('maintenance_high_priority', 'Alta prioridad', $highPriority, 'Trabajos abiertos marcados como alta prioridad.', 'bx-alarm-exclamation', 'danger', 'follow_up'),
            ]
        );
    }

    private function infirmaryQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $attentionsToday = $this->countRows('infirmary_attentions', fn ($query) => $query->whereDate('attended_at', $today));
        $open = $this->countRows('infirmary_attentions', fn ($query) => $query->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $accidents = $this->countRows('infirmary_accidents', fn ($query) => $query->where('occurred_at', '>=', $from->toDateTimeString()));
        $pendingCalls = $this->countRows('infirmary_attention_calls', fn ($query) => $query->whereIn(DB::raw('LOWER(call_status)'), self::PENDING_STATUSES));

        return $this->quickReportPayload(
            'Informe rápido de enfermería',
            "Hoy hay {$attentionsToday} atenciones; {$open} casos siguen abiertos.",
            [
                $this->quickMetric('infirmary_today', 'Atenciones hoy', $attentionsToday, 'Estudiantes atendidos durante el día.', 'bx-plus-medical', 'primary', 'today'),
                $this->quickMetric('infirmary_open', 'Casos abiertos', $open, 'Atenciones sin cierre o finalización.', 'bx-folder-open', 'warning', 'follow_up'),
                $this->quickMetric('infirmary_accidents', 'Accidentes del período', $accidents, 'Accidentes escolares reportados en el rango seleccionado.', 'bx-shield-quarter', 'danger', 'follow_up'),
                $this->quickMetric('infirmary_pending_calls', 'Llamados pendientes', $pendingCalls, 'Contactos con apoderados pendientes o en seguimiento.', 'bx-phone-call', 'warning', 'follow_up'),
            ]
        );
    }

    private function libraryQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $loansToday = $this->countRows('biblioteca_prestamos', fn ($query) => $query->whereDate('borrowed_at', $today));
        $overdue = $this->countRows('biblioteca_prestamos', fn ($query) => $query
            ->whereDate('due_at', '<', $today)
            ->whereNull('returned_at')
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $dueToday = $this->countRows('biblioteca_prestamos', fn ($query) => $query
            ->whereDate('due_at', $today)
            ->whereNull('returned_at'));
        $pendingReservations = $this->countRows('biblioteca_reservas', fn ($query) => $query->whereIn(DB::raw('LOWER(status)'), self::PENDING_STATUSES));

        return $this->quickReportPayload(
            'Informe rápido de biblioteca',
            "Hoy se registran {$loansToday} préstamos; {$overdue} devoluciones están atrasadas.",
            [
                $this->quickMetric('library_loans_today', 'Préstamos hoy', $loansToday, 'Material prestado durante el día.', 'bx-book-reader', 'primary', 'today'),
                $this->quickMetric('library_due_today', 'Devoluciones hoy', $dueToday, 'Préstamos cuya devolución vence hoy.', 'bx-calendar-check', 'info', 'today'),
                $this->quickMetric('library_overdue', 'Devoluciones atrasadas', $overdue, 'Material con fecha vencida y sin devolución registrada.', 'bx-error-circle', 'danger', 'follow_up'),
                $this->quickMetric('library_pending_reservations', 'Reservas pendientes', $pendingReservations, 'Solicitudes de biblioteca pendientes o en revisión.', 'bx-bookmark', 'warning', 'follow_up'),
            ]
        );
    }

    private function securityQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $shiftsToday = $this->countRows('security_shifts', fn ($query) => $query->whereDate('scheduled_start_at', $today));
        $activeShifts = $this->countRows('security_shifts', fn ($query) => $query->whereIn(DB::raw('LOWER(status)'), ['en_curso', 'activo', 'activa']));
        $openIncidents = $this->countRows('security_incidents', fn ($query) => $query
            ->join('security_incident_statuses as st', 'st.id', '=', 'security_incidents.status_id')
            ->where('st.is_closed', false));
        $immediate = $this->countRows('security_incidents', fn ($query) => $query->where('requires_immediate_attention', true));
        $roundsToday = $this->countRows('security_rounds', fn ($query) => $query->whereDate('recorded_at', $today));

        return $this->quickReportPayload(
            'Informe rápido de seguridad',
            "Hoy hay {$shiftsToday} turnos y {$roundsToday} rondas registradas; {$openIncidents} novedades siguen abiertas.",
            [
                $this->quickMetric('security_shifts_today', 'Turnos hoy', $shiftsToday, 'Turnos de seguridad programados para el día.', 'bx-calendar', 'primary', 'today'),
                $this->quickMetric('security_active_shifts', 'Turnos activos', $activeShifts, 'Turnos actualmente en curso.', 'bx-walk', 'info', 'follow_up'),
                $this->quickMetric('security_rounds_today', 'Rondas hoy', $roundsToday, 'Rondas registradas durante el día.', 'bx-map-pin', 'success', 'today'),
                $this->quickMetric('security_open_incidents', 'Novedades abiertas', $openIncidents, 'Incidentes de seguridad sin cierre.', 'bx-shield', 'warning', 'follow_up'),
                $this->quickMetric('security_immediate', 'Atención inmediata', $immediate, 'Novedades marcadas para atención inmediata.', 'bx-error', 'danger', 'follow_up'),
            ]
        );
    }

    private function tasksQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $open = $this->countRows('tasks', fn ($query) => $query
            ->whereNull('completed_at')
            ->whereNotIn(DB::raw('LOWER(status)'), self::FINAL_STATUSES));
        $dueToday = $this->countRows('tasks', fn ($query) => $query
            ->whereDate('due_date', $today)
            ->whereNull('completed_at'));
        $overdue = $this->countRows('tasks', fn ($query) => $query
            ->whereDate('due_date', '<', $today)
            ->whereNull('completed_at'));
        $completedToday = $this->countRows('tasks', fn ($query) => $query->whereDate('completed_at', $today));

        return $this->quickReportPayload(
            'Informe rápido de tareas',
            "{$open} tareas abiertas; {$dueToday} vencen hoy y {$overdue} están atrasadas.",
            [
                $this->quickMetric('tasks_open', 'Tareas abiertas', $open, 'Tareas aún no completadas.', 'bx-task', 'warning', 'follow_up'),
                $this->quickMetric('tasks_due_today', 'Vencen hoy', $dueToday, 'Tareas con plazo para el día.', 'bx-time-five', 'info', 'today'),
                $this->quickMetric('tasks_overdue', 'Atrasadas', $overdue, 'Tareas no completadas con vencimiento anterior.', 'bx-error-circle', 'danger', 'follow_up'),
                $this->quickMetric('tasks_completed_today', 'Completadas hoy', $completedToday, 'Tareas cerradas durante el día.', 'bx-check-circle', 'success', 'today'),
            ]
        );
    }

    private function staffPermissionsQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $submittedToday = $this->countRows('permission_requests', fn ($query) => $query->whereDate('submitted_at', $today));
        $pending = $this->countRows('permission_requests', fn ($query) => $query->whereIn(DB::raw('LOWER(status)'), self::PENDING_STATUSES));
        $currentAbsences = $this->countRows('permission_requests', fn ($query) => $query
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereIn(DB::raw('LOWER(status)'), ['aprobado', 'aprobada', 'ejecutado', 'ejecutada']));
        $urgent = $this->countRows('permission_requests', fn ($query) => $query->where('urgency', true)->whereIn(DB::raw('LOWER(status)'), self::PENDING_STATUSES));

        return $this->quickReportPayload(
            'Informe rápido de permisos',
            "Hoy se presentaron {$submittedToday} solicitudes; {$pending} permisos requieren revisión.",
            [
                $this->quickMetric('permissions_submitted_today', 'Solicitudes hoy', $submittedToday, 'Permisos enviados durante el día.', 'bx-file', 'primary', 'today'),
                $this->quickMetric('permissions_pending', 'Por revisar', $pending, 'Solicitudes pendientes en el flujo de aprobación.', 'bx-time-five', 'warning', 'follow_up'),
                $this->quickMetric('permissions_current_absences', 'Ausencias vigentes', $currentAbsences, 'Permisos aprobados que aplican hoy.', 'bx-user-x', 'info', 'today'),
                $this->quickMetric('permissions_urgent', 'Urgentes pendientes', $urgent, 'Solicitudes urgentes aún sin resolución.', 'bx-error', 'danger', 'follow_up'),
            ]
        );
    }

    private function studentsQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $enrolledToday = $this->countRows('student_enrollments', fn ($query) => $query->whereDate('created_at', $today));
        $movements = $this->countRows('student_enrollment_movements', fn ($query) => $query->where('created_at', '>=', $from->toDateTimeString()));
        $movementsToday = $this->countRows('student_enrollment_movements', fn ($query) => $query->whereDate('created_at', $today));
        $withdrawn = $this->countRows('student_enrollments', fn ($query) => $query->whereIn(DB::raw('LOWER(enrollment_status)'), ['retirada', 'retirado', 'withdrawn']));

        return $this->quickReportPayload(
            'Informe rápido de estudiantes',
            "Hoy hay {$enrolledToday} matrículas nuevas y {$movementsToday} movimientos académicos.",
            [
                $this->quickMetric('students_enrolled_today', 'Matrículas hoy', $enrolledToday, 'Fichas de matrícula creadas durante el día.', 'bx-user-plus', 'primary', 'today'),
                $this->quickMetric('students_movements_today', 'Movimientos hoy', $movementsToday, 'Cambios de curso, estado o matrícula registrados hoy.', 'bx-transfer-alt', 'info', 'today'),
                $this->quickMetric('students_movements_period', 'Movimientos del período', $movements, 'Cambios de matrícula dentro del rango seleccionado.', 'bx-history', 'secondary', 'period'),
                $this->quickMetric('students_withdrawn', 'Matrículas retiradas', $withdrawn, 'Estudiantes con estado de retiro en matrícula.', 'bx-user-minus', 'warning', 'follow_up'),
            ]
        );
    }

    private function inventoryQuickReport(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $movementsToday = $this->countRows('inventory_movements', fn ($query) => $query->whereDate('movement_date', $today));
        $lowStock = $this->countRows('inventory_items', fn ($query) => $query
            ->where('item_type', 'consumable')
            ->whereNotNull('minimum_stock')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock'));
        $itemsInReview = $this->countRows('inventory_items', fn ($query) => $query->whereIn(DB::raw('LOWER(`condition`)'), ['regular', 'malo', 'mala', 'dañado', 'dañada']));
        $inactive = $this->countRows('inventory_items', fn ($query) => $query->where('active', false));

        return $this->quickReportPayload(
            'Informe rápido de inventario',
            "Hoy hay {$movementsToday} movimientos; {$lowStock} insumos están bajo mínimo.",
            [
                $this->quickMetric('inventory_movements_today', 'Movimientos hoy', $movementsToday, 'Traslados, asignaciones o cambios registrados hoy.', 'bx-transfer', 'primary', 'today'),
                $this->quickMetric('inventory_low_stock', 'Stock bajo', $lowStock, 'Insumos consumibles bajo o igual al mínimo definido.', 'bx-error-circle', 'danger', 'follow_up'),
                $this->quickMetric('inventory_review_condition', 'Bienes por revisar', $itemsInReview, 'Bienes con condición regular, mala o dañada.', 'bx-search-alt', 'warning', 'follow_up'),
                $this->quickMetric('inventory_inactive', 'Bienes inactivos', $inactive, 'Elementos marcados como no activos.', 'bx-archive', 'secondary', 'period'),
            ]
        );
    }

    private function genericQuickReport(array $operationalSections): array
    {
        $sections = count($operationalSections);
        $events = collect($operationalSections)->sum(fn (array $section) => count($section['rows'] ?? []));

        return $this->quickReportPayload(
            'Informe rápido del módulo',
            "{$sections} secciones de seguimiento disponibles con {$events} eventos destacados en el período.",
            [
                $this->quickMetric('generic_sections', 'Secciones de informe', $sections, 'Bloques de información operativa disponibles para este módulo.', 'bx-layout', 'secondary', 'period'),
                $this->quickMetric('generic_events', 'Eventos destacados', $events, 'Actividad relevante encontrada en el período seleccionado.', 'bx-pulse', 'info', 'period'),
            ]
        );
    }

    private function quickReportPayload(string $title, string $summary, array $metrics): array
    {
        return [
            'title' => $title,
            'summary' => $summary,
            'metrics' => array_values($metrics),
        ];
    }

    private function quickMetric(
        string $key,
        string $label,
        int $value,
        string $detail,
        string $icon,
        string $variant,
        string $scope
    ): array {
        return compact('key', 'label', 'value', 'detail', 'icon', 'variant', 'scope');
    }

    private function countRows(string $table, callable $callback): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        try {
            $query = $this->baseQuery($table);

            return (int) $callback($query)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function distinctCount(string $table, string $column, callable $callback): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        try {
            $query = $this->baseQuery($table);

            return (int) $callback($query)->distinct($column)->count($column);
        } catch (Throwable) {
            return 0;
        }
    }

    private function operationalSections(string $slug, CarbonImmutable $from): array
    {
        return match ($slug) {
            'porter' => $this->porterOperationalSections($from),
            'spaces' => $this->spacesOperationalSections($from),
            'maintenance' => $this->maintenanceOperationalSections($from),
            'infirmary' => $this->infirmaryOperationalSections($from),
            'biblioteca' => $this->libraryOperationalSections($from),
            'security' => $this->securityOperationalSections($from),
            'tasks' => $this->tasksOperationalSections($from),
            'staff_permissions' => $this->staffPermissionsOperationalSections($from),
            'students' => $this->studentsOperationalSections($from),
            'inventory' => $this->inventoryOperationalSections($from),
            default => $this->genericOperationalSections($slug, $from),
        };
    }

    private function porterOperationalSections(CarbonImmutable $from): array
    {
        $today = CarbonImmutable::now()->toDateString();

        return [
            $this->operationalSection(
                'porter_visit_registrars',
                'Quién registró visitas hoy',
                'Resumen por usuario de las visitas ingresadas en portería durante el día.',
                'bx-user-check',
                '/porter/visits',
                ['Usuario', 'Visitas registradas', 'Último ingreso', 'En curso'],
                $this->fetchOperationalRows('porter_visits', function ($query) use ($today) {
                    return $query
                        ->leftJoin('users as u', 'u.id', '=', 't.registered_by')
                        ->select(
                            DB::raw("COALESCE(u.name, 'Sin usuario asignado') as user_name"),
                            DB::raw('COUNT(*) as total'),
                            DB::raw('MAX(t.entered_at) as last_entered_at'),
                            DB::raw("SUM(CASE WHEN t.status = 'en_curso' THEN 1 ELSE 0 END) as open_total")
                        )
                        ->whereDate('t.entered_at', $today)
                        ->groupBy('u.name')
                        ->orderByDesc('total')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->user_name,
                            $row->total . ' visitas',
                            '-',
                            [
                                $this->text($row->user_name),
                                (string) $row->total,
                                $this->displayDateTime($row->last_entered_at),
                                (string) $row->open_total,
                            ]
                        ));
                }),
                'Sin visitas registradas hoy.'
            ),
            $this->operationalSection(
                'porter_visits',
                'Quién entró al colegio',
                'Últimas visitas registradas por portería durante el período seleccionado.',
                'bx-log-in-circle',
                '/porter/visits',
                ['Ingreso', 'Persona', 'Destino', 'Motivo', 'Estado'],
                $this->fetchOperationalRows('porter_visits', function ($query) use ($from) {
                    return $query
                        ->leftJoin('departments as d', 'd.id', '=', 't.visited_department_id')
                        ->select('t.entered_at', 't.visitor_name', 't.purpose', 't.visited_person_label', 't.status', 'd.name as department_name')
                        ->where('t.entered_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.entered_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->visitor_name,
                            $row->purpose,
                            $row->status,
                            [
                                $this->displayDateTime($row->entered_at),
                                $this->text($row->visitor_name),
                                $this->text($row->visited_person_label ?: $row->department_name),
                                $this->shortText($row->purpose, 58),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin visitas registradas en el período.'
            ),
            $this->operationalSection(
                'porter_received_items',
                'Paquetes y objetos recibidos',
                'Objetos, documentos o paquetes pendientes de entrega o recientemente recibidos.',
                'bx-package',
                '/porter/received-items',
                ['Recibido', 'Destinatario', 'Tipo', 'Detalle', 'Estado'],
                $this->fetchOperationalRows('porter_received_items', function ($query) use ($from) {
                    return $query
                        ->select('t.received_at', 't.recipient_label', 't.item_type', 't.description', 't.status')
                        ->where('t.received_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.received_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->recipient_label,
                            $row->description,
                            $row->status,
                            [
                                $this->displayDateTime($row->received_at),
                                $this->text($row->recipient_label),
                                $this->statusText($row->item_type),
                                $this->shortText($row->description, 58),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin paquetes u objetos recibidos en el período.'
            ),
            $this->operationalSection(
                'porter_goods_movements',
                'Mercadería y carga',
                'Ingreso o salida de mercadería, proveedores y documentación asociada.',
                'bx-transfer',
                '/porter/goods',
                ['Fecha', 'Movimiento', 'Contacto', 'Mercadería', 'Estado'],
                $this->fetchOperationalRows('porter_goods_movements', function ($query) use ($from) {
                    return $query
                        ->select('t.moved_at', 't.movement_type', 't.contact_name', 't.company', 't.goods_detail', 't.quantity', 't.unit', 't.status')
                        ->where('t.moved_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.moved_at')
                        ->limit(8)
                        ->get()
                        ->map(function ($row) {
                            $quantity = $this->quantityWithUnit($row->quantity, $row->unit);

                            return $this->row(
                                $row->goods_detail,
                                $row->company ?: $row->contact_name,
                                $row->status,
                                [
                                    $this->displayDateTime($row->moved_at),
                                    $this->statusText($row->movement_type),
                                    $this->text($row->company ?: $row->contact_name),
                                    trim($this->shortText($row->goods_detail, 54) . ($quantity ? ' · ' . $quantity : '')),
                                    $this->statusText($row->status),
                                ]
                            );
                        });
                }),
                'Sin movimientos de mercadería en el período.'
            ),
            $this->operationalSection(
                'porter_student_withdrawals',
                'Retiros de estudiantes',
                'Salidas autorizadas o registradas desde portería.',
                'bx-user-minus',
                '/porter/withdrawals',
                ['Retiro', 'Estudiante', 'Curso', 'Retira', 'Motivo', 'Estado'],
                $this->fetchOperationalRows('porter_student_withdrawals', function ($query) use ($from) {
                    return $query
                        ->select('t.withdrawn_at', 't.student_full_name_snapshot', 't.course_name_snapshot', 't.person_name', 't.person_relationship', 't.reason', 't.status')
                        ->where('t.withdrawn_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.withdrawn_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->student_full_name_snapshot,
                            $row->person_name,
                            $row->status,
                            [
                                $this->displayDateTime($row->withdrawn_at),
                                $this->text($row->student_full_name_snapshot),
                                $this->text($row->course_name_snapshot),
                                trim($this->text($row->person_name) . ($row->person_relationship ? ' · ' . $row->person_relationship : '')),
                                $this->statusText($row->reason),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin retiros de estudiantes en el período.'
            ),
            $this->operationalSection(
                'porter_external_service_entries',
                'Proveedores y servicios externos',
                'Empresas, técnicos o servicios que ingresaron al establecimiento.',
                'bx-hard-hat',
                '/porter/providers',
                ['Ingreso', 'Proveedor', 'Servicio', 'Dependencia', 'Estado'],
                $this->fetchOperationalRows('porter_external_service_entries', function ($query) use ($from) {
                    return $query
                        ->leftJoin('maintenance_dependencies as d', 'd.id', '=', 't.maintenance_dependency_id')
                        ->select('t.entered_at', 't.company_name', 't.contact_name', 't.service_type', 't.status', 'd.name as dependency_name')
                        ->where('t.entered_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.entered_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->company_name ?: $row->contact_name,
                            $row->service_type,
                            $row->status,
                            [
                                $this->displayDateTime($row->entered_at),
                                $this->text($row->company_name ?: $row->contact_name),
                                $this->statusText($row->service_type),
                                $this->text($row->dependency_name),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin proveedores registrados en el período.'
            ),
            $this->operationalSection(
                'porter_daily_log_entries',
                'Bitácora de portería',
                'Novedades registradas por turno para seguimiento directivo.',
                'bx-notepad',
                '/porter/daily-log',
                ['Fecha', 'Turno', 'Categoría', 'Prioridad', 'Novedad', 'Estado'],
                $this->fetchOperationalRows('porter_daily_log_entries', function ($query) use ($from) {
                    return $query
                        ->select('t.logged_at', 't.shift_label', 't.category', 't.priority', 't.title', 't.status')
                        ->where('t.logged_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.logged_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->title,
                            $row->category,
                            $row->priority,
                            [
                                $this->displayDateTime($row->logged_at),
                                $this->text($row->shift_label),
                                $this->statusText($row->category),
                                $this->statusText($row->priority),
                                $this->shortText($row->title, 54),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin novedades de bitácora en el período.'
            ),
        ];
    }

    private function spacesOperationalSections(CarbonImmutable $from): array
    {
        $now = CarbonImmutable::now();

        return [
            $this->operationalSection(
                'spaces_upcoming_reservations',
                'Espacios agendados',
                'Reservas próximas de salas, auditorios, canchas u otras dependencias.',
                'bx-calendar-event',
                '/spaces/reservations',
                ['Inicio', 'Término', 'Espacio', 'Actividad', 'Responsable', 'Estado'],
                $this->fetchOperationalRows('dependency_reservations', function ($query) use ($now) {
                    return $query
                        ->leftJoin('maintenance_dependencies as d', 'd.id', '=', 't.maintenance_dependency_id')
                        ->leftJoin('staff as s', 's.id', '=', 't.staff_id')
                        ->select('t.starts_at', 't.ends_at', 't.title', 't.activity', 't.status', 'd.name as dependency_name', 's.full_name as staff_name')
                        ->where('t.starts_at', '>=', $now->toDateTimeString())
                        ->whereNotIn(DB::raw('LOWER(t.status)'), self::FINAL_STATUSES)
                        ->orderBy('t.starts_at')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->title,
                            $row->dependency_name,
                            $row->status,
                            [
                                $this->displayDateTime($row->starts_at),
                                $this->displayTime($row->ends_at),
                                $this->text($row->dependency_name),
                                $this->shortText($row->activity ?: $row->title, 52),
                                $this->text($row->staff_name),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin espacios próximos agendados.'
            ),
            $this->operationalSection(
                'spaces_pending_reservations',
                'Reservas por aprobar',
                'Solicitudes de espacios que requieren revisión o aprobación.',
                'bx-time-five',
                '/spaces/reservations',
                ['Solicitada', 'Inicio', 'Espacio', 'Actividad', 'Solicitante', 'Estado'],
                $this->fetchOperationalRows('dependency_reservations', function ($query) use ($from) {
                    return $query
                        ->leftJoin('maintenance_dependencies as d', 'd.id', '=', 't.maintenance_dependency_id')
                        ->leftJoin('staff as s', 's.id', '=', 't.staff_id')
                        ->select('t.created_at', 't.starts_at', 't.title', 't.activity', 't.status', 'd.name as dependency_name', 's.full_name as staff_name')
                        ->whereIn(DB::raw('LOWER(t.status)'), self::PENDING_STATUSES)
                        ->where('t.created_at', '>=', $from->toDateTimeString())
                        ->orderBy('t.starts_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->title,
                            $row->staff_name,
                            $row->status,
                            [
                                $this->displayDateTime($row->created_at),
                                $this->displayDateTime($row->starts_at),
                                $this->text($row->dependency_name),
                                $this->shortText($row->activity ?: $row->title, 52),
                                $this->text($row->staff_name),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin reservas pendientes en el período.'
            ),
        ];
    }

    private function maintenanceOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'maintenance_open_orders',
                'Órdenes de mantención abiertas',
                'Trabajos solicitados, asignados o pendientes por resolver.',
                'bx-wrench',
                '/maintenance/work-orders',
                ['Reportada', 'Vence', 'Dependencia', 'Solicita', 'Prioridad', 'Estado'],
                $this->fetchOperationalRows('maintenance_work_orders', function ($query) {
                    return $query
                        ->leftJoin('maintenance_dependencies as d', 'd.id', '=', 't.maintenance_dependency_id')
                        ->select('t.reported_at', 't.due_date', 't.requested_by', 't.assigned_to', 't.priority', 't.status', 't.description', 'd.name as dependency_name')
                        ->whereNotIn(DB::raw('LOWER(t.status)'), self::FINAL_STATUSES)
                        ->orderByRaw('CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('t.due_date')
                        ->orderByDesc('t.reported_at')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->description,
                            $row->dependency_name,
                            $row->priority,
                            [
                                $this->displayDate($row->reported_at),
                                $this->displayDate($row->due_date),
                                $this->text($row->dependency_name),
                                $this->text($row->requested_by),
                                $this->statusText($row->priority),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin órdenes abiertas.'
            ),
            $this->operationalSection(
                'maintenance_recent_orders',
                'Mantenciones registradas',
                'Órdenes creadas o actualizadas durante el período seleccionado.',
                'bx-clipboard',
                '/maintenance/work-orders',
                ['Actualizada', 'Dependencia', 'Trabajo', 'Asignado', 'Prioridad', 'Estado'],
                $this->fetchOperationalRows('maintenance_work_orders', function ($query) use ($from) {
                    return $query
                        ->leftJoin('maintenance_dependencies as d', 'd.id', '=', 't.maintenance_dependency_id')
                        ->select('t.updated_at', 't.description', 't.assigned_to', 't.priority', 't.status', 'd.name as dependency_name')
                        ->where('t.updated_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.updated_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->description,
                            $row->assigned_to,
                            $row->status,
                            [
                                $this->displayDateTime($row->updated_at),
                                $this->text($row->dependency_name),
                                $this->shortText($row->description, 56),
                                $this->text($row->assigned_to),
                                $this->statusText($row->priority),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin mantenciones registradas en el período.'
            ),
        ];
    }

    private function infirmaryOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'infirmary_attentions',
                'Atenciones de enfermería',
                'Atenciones recientes de estudiantes, motivos y estado del caso.',
                'bx-plus-medical',
                '/infirmary/attentions',
                ['Atención', 'Estudiante', 'Curso', 'Categoría', 'Motivo', 'Estado'],
                $this->fetchOperationalRows('infirmary_attentions', function ($query) use ($from) {
                    return $query
                        ->select('t.attended_at', 't.student_full_name_snapshot', 't.course_name_snapshot', 't.attention_category', 't.consultation_reason', 't.priority', 't.status')
                        ->where('t.attended_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.attended_at')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->student_full_name_snapshot,
                            $row->consultation_reason,
                            $row->priority ?: $row->status,
                            [
                                $this->displayDateTime($row->attended_at),
                                $this->text($row->student_full_name_snapshot),
                                $this->text($row->course_name_snapshot),
                                $this->statusText($row->attention_category),
                                $this->shortText($row->consultation_reason, 52),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin atenciones de enfermería en el período.'
            ),
            $this->operationalSection(
                'infirmary_accidents',
                'Accidentes escolares',
                'Accidentes reportados, severidad, lugar y estado del caso.',
                'bx-shield-quarter',
                '/infirmary/accidents',
                ['Ocurrido', 'Estudiante', 'Lugar', 'Tipo', 'Severidad', 'Estado'],
                $this->fetchOperationalRows('infirmary_accidents', function ($query) use ($from) {
                    return $query
                        ->leftJoin('student_profiles as sp', 'sp.id', '=', 't.student_profile_id')
                        ->select('t.occurred_at', 't.accident_type', 't.place', 't.severity', 't.case_status', 'sp.first_name', 'sp.last_name')
                        ->where('t.occurred_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.occurred_at')
                        ->limit(8)
                        ->get()
                        ->map(function ($row) {
                            $student = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

                            return $this->row(
                                $student,
                                $row->accident_type,
                                $row->severity,
                                [
                                    $this->displayDateTime($row->occurred_at),
                                    $this->text($student),
                                    $this->text($row->place),
                                    $this->statusText($row->accident_type),
                                    $this->statusText($row->severity),
                                    $this->statusText($row->case_status),
                                ]
                            );
                        });
                }),
                'Sin accidentes escolares en el período.'
            ),
        ];
    }

    private function libraryOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'library_active_loans',
                'Préstamos y devoluciones',
                'Material prestado, fechas de devolución y atrasos.',
                'bx-book-reader',
                '/biblioteca/prestamos',
                ['Préstamo', 'Vence', 'Usuario', 'Obra', 'Curso', 'Estado'],
                $this->fetchOperationalRows('biblioteca_prestamos', function ($query) {
                    return $query
                        ->leftJoin('biblioteca_obras as o', 'o.id', '=', 't.biblioteca_obra_id')
                        ->select('t.borrowed_at', 't.due_at', 't.borrower_name_snapshot', 't.course_name_snapshot', 't.status', 't.overdue_days', 'o.title as obra_title')
                        ->whereNotIn(DB::raw('LOWER(t.status)'), self::FINAL_STATUSES)
                        ->orderBy('t.due_at')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->borrower_name_snapshot,
                            $row->obra_title,
                            $row->overdue_days > 0 ? 'vencido' : $row->status,
                            [
                                $this->displayDateTime($row->borrowed_at),
                                $this->displayDate($row->due_at),
                                $this->text($row->borrower_name_snapshot),
                                $this->shortText($row->obra_title, 54),
                                $this->text($row->course_name_snapshot),
                                $row->overdue_days > 0 ? 'Vencido (' . $row->overdue_days . ' días)' : $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin préstamos activos.'
            ),
            $this->operationalSection(
                'library_reservations',
                'Reservas de biblioteca',
                'Solicitudes y reservas de recursos bibliográficos o espacios.',
                'bx-bookmark',
                '/biblioteca/reservas',
                ['Solicitada', 'Retiro', 'Solicitante', 'Recurso', 'Propósito', 'Estado'],
                $this->fetchOperationalRows('biblioteca_reservas', function ($query) use ($from) {
                    return $query
                        ->leftJoin('biblioteca_obras as o', 'o.id', '=', 't.biblioteca_obra_id')
                        ->select('t.requested_at', 't.pickup_at', 't.requester_type', 't.purpose', 't.status', 'o.title as obra_title')
                        ->where('t.requested_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.requested_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->requester_type,
                            $row->obra_title ?: $row->purpose,
                            $row->status,
                            [
                                $this->displayDateTime($row->requested_at),
                                $this->displayDateTime($row->pickup_at),
                                $this->statusText($row->requester_type),
                                $this->shortText($row->obra_title ?: 'Recurso', 46),
                                $this->shortText($row->purpose, 48),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin reservas de biblioteca en el período.'
            ),
        ];
    }

    private function securityOperationalSections(CarbonImmutable $from): array
    {
        $now = CarbonImmutable::now()->startOfDay();

        return [
            $this->operationalSection(
                'security_incidents',
                'Novedades de seguridad',
                'Incidentes abiertos, prioridad y responsable de seguimiento.',
                'bx-shield',
                '/security/incidents',
                ['Registrada', 'Título', 'Sector', 'Prioridad', 'Estado', 'Responsable'],
                $this->fetchOperationalRows('security_incidents', function ($query) {
                    return $query
                        ->leftJoin('security_incident_statuses as st', 'st.id', '=', 't.status_id')
                        ->leftJoin('users as u', 'u.id', '=', 't.current_responsible_user_id')
                        ->select('t.created_at', 't.title', 't.sector_name', 't.priority', 't.requires_immediate_attention', 'st.name as status_name', 'st.is_closed', 'u.name as responsible_name')
                        ->where(function ($query) {
                            $query->whereNull('st.is_closed')->orWhere('st.is_closed', false);
                        })
                        ->orderByDesc('t.requires_immediate_attention')
                        ->orderByDesc('t.created_at')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->title,
                            $row->sector_name,
                            $row->requires_immediate_attention ? 'urgente' : $row->priority,
                            [
                                $this->displayDateTime($row->created_at),
                                $this->shortText($row->title, 52),
                                $this->text($row->sector_name),
                                $row->requires_immediate_attention ? 'Inmediata' : $this->statusText($row->priority),
                                $this->text($row->status_name),
                                $this->text($row->responsible_name),
                            ]
                        ));
                }),
                'Sin novedades abiertas de seguridad.'
            ),
            $this->operationalSection(
                'security_shifts',
                'Turnos y rondas',
                'Turnos programados, en curso o cerrados recientemente.',
                'bx-walk',
                '/security/shifts',
                ['Inicio', 'Término', 'Funcionario', 'Cobertura', 'Estado'],
                $this->fetchOperationalRows('security_shifts', function ($query) use ($now) {
                    return $query
                        ->leftJoin('staff as s', 's.id', '=', 't.staff_id')
                        ->select('t.scheduled_start_at', 't.scheduled_end_at', 't.coverage_label', 't.status', 's.full_name as staff_name')
                        ->where('t.scheduled_end_at', '>=', $now->toDateTimeString())
                        ->orderBy('t.scheduled_start_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->staff_name,
                            $row->coverage_label,
                            $row->status,
                            [
                                $this->displayDateTime($row->scheduled_start_at),
                                $this->displayDateTime($row->scheduled_end_at),
                                $this->text($row->staff_name),
                                $this->text($row->coverage_label),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin turnos próximos o activos.'
            ),
            $this->operationalSection(
                'security_rounds',
                'Rondas recientes',
                'Rondas registradas durante el período seleccionado.',
                'bx-map-pin',
                '/security/dashboard',
                ['Registrada', 'Turno', 'Ronda', 'Estado', 'Observación'],
                $this->fetchOperationalRows('security_rounds', function ($query) use ($from) {
                    return $query
                        ->leftJoin('security_shifts as sh', 'sh.id', '=', 't.security_shift_id')
                        ->leftJoin('staff as s', 's.id', '=', 'sh.staff_id')
                        ->select('t.recorded_at', 't.round_number', 't.overall_status', 't.observations', 's.full_name as staff_name')
                        ->where('t.recorded_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.recorded_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            'Ronda ' . $row->round_number,
                            $row->staff_name,
                            $row->overall_status,
                            [
                                $this->displayDateTime($row->recorded_at),
                                $this->text($row->staff_name),
                                'Ronda ' . $row->round_number,
                                $this->statusText($row->overall_status),
                                $this->shortText($row->observations, 52),
                            ]
                        ));
                }),
                'Sin rondas registradas en el período.'
            ),
        ];
    }

    private function tasksOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'tasks_open',
                'Tareas pendientes',
                'Tareas abiertas, próximas a vencer o con responsable asignado.',
                'bx-task',
                '/tasks/backlog',
                ['Vence', 'Tarea', 'Responsable', 'Prioridad', 'Estado'],
                $this->fetchOperationalRows('tasks', function ($query) {
                    return $query
                        ->leftJoin('users as u', 'u.id', '=', 't.owner_user_id')
                        ->select('t.due_date', 't.title', 't.priority', 't.status', 'u.name as owner_name')
                        ->whereNull('t.completed_at')
                        ->whereNotIn(DB::raw('LOWER(t.status)'), self::FINAL_STATUSES)
                        ->orderByRaw('CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('t.due_date')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->title,
                            $row->owner_name,
                            $row->priority,
                            [
                                $this->displayDate($row->due_date),
                                $this->shortText($row->title, 58),
                                $this->text($row->owner_name),
                                $this->statusText($row->priority),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin tareas pendientes.'
            ),
            $this->operationalSection(
                'tasks_activity',
                'Actividad de tareas',
                'Cambios recientes realizados en tareas.',
                'bx-history',
                '/tasks/backlog',
                ['Fecha', 'Tarea', 'Acción', 'Usuario'],
                $this->fetchOperationalRows('task_activity_logs', function ($query) use ($from) {
                    return $query
                        ->leftJoin('tasks as task', 'task.id', '=', 't.task_id')
                        ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                        ->select('t.created_at', 't.action', 'task.title', 'u.name as user_name')
                        ->where('t.created_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.created_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->title,
                            $row->action,
                            $row->action,
                            [
                                $this->displayDateTime($row->created_at),
                                $this->shortText($row->title, 56),
                                $this->statusText($row->action),
                                $this->text($row->user_name),
                            ]
                        ));
                }),
                'Sin actividad de tareas en el período.'
            ),
        ];
    }

    private function staffPermissionsOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'staff_permission_requests',
                'Permisos de personal',
                'Solicitudes de permisos, ausencias y flujos pendientes.',
                'bx-calendar-check',
                '/staff/permissions/review',
                ['Solicitud', 'Funcionario', 'Tipo', 'Período', 'Motivo', 'Estado'],
                $this->fetchOperationalRows('permission_requests', function ($query) use ($from) {
                    return $query
                        ->leftJoin('staff as s', 's.id', '=', 't.staff_id')
                        ->leftJoin('permission_types as pt', 'pt.id', '=', 't.permission_type_id')
                        ->select('t.submitted_at', 't.start_date', 't.end_date', 't.reason', 't.status', 't.current_step', 's.full_name as staff_name', 'pt.name as type_name')
                        ->where(function ($query) use ($from) {
                            $query->where('t.created_at', '>=', $from->toDateTimeString())
                                ->orWhereIn(DB::raw('LOWER(t.status)'), self::PENDING_STATUSES);
                        })
                        ->orderByDesc('t.submitted_at')
                        ->orderByDesc('t.created_at')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->staff_name,
                            $row->reason,
                            $row->status,
                            [
                                $this->displayDateTime($row->submitted_at),
                                $this->text($row->staff_name),
                                $this->text($row->type_name),
                                $this->displayDateRange($row->start_date, $row->end_date),
                                $this->shortText($row->reason, 42),
                                trim($this->statusText($row->status) . ($row->current_step ? ' · ' . $this->statusText($row->current_step) : '')),
                            ]
                        ));
                }),
                'Sin solicitudes de permisos relevantes.'
            ),
        ];
    }

    private function studentsOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'student_movements',
                'Cambios y retiros de matrícula',
                'Movimientos académicos recientes por estudiante y curso.',
                'bx-transfer-alt',
                '/students/movements',
                ['Fecha', 'Estudiante', 'Movimiento', 'Desde', 'Hacia', 'Estado'],
                $this->fetchOperationalRows('student_enrollment_movements', function ($query) use ($from) {
                    return $query
                        ->leftJoin('student_profiles as sp', 'sp.id', '=', 't.student_profile_id')
                        ->select('t.effective_date', 't.created_at', 't.movement_type', 't.snapshot_from_course_display_name', 't.snapshot_to_course_display_name', 't.from_status', 't.to_status', 'sp.first_name', 'sp.last_name')
                        ->where('t.created_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.created_at')
                        ->limit(8)
                        ->get()
                        ->map(function ($row) {
                            $student = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

                            return $this->row(
                                $student,
                                $row->movement_type,
                                $row->to_status ?: $row->from_status,
                                [
                                    $this->displayDate($row->effective_date ?: $row->created_at),
                                    $this->text($student),
                                    $this->statusText($row->movement_type),
                                    $this->text($row->snapshot_from_course_display_name),
                                    $this->text($row->snapshot_to_course_display_name),
                                    $this->statusText($row->to_status ?: $row->from_status),
                                ]
                            );
                        });
                }),
                'Sin movimientos de matrícula en el período.'
            ),
            $this->operationalSection(
                'student_enrollments',
                'Matrículas recientes',
                'Altas o cambios de matrícula registrados en el período.',
                'bx-user-plus',
                '/students',
                ['Fecha', 'Estudiante', 'Curso', 'Año', 'Estado'],
                $this->fetchOperationalRows('student_enrollments', function ($query) use ($from) {
                    return $query
                        ->leftJoin('student_profiles as sp', 'sp.id', '=', 't.student_profile_id')
                        ->select('t.enrolled_at', 't.created_at', 't.snapshot_course_display_name', 't.snapshot_year_name', 't.enrollment_status', 'sp.first_name', 'sp.last_name')
                        ->where('t.created_at', '>=', $from->toDateTimeString())
                        ->orderByDesc('t.created_at')
                        ->limit(8)
                        ->get()
                        ->map(function ($row) {
                            $student = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

                            return $this->row(
                                $student,
                                $row->snapshot_course_display_name,
                                $row->enrollment_status,
                                [
                                    $this->displayDate($row->enrolled_at ?: $row->created_at),
                                    $this->text($student),
                                    $this->text($row->snapshot_course_display_name),
                                    $this->text($row->snapshot_year_name),
                                    $this->statusText($row->enrollment_status),
                                ]
                            );
                        });
                }),
                'Sin matrículas recientes en el período.'
            ),
        ];
    }

    private function inventoryOperationalSections(CarbonImmutable $from): array
    {
        return [
            $this->operationalSection(
                'inventory_movements',
                'Movimientos de inventario',
                'Traslados, asignaciones o cambios de bienes registrados recientemente.',
                'bx-archive-in',
                '/inventory/management',
                ['Fecha', 'Bien', 'Movimiento', 'Desde', 'Hacia', 'Motivo'],
                $this->fetchOperationalRows('inventory_movements', function ($query) use ($from) {
                    return $query
                        ->leftJoin('inventory_items as item', 'item.id', '=', 't.inventory_item_id')
                        ->leftJoin('maintenance_dependencies as from_dep', 'from_dep.id', '=', 't.from_dependency_id')
                        ->leftJoin('maintenance_dependencies as to_dep', 'to_dep.id', '=', 't.to_dependency_id')
                        ->select('t.movement_date', 't.movement_type', 't.reason', 'item.name as item_name', 'item.code as item_code', 'from_dep.name as from_dependency', 'to_dep.name as to_dependency')
                        ->where('t.movement_date', '>=', $from->toDateString())
                        ->orderByDesc('t.movement_date')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->item_name,
                            $row->movement_type,
                            $row->movement_type,
                            [
                                $this->displayDate($row->movement_date),
                                trim($this->text($row->item_name) . ($row->item_code ? ' · ' . $row->item_code : '')),
                                $this->statusText($row->movement_type),
                                $this->text($row->from_dependency),
                                $this->text($row->to_dependency),
                                $this->shortText($row->reason, 46),
                            ]
                        ));
                }),
                'Sin movimientos de inventario en el período.'
            ),
            $this->operationalSection(
                'inventory_stock_alerts',
                'Stock e insumos críticos',
                'Insumos bajo mínimo o con stock agotado.',
                'bx-error',
                '/inventory/items',
                ['Bien', 'Stock', 'Mínimo', 'Unidad', 'Estado'],
                $this->fetchOperationalRows('inventory_items', function ($query) {
                    return $query
                        ->select('t.name', 't.code', 't.stock_quantity', 't.minimum_stock', 't.unit_of_measure', 't.status')
                        ->where('t.item_type', 'consumable')
                        ->whereNotNull('t.minimum_stock')
                        ->whereColumn('t.stock_quantity', '<=', 't.minimum_stock')
                        ->orderBy('t.stock_quantity')
                        ->limit(10)
                        ->get()
                        ->map(fn ($row) => $this->row(
                            $row->name,
                            'Stock bajo',
                            'urgente',
                            [
                                trim($this->text($row->name) . ($row->code ? ' · ' . $row->code : '')),
                                $this->text($row->stock_quantity),
                                $this->text($row->minimum_stock),
                                $this->text($row->unit_of_measure),
                                $this->statusText($row->status),
                            ]
                        ));
                }),
                'Sin insumos bajo mínimo.'
            ),
        ];
    }

    private function genericOperationalSections(string $slug, CarbonImmutable $from): array
    {
        $definition = $this->moduleDefinitions()[$slug] ?? null;

        if (!$definition) {
            return [];
        }

        $rows = collect($definition['tables'] ?? [])
            ->flatMap(function (array $definition) use ($from) {
                $table = $definition['table'];

                if (!Schema::hasTable($table)) {
                    return [];
                }

                try {
                    $columns = Schema::getColumnListing($table);
                    $dateColumn = $this->firstExistingColumn($columns, self::RECENT_DATE_COLUMNS);

                    if (!$dateColumn) {
                        return [];
                    }

                    $statusColumn = $this->firstExistingColumn($columns, self::STATUS_COLUMNS);
                    $titleColumn = $this->firstExistingColumn($columns, [
                        'title',
                        'name',
                        'full_name',
                        'description',
                        'detail',
                        'reason',
                        'activity',
                        'subject',
                        'code',
                        'internal_code',
                        'loan_code',
                        'reservation_code',
                    ]);

                    return $this->fetchOperationalRows($table, function ($query) use ($definition, $dateColumn, $statusColumn, $titleColumn, $from) {
                        $selects = [
                            't.' . $dateColumn . ' as activity_at',
                            DB::raw("'" . str_replace("'", "''", $definition['label'] ?? $definition['table']) . "' as table_label"),
                        ];

                        $selects[] = $statusColumn ? 't.' . $statusColumn . ' as status_value' : DB::raw('NULL as status_value');
                        $selects[] = $titleColumn ? 't.' . $titleColumn . ' as title_value' : DB::raw('NULL as title_value');

                        return $query
                            ->select($selects)
                            ->where('t.' . $dateColumn, '>=', $from->toDateTimeString())
                            ->orderByDesc('t.' . $dateColumn)
                            ->limit(4)
                            ->get()
                            ->map(fn ($row) => $this->row(
                                $row->table_label,
                                $row->title_value,
                                $row->status_value,
                                [
                                    $this->displayDateTime($row->activity_at),
                                    $this->text($row->table_label),
                                    $this->shortText($row->title_value, 64),
                                    $this->statusText($row->status_value),
                                ],
                                $row->activity_at
                            ));
                    });
                } catch (Throwable) {
                    return [];
                }
            })
            ->sortByDesc(fn (array $row) => $row['sort_value'] ?? '')
            ->take(10)
            ->map(function (array $row) {
                unset($row['sort_value']);

                return $row;
            })
            ->values()
            ->all();

        return [
            $this->operationalSection(
                'generic_recent_activity',
                'Actividad reciente',
                'Última actividad relevante detectada para este módulo.',
                'bx-pulse',
                null,
                ['Fecha', 'Origen', 'Detalle', 'Estado'],
                $rows,
                'Sin actividad reciente detectada en el período.'
            ),
        ];
    }

    private function operationalSection(
        string $key,
        string $title,
        string $description,
        string $icon,
        ?string $route,
        array $headers,
        array $rows,
        string $empty
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'route' => $route,
            'headers' => $headers,
            'rows' => array_values($rows),
            'empty' => $empty,
        ];
    }

    private function fetchOperationalRows(string $table, callable $callback): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        try {
            $query = DB::table($table . ' as t');

            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('t.deleted_at');
            }

            $rows = $callback($query);

            if ($rows instanceof Collection) {
                return $rows->values()->all();
            }

            return array_values((array) $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function row(mixed $primary, mixed $secondary, mixed $status, array $cells, ?string $sortValue = null): array
    {
        return [
            'primary' => $this->text($primary),
            'secondary' => $this->text($secondary),
            'status' => $this->text($status),
            'cells' => array_map(fn ($cell) => $this->text($cell), $cells),
            'sort_value' => $sortValue,
        ];
    }

    private function mergeStatusSummaries(Collection $tables): array
    {
        return $tables
            ->flatMap(fn (array $table) => $table['status_breakdown'] ?? [])
            ->groupBy('label')
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'total' => (int) $items->sum('total'),
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values()
            ->all();
    }

    private function baseQuery(string $table)
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function safeCount(string $table, array $conditions = []): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        try {
            $query = $this->baseQuery($table);

            foreach ($conditions as $column => $value) {
                if (Schema::hasColumn($table, $column)) {
                    $query->where($column, $value);
                }
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countStatusValues(string $table, string $column, array $values): int
    {
        try {
            return (int) $this->baseQuery($table)
                ->whereIn($column, $values)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countOverdue(string $table, array $columns, ?string $statusColumn): int
    {
        $dateColumns = array_values(array_intersect(self::OVERDUE_DATE_COLUMNS, $columns));

        if (empty($dateColumns)) {
            return 0;
        }

        try {
            $query = $this->baseQuery($table)
                ->where(function ($query) use ($dateColumns) {
                    foreach ($dateColumns as $dateColumn) {
                        $query->orWhereDate($dateColumn, '<', CarbonImmutable::now()->toDateString());
                    }
                });

            if ($statusColumn) {
                $query->whereNotIn($statusColumn, self::FINAL_STATUSES);
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function statusBreakdown(string $table, string $statusColumn): array
    {
        try {
            return $this->baseQuery($table)
                ->select($statusColumn . ' as label', DB::raw('COUNT(*) as total'))
                ->whereNotNull($statusColumn)
                ->groupBy($statusColumn)
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'label' => (string) $row->label,
                    'total' => (int) $row->total,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function maxDateValue(string $table, ?string $column): ?string
    {
        if (!$column) {
            return null;
        }

        try {
            $value = $this->baseQuery($table)->max($column);

            return $value ? CarbonImmutable::parse($value)->toDateTimeString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function latestActivity(Collection $tables): ?string
    {
        return $tables
            ->pluck('last_activity_at')
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function displayDateTime(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->format('d-m-Y H:i');
        } catch (Throwable) {
            return '-';
        }
    }

    private function displayDate(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->format('d-m-Y');
        } catch (Throwable) {
            return '-';
        }
    }

    private function displayTime(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return CarbonImmutable::parse($value)->format('H:i');
        } catch (Throwable) {
            return '-';
        }
    }

    private function displayDateRange(mixed $start, mixed $end): string
    {
        $startText = $this->displayDate($start);
        $endText = $this->displayDate($end);

        if ($startText === '-' && $endText === '-') {
            return '-';
        }

        if ($startText === $endText || $endText === '-') {
            return $startText;
        }

        if ($startText === '-') {
            return $endText;
        }

        return $startText . ' al ' . $endText;
    }

    private function statusText(mixed $value): string
    {
        $text = $this->text($value);

        return $text === '-' ? '-' : $this->humanize($text);
    }

    private function shortText(mixed $value, int $limit = 60): string
    {
        $text = $this->text($value);

        return $text === '-' ? '-' : Str::limit($text, $limit);
    }

    private function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }

    private function quantityWithUnit(mixed $quantity, mixed $unit): string
    {
        if ($quantity === null || $quantity === '') {
            return '';
        }

        $number = rtrim(rtrim(number_format((float) $quantity, 2, ',', '.'), '0'), ',');

        return trim($number . ' ' . trim((string) ($unit ?? '')));
    }

    private function moduleRoute(SystemModule $module): ?string
    {
        if ($module->frontend_route) {
            return $module->frontend_route;
        }

        return $module->children->first(fn (SystemModule $child) => (bool) $child->frontend_route)?->frontend_route;
    }

    private function healthStatus(int $total, int $pending, int $overdue, int $alerts): string
    {
        if ($total === 0) {
            return 'sin_datos';
        }

        if ($overdue > 0 || $alerts > 0) {
            return 'requiere_atencion';
        }

        if ($pending > 0) {
            return 'en_revision';
        }

        return 'operativo';
    }

    private function humanize(string $value): string
    {
        return mb_convert_case(str_replace('_', ' ', $value), MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizePeriodDays(int $periodDays): int
    {
        return max(7, min(365, $periodDays));
    }

    private function moduleDefinitions(): array
    {
        return [
            'settings' => [
                'tables' => [
                    ['table' => 'users', 'label' => 'Usuarios'],
                    ['table' => 'roles', 'label' => 'Roles'],
                    ['table' => 'permissions', 'label' => 'Permisos'],
                    ['table' => 'system_modules', 'label' => 'Módulos del sistema'],
                    ['table' => 'cargos', 'label' => 'Cargos'],
                ],
            ],
            'students' => [
                'tables' => [
                    ['table' => 'student_profiles', 'label' => 'Fichas de estudiantes'],
                    ['table' => 'student_enrollments', 'label' => 'Matrículas'],
                    ['table' => 'student_enrollment_movements', 'label' => 'Cambios y retiros'],
                    ['table' => 'student_promotions', 'label' => 'Promociones'],
                    ['table' => 'course_sections', 'label' => 'Cursos'],
                    ['table' => 'academic_years', 'label' => 'Años académicos'],
                    ['table' => 'education_levels', 'label' => 'Niveles educativos'],
                ],
            ],
            'schedule' => [
                'tables' => [
                    ['table' => 'schedule_events', 'label' => 'Eventos horarios'],
                    ['table' => 'schedule_validation_issues', 'label' => 'Conflictos de horario'],
                    ['table' => 'teacher_contracts', 'label' => 'Contratos docentes'],
                    ['table' => 'teacher_schedule_layers', 'label' => 'Capas horarias'],
                    ['table' => 'study_plans', 'label' => 'Planes de estudio'],
                    ['table' => 'schedule_subjects', 'label' => 'Asignaturas'],
                    ['table' => 'school_day_templates', 'label' => 'Jornadas'],
                ],
            ],
            'porter' => [
                'tables' => [
                    ['table' => 'porter_student_withdrawals', 'label' => 'Retiros de estudiantes'],
                    ['table' => 'porter_received_items', 'label' => 'Objetos recibidos'],
                    ['table' => 'porter_goods_movements', 'label' => 'Mercadería'],
                    ['table' => 'porter_visits', 'label' => 'Visitas'],
                    ['table' => 'porter_external_service_entries', 'label' => 'Proveedores'],
                    ['table' => 'porter_daily_log_entries', 'label' => 'Bitácora'],
                    ['table' => 'porter_keys', 'label' => 'Llaves'],
                    ['table' => 'porter_key_groups', 'label' => 'Manojos de llaves'],
                    ['table' => 'porter_key_loans', 'label' => 'Préstamos de llaves'],
                    ['table' => 'porter_authorization_requests', 'label' => 'Autorizaciones'],
                ],
            ],
            'staff' => [
                'tables' => [
                    ['table' => 'staff', 'label' => 'Funcionarios'],
                    ['table' => 'departments', 'label' => 'Departamentos'],
                    ['table' => 'staff_documents', 'label' => 'Documentos de funcionarios'],
                    ['table' => 'staff_organigram_relations', 'label' => 'Organigrama'],
                ],
            ],
            'staff_permissions' => [
                'tables' => [
                    ['table' => 'permission_requests', 'label' => 'Solicitudes de permisos'],
                    ['table' => 'permission_types', 'label' => 'Tipos de permiso'],
                    ['table' => 'permission_request_approvals', 'label' => 'Aprobaciones'],
                    ['table' => 'permission_request_documents', 'label' => 'Documentos de permiso'],
                    ['table' => 'permission_request_replacements', 'label' => 'Reemplazos'],
                    ['table' => 'permission_request_logs', 'label' => 'Historial de permisos'],
                ],
            ],
            'tasks' => [
                'tables' => [
                    ['table' => 'tasks', 'label' => 'Tareas'],
                    ['table' => 'task_assigners', 'label' => 'Asignadores'],
                    ['table' => 'task_activity_logs', 'label' => 'Actividad de tareas'],
                ],
            ],
            'contracts' => [
                'tables' => [
                    ['table' => 'contracts', 'label' => 'Contratos'],
                    ['table' => 'contract_templates', 'label' => 'Plantillas'],
                    ['table' => 'contract_clauses', 'label' => 'Cláusulas'],
                    ['table' => 'contract_signatures', 'label' => 'Firmas'],
                    ['table' => 'contract_signers', 'label' => 'Firmantes'],
                ],
            ],
            'maintenance' => [
                'tables' => [
                    ['table' => 'maintenance_dependencies', 'label' => 'Áreas y dependencias'],
                    ['table' => 'maintenance_work_orders', 'label' => 'Órdenes de trabajo'],
                    ['table' => 'maintenance_visits', 'label' => 'Visitas'],
                    ['table' => 'maintenance_visit_checklist_responses', 'label' => 'Checklist de visitas'],
                    ['table' => 'maintenance_annual_plans', 'label' => 'Plan anual'],
                    ['table' => 'maintenance_checklist_items', 'label' => 'Ítems de checklist'],
                ],
            ],
            'spaces' => [
                'tables' => [
                    ['table' => 'maintenance_dependencies', 'label' => 'Dependencias reservables'],
                    ['table' => 'dependency_types', 'label' => 'Tipos de dependencia'],
                    ['table' => 'dependency_reservations', 'label' => 'Reservas'],
                    ['table' => 'maintenance_dependency_approvers', 'label' => 'Gestores'],
                    ['table' => 'dependency_reservation_collaborators', 'label' => 'Colaboradores'],
                ],
            ],
            'inventory' => [
                'tables' => [
                    ['table' => 'inventory_items', 'label' => 'Bienes'],
                    ['table' => 'inventory_movements', 'label' => 'Movimientos'],
                    ['table' => 'inventory_stock_movements', 'label' => 'Stock'],
                    ['table' => 'inventory_categories', 'label' => 'Categorías'],
                    ['table' => 'inventory_subcategories', 'label' => 'Subcategorías'],
                    ['table' => 'inventory_documents', 'label' => 'Documentos'],
                    ['table' => 'inventory_photos', 'label' => 'Fotos'],
                    ['table' => 'suppliers', 'label' => 'Proveedores'],
                ],
            ],
            'infirmary' => [
                'tables' => [
                    ['table' => 'infirmary_attentions', 'label' => 'Atenciones'],
                    ['table' => 'infirmary_accidents', 'label' => 'Accidentes'],
                    ['table' => 'infirmary_medications', 'label' => 'Medicamentos'],
                    ['table' => 'infirmary_medication_authorizations', 'label' => 'Autorizaciones de medicamentos'],
                    ['table' => 'infirmary_medication_administrations', 'label' => 'Administraciones'],
                    ['table' => 'infirmary_attention_calls', 'label' => 'Llamados'],
                    ['table' => 'infirmary_attention_follow_ups', 'label' => 'Seguimientos'],
                    ['table' => 'infirmary_documents', 'label' => 'Documentos'],
                ],
            ],
            'apoyo_profesional' => [
                'tables' => [
                    ['table' => 'apoyo_profesionales', 'label' => 'Profesionales'],
                    ['table' => 'apoyo_atenciones', 'label' => 'Atenciones'],
                    ['table' => 'apoyo_derivaciones', 'label' => 'Derivaciones'],
                    ['table' => 'apoyo_seguimientos', 'label' => 'Seguimientos'],
                    ['table' => 'apoyo_planes', 'label' => 'Planes de apoyo'],
                    ['table' => 'apoyo_plan_acciones', 'label' => 'Acciones de planes'],
                    ['table' => 'apoyo_entrevistas', 'label' => 'Entrevistas'],
                    ['table' => 'apoyo_adjuntos', 'label' => 'Adjuntos'],
                ],
            ],
            'pme_sep' => [
                'tables' => [
                    ['table' => 'pme_planes', 'label' => 'Planes PME'],
                    ['table' => 'pme_dimensiones', 'label' => 'Dimensiones'],
                    ['table' => 'pme_objetivos', 'label' => 'Objetivos'],
                    ['table' => 'pme_estrategias', 'label' => 'Estrategias'],
                    ['table' => 'pme_indicadores', 'label' => 'Indicadores'],
                    ['table' => 'pme_acciones', 'label' => 'Acciones'],
                    ['table' => 'pme_actividades', 'label' => 'Actividades'],
                    ['table' => 'pme_hitos', 'label' => 'Hitos'],
                    ['table' => 'pme_evidencias', 'label' => 'Evidencias'],
                    ['table' => 'pme_ingresos_sep', 'label' => 'Ingresos SEP'],
                    ['table' => 'pme_estudiantes_sep', 'label' => 'Estudiantes SEP'],
                    ['table' => 'pme_alertas', 'label' => 'Alertas PME'],
                ],
            ],
            'convivencia' => [
                'tables' => [
                    ['table' => 'convivencia_cases', 'label' => 'Casos'],
                    ['table' => 'convivencia_complaints', 'label' => 'Denuncias'],
                    ['table' => 'convivencia_derivations', 'label' => 'Derivaciones'],
                    ['table' => 'convivencia_measures', 'label' => 'Medidas'],
                    ['table' => 'convivencia_interviews', 'label' => 'Entrevistas'],
                    ['table' => 'convivencia_daily_logs', 'label' => 'Bitácora'],
                    ['table' => 'convivencia_protocol_activations', 'label' => 'Protocolos activados'],
                    ['table' => 'convivencia_plans', 'label' => 'Planes'],
                    ['table' => 'convivencia_sociograms', 'label' => 'Sociogramas'],
                    ['table' => 'convivencia_idps_results', 'label' => 'Resultados IDPS'],
                ],
            ],
            'risk_prevention' => [
                'tables' => [
                    ['table' => 'prevent_fire_extinguishers', 'label' => 'Extintores'],
                    ['table' => 'prevent_accidents', 'label' => 'Accidentes'],
                    ['table' => 'prevent_accident_follow_ups', 'label' => 'Seguimientos de accidentes'],
                    ['table' => 'prevent_emergency_plans', 'label' => 'Planes de emergencia'],
                    ['table' => 'prevent_emergency_drills', 'label' => 'Simulacros'],
                    ['table' => 'prevent_epp_items', 'label' => 'EPP'],
                    ['table' => 'prevent_epp_deliveries', 'label' => 'Entregas EPP'],
                    ['table' => 'prevent_trainings', 'label' => 'Capacitaciones'],
                    ['table' => 'prevent_documents', 'label' => 'Documentos prevención'],
                ],
            ],
            'biblioteca' => [
                'tables' => [
                    ['table' => 'biblioteca_obras', 'label' => 'Obras'],
                    ['table' => 'biblioteca_ejemplares', 'label' => 'Ejemplares'],
                    ['table' => 'biblioteca_prestamos', 'label' => 'Préstamos'],
                    ['table' => 'biblioteca_reservas', 'label' => 'Reservas'],
                    ['table' => 'biblioteca_plan_lector', 'label' => 'Plan lector'],
                    ['table' => 'biblioteca_espacios', 'label' => 'Espacios'],
                    ['table' => 'biblioteca_uso_espacios', 'label' => 'Uso de espacios'],
                    ['table' => 'biblioteca_alertas', 'label' => 'Alertas biblioteca'],
                ],
            ],
            'centro_apuntes' => [
                'tables' => [
                    ['table' => 'centro_apuntes_solicitudes', 'label' => 'Solicitudes'],
                    ['table' => 'centro_apuntes_asignaturas', 'label' => 'Asignaturas'],
                    ['table' => 'centro_apuntes_maquinas', 'label' => 'Máquinas'],
                    ['table' => 'panol_insumos', 'label' => 'Insumos'],
                    ['table' => 'panol_movimientos', 'label' => 'Movimientos de pañol'],
                    ['table' => 'panol_entregas', 'label' => 'Entregas'],
                    ['table' => 'centro_apuntes_alertas', 'label' => 'Alertas centro de apuntes'],
                ],
            ],
            'informatica' => [
                'tables' => [
                    ['table' => 'it_equipment', 'label' => 'Equipos'],
                    ['table' => 'it_equipment_loans', 'label' => 'Préstamos'],
                    ['table' => 'it_equipment_maintenance_reports', 'label' => 'Mantenciones'],
                    ['table' => 'it_equipment_status_logs', 'label' => 'Historial de estados'],
                    ['table' => 'it_equipment_attachments', 'label' => 'Adjuntos'],
                ],
            ],
            'remuneration' => [
                'tables' => [
                    ['table' => 'remuneration_periods', 'label' => 'Períodos'],
                    ['table' => 'remuneration_employee_profiles', 'label' => 'Perfiles remuneración'],
                    ['table' => 'remuneration_contract_settings', 'label' => 'Contratos remuneración'],
                    ['table' => 'remuneration_concepts', 'label' => 'Haberes y descuentos'],
                    ['table' => 'remuneration_movements', 'label' => 'Movimientos'],
                    ['table' => 'remuneration_payrolls', 'label' => 'Liquidaciones'],
                    ['table' => 'remuneration_payments', 'label' => 'Pagos'],
                    ['table' => 'remuneration_accounting_exports', 'label' => 'Centralizaciones'],
                    ['table' => 'hr_document_controls', 'label' => 'Control documental RRHH'],
                    ['table' => 'hr_medical_leaves', 'label' => 'Licencias médicas'],
                    ['table' => 'hr_climate_surveys', 'label' => 'Clima laboral'],
                    ['table' => 'hr_climate_action_plans', 'label' => 'Planes clima'],
                    ['table' => 'hr_workload_assignments', 'label' => 'Dotación y carga'],
                ],
            ],
            'accounting' => [
                'tables' => [
                    ['table' => 'accounting_budgets', 'label' => 'Presupuestos'],
                    ['table' => 'accounting_budget_lines', 'label' => 'Líneas presupuestarias'],
                    ['table' => 'accounting_incomes', 'label' => 'Ingresos'],
                    ['table' => 'accounting_expenses', 'label' => 'Egresos'],
                    ['table' => 'accounting_renderings', 'label' => 'Rendiciones'],
                    ['table' => 'accounting_payables', 'label' => 'Cuentas por pagar'],
                    ['table' => 'accounting_cheques', 'label' => 'Cheques'],
                    ['table' => 'accounting_bank_movements', 'label' => 'Movimientos bancarios'],
                    ['table' => 'accounting_f29_declarations', 'label' => 'Declaraciones F29'],
                    ['table' => 'accounting_journal_entries', 'label' => 'Asientos contables'],
                    ['table' => 'accounting_documents', 'label' => 'Documentos contables'],
                ],
            ],
            'security' => [
                'tables' => [
                    ['table' => 'security_shifts', 'label' => 'Turnos'],
                    ['table' => 'security_rounds', 'label' => 'Rondas'],
                    ['table' => 'security_round_sectors', 'label' => 'Sectores de ronda'],
                    ['table' => 'security_incidents', 'label' => 'Novedades'],
                    ['table' => 'security_incident_comments', 'label' => 'Comentarios'],
                    ['table' => 'security_notifications', 'label' => 'Notificaciones'],
                ],
            ],
            'relevant_calendar' => [
                'tables' => [
                    ['table' => 'calendar_events', 'label' => 'Eventos y procesos'],
                    ['table' => 'calendar_event_users', 'label' => 'Participantes'],
                    ['table' => 'calendar_event_reminders', 'label' => 'Recordatorios'],
                    ['table' => 'calendar_event_attachments', 'label' => 'Adjuntos'],
                    ['table' => 'calendar_event_logs', 'label' => 'Historial'],
                    ['table' => 'calendar_process_types', 'label' => 'Tipos de proceso'],
                    ['table' => 'calendar_institutions', 'label' => 'Instituciones'],
                ],
            ],
        ];
    }
}
