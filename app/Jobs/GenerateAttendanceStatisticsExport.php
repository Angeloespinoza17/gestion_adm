<?php

namespace App\Jobs;

use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceActionPlan;
use App\Models\Attendance\AttendanceCase;
use App\Models\Attendance\AttendanceDataQualityIssue;
use App\Models\Attendance\AttendanceExportJob;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\Attendance\AttendancePatternDetection;
use App\Models\Attendance\AttendanceRiskSnapshot;
use App\Models\Attendance\AttendanceScheduledReport;
use App\Models\Security\SecurityNotification;
use App\Models\StudentProfile;
use App\Services\Attendance\AttendanceAggregationService;
use App\Services\Attendance\AttendanceAnalyticsService;
use App\Services\Attendance\AttendanceFinancialImpactService;
use App\Services\Attendance\AttendanceManagementAccessService;
use App\Services\Attendance\AttendanceManagementSettingsService;
use App\Services\Attendance\AttendancePdfBuilder;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAttendanceStatisticsExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public readonly int $exportId) {}

    public function handle(
        AttendanceAggregationService $aggregation,
        AttendancePdfBuilder $pdf,
        AttendanceFinancialImpactService $financial,
        AttendanceStatisticsAuditService $audit,
        AttendanceAnalyticsService $analytics,
        AttendanceManagementAccessService $access,
        AttendanceManagementSettingsService $managementSettings,
    ): void {
        $export = AttendanceExportJob::query()->with('user')->findOrFail($this->exportId);
        $export->update(['status' => 'processing', 'progress' => 10, 'failure_message' => null]);

        try {
            $dashboard = $aggregation->dashboard($export->filters ?? [], $export->user);
            $sections = $this->sections($export, $dashboard, $aggregation, $financial, $analytics, $access);
            $pdfContext = $this->pdfContext($export, $dashboard, $analytics, $access, $managementSettings);
            $metadata = [
                'periodo' => ($dashboard['meta']['date_range']['from'] ?? '-').' a '.($dashboard['meta']['date_range']['to'] ?? '-'),
                'año académico' => $dashboard['meta']['academic_year']['name'] ?? '-',
                'tipo de reporte' => $this->reportTypeLabel($export->report_type),
                'generado por' => $export->user?->name ?? '-',
                'fecha' => now()->format('d-m-Y H:i'),
                'filtros' => $this->filterSummary($export->filters ?? [], $dashboard),
            ];
            [$contents, $extension, $mime] = match ($export->format) {
                'pdf' => [$pdf->build($this->reportTitle($export->report_type), $metadata, $sections, $pdfContext), 'pdf', 'application/pdf'],
                'xls' => [$this->excel($metadata, $sections), 'xls', 'application/vnd.ms-excel'],
                default => [$this->csv($metadata, $sections), 'csv', 'text/csv'],
            };
            $path = 'attendance-statistics/exports/'.$export->uuid.'.'.$extension;
            Storage::disk('local')->put($path, $contents);
            $export->update([
                'status' => 'completed', 'progress' => 100, 'file_path' => $path,
                'file_size' => strlen($contents), 'completed_at' => now(),
            ]);
            SecurityNotification::query()->create([
                'user_id' => $export->user_id,
                'title' => 'Exportación de asistencia lista',
                'message' => 'El reporte solicitado ya está disponible para descarga.',
                'priority' => 'media',
                'action_url' => str_contains($export->report_type, 'management') || in_array($export->report_type, ['individual', 'family_interview', 'critical_cases', 'intervention_effectiveness'], true)
                    ? '/students/attendance-management?section=reports&export='.$export->uuid
                    : '/students/attendance-statistics?section=quality&export='.$export->uuid,
            ]);
            $this->notifyScheduledRecipients($export);
            $audit->log('export_completed', $export, $export->user, newValues: ['format' => $export->format, 'report_type' => $export->report_type, 'mime' => $mime]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'failure_message' => mb_strimwidth($exception->getMessage(), 0, 1900), 'progress' => 0]);
            throw $exception;
        }
    }

    private function pdfContext(
        AttendanceExportJob $export,
        array $dashboard,
        AttendanceAnalyticsService $analytics,
        AttendanceManagementAccessService $access,
        AttendanceManagementSettingsService $managementSettings,
    ): array {
        if (! in_array($export->report_type, ['individual', 'family_interview'], true)) {
            return $dashboard;
        }

        $filters = $export->filters ?? [];
        $studentId = (int) ($filters['student_profile_id'] ?? 0);
        abort_unless($studentId && $access->canViewStudent($export->user, $studentId, $export->academic_year_id), 403);
        $student = StudentProfile::query()->findOrFail($studentId);
        $analysis = $analytics->studentSummary($student, $export->academic_year_id, $filters['as_of'] ?? null);
        $settings = $managementSettings->forYear($export->academic_year_id);
        $dashboard['monthly'] = collect($analysis['monthly'])->map(fn (array $row) => [
            'label' => $row['period'],
            'attendance_rate' => $row['attendance_rate'],
        ])->values()->all();
        $dashboard['summary']['target_rate'] = (float) ($settings['risk_thresholds']['green'] ?? 95);

        return $dashboard;
    }

    private function sections(
        AttendanceExportJob $export,
        array $dashboard,
        AttendanceAggregationService $aggregation,
        AttendanceFinancialImpactService $financial,
        AttendanceAnalyticsService $analytics,
        AttendanceManagementAccessService $access,
    ): array
    {
        $summary = $dashboard['summary'];
        $sections = [[
            'title' => 'Resumen ejecutivo',
            'headers' => ['Indicador', 'Valor'],
            'rows' => [
                ['Asistencia', $this->percent($summary['attendance_rate'])], ['Meta', $this->percent($summary['target_rate'])],
                ['Presentes', $summary['present']], ['Ausentes', $summary['absent']], ['Justificadas', $summary['justified_absent']],
                ['Injustificadas', $summary['unjustified_absent']], ['Atrasos', $summary['late']], ['Retiros anticipados', $summary['early_departure']],
                ['Estudiantes en riesgo', $summary['students_at_risk']], ['Alertas abiertas', $summary['open_alerts']],
            ],
        ]];

        if (in_array($export->report_type, ['institutional_management', 'course_management', 'individual', 'family_interview', 'critical_cases', 'intervention_effectiveness'], true)) {
            return $this->managementSections($export, $dashboard, $sections, $analytics, $access);
        }

        if (in_array($export->report_type, ['executive', 'courses'], true)) {
            $sections[] = ['title' => 'Cursos', 'headers' => ['Curso', 'Nivel', 'Estudiantes', 'Días', 'Presentes', 'Ausentes', 'Asistencia'], 'rows' => collect($dashboard['courses'])->map(fn ($row) => [$row['name'], $row['level'], $row['students'], $row['school_days'], $row['present'], $row['absent'], $this->percent($row['attendance_rate'])])->all()];
        }
        if ($export->report_type === 'students' || $export->report_type === 'risk') {
            $rows = collect();
            $page = 1;
            do {
                $result = $aggregation->students([...($export->filters ?? []), 'page' => $page, 'per_page' => 100], $export->user);
                $rows->push(...$result['data']);
                $page++;
            } while ($page <= ($result['meta']['last_page'] ?? 1));
            $sections[] = ['title' => 'Estudiantes', 'headers' => ['Estudiante', 'RUT', 'Curso', 'Nivel', 'Asistencia', 'Ausentes', 'Justificadas', 'Atrasos', 'Riesgo'], 'rows' => $rows->map(fn ($row) => [$row['name'], $row['rut'], $row['course'], $row['level'], $this->percent($row['attendance_rate']), $row['absent'], $row['justified_absent'], $row['late'], $row['risk']['name']])->all()];
        }
        if ($export->report_type === 'alerts') {
            $rows = AttendanceAlert::query()->where('academic_year_id', $export->academic_year_id)
                ->when($export->filters['course_section_id'] ?? null, fn ($query, $id) => $query->where('course_section_id', $id))
                ->with(['studentProfile:id,first_name,last_name,registered_name', 'courseSection:id,display_name'])->get();
            $sections[] = ['title' => 'Alertas', 'headers' => ['Estudiante', 'Curso', 'Tipo', 'Gravedad', 'Estado', 'Detectada', 'Descripción'], 'rows' => $rows->map(fn ($row) => [$row->studentProfile?->registered_name_resolved, $row->courseSection?->display_name, $row->type, $row->severity, $row->status, $row->detected_on?->format('d-m-Y'), $row->description])->all()];
        }
        if ($export->report_type === 'interventions') {
            $rows = AttendanceIntervention::query()->where('academic_year_id', $export->academic_year_id)->with(['studentProfile:id,first_name,last_name,registered_name', 'courseSection:id,display_name', 'responsible:id,name'])->get();
            $sections[] = ['title' => 'Intervenciones', 'headers' => ['Folio', 'Estudiante', 'Curso', 'Estado', 'Responsable', 'Apertura', 'Vencimiento', 'Resultado'], 'rows' => $rows->map(fn ($row) => [$row->folio, $row->studentProfile?->registered_name_resolved, $row->courseSection?->display_name, $row->status, $row->responsible?->name, $row->opened_at?->format('d-m-Y'), $row->due_on?->format('d-m-Y'), $row->result])->all()];
        }
        if ($export->report_type === 'goals') {
            $rows = AttendanceGoal::query()->where('academic_year_id', $export->academic_year_id)->get();
            $sections[] = ['title' => 'Metas', 'headers' => ['Nombre', 'Alcance', 'Inicio', 'Término', 'Objetivo', 'Estado'], 'rows' => $rows->map(fn ($row) => [$row->name, $row->scope_type, $row->starts_on?->format('d-m-Y'), $row->ends_on?->format('d-m-Y'), $this->percent((float) $row->target_rate), $row->status])->all()];
        }
        if ($export->report_type === 'data_quality') {
            $rows = AttendanceDataQualityIssue::query()->where('academic_year_id', $export->academic_year_id)->get();
            $sections[] = ['title' => 'Calidad de datos', 'headers' => ['Tipo', 'Gravedad', 'Estado', 'Título', 'Descripción', 'Acción sugerida'], 'rows' => $rows->map(fn ($row) => [$row->type, $row->severity, $row->status, $row->title, $row->description, $row->suggested_action])->all()];
        }
        if ($export->report_type === 'financial') {
            $impact = $financial->calculate($export->academic_year_id, $dashboard['summary'], (float) ($dashboard['summary']['attendance_rate'] ?? 0));
            $sections[] = [
                'title' => 'Impacto financiero estimado',
                'headers' => ['Parámetro', 'Moneda', 'Valor unitario', 'Factor', 'Estimación actual', 'Impacto por punto', 'Vigencia', 'Fuente'],
                'rows' => collect($impact['parameters'])->map(fn ($row) => [$row['name'], $row['currency'], $row['unit_value'], $row['attendance_factor'], $row['current_estimate'], $row['impact_per_point'], $row['valid_from'], $row['source_reference']])->all(),
            ];
            $sections[] = ['title' => 'Advertencia metodológica', 'headers' => ['Nota'], 'rows' => [[$impact['warning']]]];
        }

        return $sections;
    }

    private function managementSections(
        AttendanceExportJob $export,
        array $dashboard,
        array $sections,
        AttendanceAnalyticsService $analytics,
        AttendanceManagementAccessService $access,
    ): array {
        $filters = $export->filters ?? [];
        $courseIds = $access->canViewAll($export->user)
            ? null
            : $access->courseIds($export->user, $export->academic_year_id)->all();
        $selectedCourseId = isset($filters['course_section_id']) ? (int) $filters['course_section_id'] : null;
        $latestSnapshot = AttendanceRiskSnapshot::query()->where('academic_year_id', $export->academic_year_id)->max('snapshot_date');
        $snapshots = AttendanceRiskSnapshot::query()->where('academic_year_id', $export->academic_year_id)
            ->when($latestSnapshot, fn ($query) => $query->whereDate('snapshot_date', $latestSnapshot))
            ->when($selectedCourseId, fn ($query) => $query->where('course_section_id', $selectedCourseId))
            ->when(is_array($courseIds), fn ($query) => $query->whereIn('course_section_id', $courseIds));

        if (in_array($export->report_type, ['institutional_management', 'course_management'], true)) {
            $riskRows = collect(['green' => 'Asistencia adecuada', 'yellow' => 'Atención preventiva', 'orange' => 'Riesgo de asistencia', 'red' => 'Apoyo prioritario', 'critical' => 'Atención crítica', 'no_data' => 'Sin datos'])
                ->map(fn (string $label, string $level) => [$label, (clone $snapshots)->where('risk_level', $level)->count()])->values()->all();
            $sections[] = ['title' => 'Distribución preventiva', 'headers' => ['Nivel', 'Estudiantes'], 'rows' => $riskRows];
            $patterns = AttendancePatternDetection::query()->where('academic_year_id', $export->academic_year_id)->where('is_active', true)
                ->when($selectedCourseId, fn ($query) => $query->where('course_section_id', $selectedCourseId))
                ->when(is_array($courseIds), fn ($query) => $query->whereIn('course_section_id', $courseIds))
                ->select('pattern_type')->selectRaw('COUNT(*) as total')->selectRaw('AVG(confidence_score) as confidence')
                ->groupBy('pattern_type')->orderByDesc('total')->get();
            $sections[] = ['title' => 'Patrones detectados', 'headers' => ['Patrón', 'Estudiantes', 'Confianza media'], 'rows' => $patterns->map(fn ($row) => [str_replace('_', ' ', $row->pattern_type), $row->total, $this->percent((float) $row->confidence)])->all()];
            $priority = (clone $snapshots)->with(['studentProfile:id,first_name,last_name,registered_name', 'courseSection:id,display_name'])
                ->whereIn('risk_level', ['orange', 'red', 'critical'])
                ->orderByRaw("CASE risk_level WHEN 'critical' THEN 3 WHEN 'red' THEN 2 ELSE 1 END DESC")
                ->orderByDesc('risk_score')->limit(200)->get();
            $sections[] = ['title' => 'Estudiantes priorizados', 'headers' => ['Estudiante', 'Curso', 'Nivel', 'Asistencia', 'Días perdidos', 'Racha', 'Motivo principal'], 'rows' => $priority->map(fn ($row) => [
                $row->studentProfile?->registered_name_resolved, $row->courseSection?->display_name, $row->risk_level,
                $this->percent($row->attendance_percentage), $row->days_absent, $row->consecutive_absences,
                collect($row->risk_reasons)->first() ?: 'Revisión preventiva',
            ])->all()];
        }

        if (in_array($export->report_type, ['critical_cases', 'institutional_management', 'course_management'], true)) {
            $cases = AttendanceCase::query()->where('academic_year_id', $export->academic_year_id)
                ->when($export->report_type === 'critical_cases', fn ($query) => $query->where('priority', 'critical'))
                ->when($selectedCourseId, fn ($query) => $query->where('course_section_id', $selectedCourseId))
                ->when(is_array($courseIds), fn ($query) => $query->whereIn('course_section_id', $courseIds))
                ->with(['studentProfile:id,first_name,last_name,registered_name', 'courseSection:id,display_name', 'responsible:id,name'])
                ->withCount(['interventions', 'familyContacts', 'plans'])->orderByDesc('opened_at')->limit(300)->get();
            $sections[] = ['title' => $export->report_type === 'critical_cases' ? 'Casos críticos' : 'Gestión de expedientes', 'headers' => ['Folio', 'Estudiante', 'Curso', 'Prioridad', 'Estado', 'Responsable', 'Contactos', 'Intervenciones', 'Planes', 'Próxima revisión'], 'rows' => $cases->map(fn ($case) => [
                $case->folio, $case->studentProfile?->registered_name_resolved, $case->courseSection?->display_name,
                $case->priority, $case->status, $case->responsible?->name ?: 'Por asignar', $case->family_contacts_count,
                $case->interventions_count, $case->plans_count, $case->next_review_on?->format('d-m-Y'),
            ])->all()];
        }

        if (in_array($export->report_type, ['individual', 'family_interview'], true)) {
            $studentId = (int) ($filters['student_profile_id'] ?? 0);
            abort_unless($studentId && $access->canViewStudent($export->user, $studentId, $export->academic_year_id), 403);
            $student = StudentProfile::query()->findOrFail($studentId);
            $analysis = $analytics->studentSummary($student, $export->academic_year_id, $filters['as_of'] ?? null);
            $summary = $analysis['summary'];
            $sections[0]['rows'] = [
                ['Estudiante', $student->registered_name_resolved], ['RUT', $student->rut],
                ['Asistencia', $this->percent($summary['attendance_percentage'])], ['Días lectivos registrados', $summary['school_days_elapsed']],
                ['Días presentes', $summary['days_present']], ['Días perdidos', $summary['days_absent']],
                ['Ausencias justificadas', $summary['justified_absences']], ['Ausencias injustificadas', $summary['unjustified_absences']],
                ['Atrasos', $summary['late_arrivals']], ['Retiros anticipados', $summary['early_departures']],
            ];
            if ($export->report_type === 'individual') {
                $sections[0]['rows'][] = ['Nivel de atención', $analysis['risk']['label'] ?? 'Sin datos'];
                $sections[0]['rows'][] = ['Explicación principal', collect($analysis['risk']['explanations'] ?? [])->first() ?: 'Sin señales suficientes'];
            }
            $sections[] = ['title' => 'Evolución mensual', 'headers' => ['Mes', 'Presentes', 'Ausentes', 'Asistencia'], 'rows' => collect($analysis['monthly'])->map(fn ($row) => [$row['period'], $row['present'], $row['absent'], $this->percent($row['attendance_rate'])])->all()];
            $sections[] = ['title' => 'Patrones observados', 'headers' => ['Señal', 'Ocurrencias', 'Confianza', 'Descripción'], 'rows' => collect($analysis['patterns'])->map(fn ($row) => [str_replace('_', ' ', $row->pattern_type), $row->occurrence_count, $this->percent($row->confidence_score), $row->description])->all()];
            $caseQuery = AttendanceCase::query()->where('student_profile_id', $studentId)->where('academic_year_id', $export->academic_year_id)
                ->with(['causes.reason', 'plans.actions', 'agreements']);
            $cases = $caseQuery->get();
            if ($export->report_type === 'individual') {
                $allowSensitive = $access->canViewSensitive($export->user);
                $causeRows = $cases->flatMap(fn ($case) => $case->causes->filter(fn ($cause) => $allowSensitive || ! $cause->is_sensitive)->map(fn ($cause) => [
                    $case->folio, $cause->reason?->name, $cause->information_source, $cause->identified_on?->format('d-m-Y'),
                    $allowSensitive ? $cause->observations : null,
                ]));
                $sections[] = ['title' => 'Causas identificadas', 'headers' => ['Expediente', 'Causa', 'Fuente', 'Fecha', 'Observación autorizada'], 'rows' => $causeRows->all()];
            }
            $planRows = $cases->flatMap(fn ($case) => $case->plans->map(fn ($plan) => [$plan->folio, $plan->objective, $plan->goal_type, $plan->goal_value, $plan->review_on?->format('d-m-Y'), $plan->status]));
            $sections[] = ['title' => $export->report_type === 'family_interview' ? 'Compromisos y plan compartido' : 'Planes individuales', 'headers' => ['Folio', 'Objetivo', 'Indicador', 'Meta', 'Revisión', 'Estado'], 'rows' => $planRows->all()];
            $agreementRows = $cases->flatMap(fn ($case) => $case->agreements->map(fn ($agreement) => [$agreement->meeting_date?->format('d-m-Y'), $agreement->agreement, $agreement->commitment_date?->format('d-m-Y'), $agreement->status]));
            $sections[] = ['title' => 'Acuerdos de entrevista', 'headers' => ['Fecha', 'Acuerdo', 'Compromiso', 'Estado'], 'rows' => $agreementRows->all()];
            if ($export->report_type === 'family_interview') {
                $sections[] = ['title' => 'Orientación para la familia', 'headers' => ['Nota'], 'rows' => [['Este documento apoya la conversación y el acompañamiento. No contiene diagnósticos ni puntajes internos de riesgo.']]];
            }
        }

        if ($export->report_type === 'intervention_effectiveness') {
            $plans = AttendanceActionPlan::query()->whereNotNull('evaluated_at')
                ->whereHas('attendanceCase', function ($query) use ($export, $selectedCourseId, $courseIds): void {
                    $query->where('academic_year_id', $export->academic_year_id)
                        ->when($selectedCourseId, fn ($builder) => $builder->where('course_section_id', $selectedCourseId))
                        ->when(is_array($courseIds), fn ($builder) => $builder->whereIn('course_section_id', $courseIds));
                })->with(['attendanceCase.studentProfile:id,first_name,last_name,registered_name', 'attendanceCase.courseSection:id,display_name', 'responsible:id,name'])->get();
            $sections[] = ['title' => 'Planes evaluados', 'headers' => ['Plan', 'Estudiante', 'Curso', 'Responsable', 'Inicial', 'Revisión', 'Variación', 'Resultado'], 'rows' => $plans->map(fn ($plan) => [
                $plan->folio, $plan->attendanceCase?->studentProfile?->registered_name_resolved, $plan->attendanceCase?->courseSection?->display_name,
                $plan->responsible?->name, $this->percent($plan->initial_attendance_rate), $this->percent($plan->review_attendance_rate),
                $plan->result_variation, str_replace('_', ' ', $plan->evaluation_result),
            ])->all()];
            $sections[] = ['title' => 'Advertencia metodológica', 'headers' => ['Nota'], 'rows' => [['La variación posterior a una intervención representa una asociación observada. No demuestra que la intervención haya causado el cambio.']]];
        }

        return $sections;
    }

    private function csv(array $metadata, array $sections): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($metadata as $label => $value) {
            fputcsv($stream, [$label, $value], ';');
        }
        fputcsv($stream, [], ';');
        foreach ($sections as $section) {
            fputcsv($stream, [$section['title']], ';');
            fputcsv($stream, $section['headers'] ?? [], ';');
            foreach ($section['rows'] ?? [] as $row) {
                fputcsv($stream, $this->safeRow($row), ';');
            }
            fputcsv($stream, [], ';');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function excel(array $metadata, array $sections): string
    {
        $worksheets = [[
            'name' => 'Filtros aplicados',
            'headers' => ['Campo', 'Valor'],
            'rows' => collect($metadata)->map(fn ($value, $label) => [$label, $value])->values()->all(),
        ], [
            'name' => 'Metodología',
            'headers' => ['Indicador', 'Fórmula o criterio'],
            'rows' => [
                ['Asistencia', 'Presentes / registros esperados * 100'],
                ['Inasistencia', 'Ausentes / registros esperados * 100'],
                ['Ausencia justificada', 'Ausencias justificadas / registros esperados * 100'],
                ['Atrasos', 'Registros con minutos de atraso / registros esperados * 100'],
                ['Retiros anticipados', 'Registros con retiro anticipado / registros esperados * 100'],
                ['Fuente', 'attendance_records + school_days + student_enrollments'],
                ['Proyecciones', 'Estimaciones sujetas a los parámetros y supuestos informados en el reporte'],
            ],
        ]];
        foreach ($sections as $section) {
            $worksheets[] = [
                'name' => $section['title'] ?? 'Datos',
                'headers' => $section['headers'] ?? [],
                'rows' => $section['rows'] ?? [],
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:o="urn:schemas-microsoft-com:office:office" '
            .'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles>'
            .'<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>'
            .'<Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#405189" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>'
            .'<Style ss:ID="Cell"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7DCE3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7DCE3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7DCE3"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7DCE3"/></Borders></Style>'
            .'</Styles>';

        $usedNames = [];
        foreach ($worksheets as $index => $worksheet) {
            $name = $this->worksheetName((string) $worksheet['name'], $usedNames, $index + 1);
            $headers = array_values($worksheet['headers']);
            $rows = collect($worksheet['rows'])->map(fn ($row) => array_values((array) $row))->all();
            $columnCount = max(1, count($headers), ...array_map('count', $rows ?: [[]]));
            $rowCount = max(1, count($rows) + 1);
            $xml .= '<Worksheet ss:Name="'.$this->xml($name).'"><Table>';
            for ($column = 0; $column < $columnCount; $column++) {
                $values = collect($rows)->take(250)->map(fn ($row) => $row[$column] ?? '')->push($headers[$column] ?? '');
                $width = min(300, max(80, ((int) $values->map(fn ($value) => mb_strlen($this->safeCell($value)))->max() * 6.5) + 14));
                $xml .= '<Column ss:AutoFitWidth="0" ss:Width="'.$width.'"/>';
            }
            $xml .= '<Row ss:StyleID="Header">';
            for ($column = 0; $column < $columnCount; $column++) {
                $xml .= $this->excelCell($headers[$column] ?? '');
            }
            $xml .= '</Row>';
            foreach ($rows as $row) {
                $xml .= '<Row ss:StyleID="Cell">';
                for ($column = 0; $column < $columnCount; $column++) {
                    $xml .= $this->excelCell($row[$column] ?? '');
                }
                $xml .= '</Row>';
            }
            $xml .= '</Table>';
            $xml .= '<AutoFilter x:Range="R1C1:R'.$rowCount.'C'.$columnCount.'" xmlns="urn:schemas-microsoft-com:office:excel"/>';
            $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions>';
            $xml .= '</Worksheet>';
        }

        return "\xEF\xBB\xBF".$xml.'</Workbook>';
    }

    private function excelCell(mixed $value): string
    {
        if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
            return '<Cell><Data ss:Type="Number">'.$value.'</Data></Cell>';
        }

        return '<Cell><Data ss:Type="String">'.$this->xml($this->safeCell($value)).'</Data></Cell>';
    }

    private function worksheetName(string $name, array &$usedNames, int $fallback): string
    {
        $base = trim((string) preg_replace('/[\\\\\/\?\*\[\]:]+/u', ' ', $name));
        $base = mb_substr($base !== '' ? $base : 'Hoja '.$fallback, 0, 31);
        $candidate = $base;
        $suffix = 2;
        while (in_array(mb_strtolower($candidate), $usedNames, true)) {
            $ending = ' '.$suffix++;
            $candidate = mb_substr($base, 0, 31 - mb_strlen($ending)).$ending;
        }
        $usedNames[] = mb_strtolower($candidate);

        return $candidate;
    }

    private function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }

    private function safeRow(array $row): array
    {
        return array_map([$this, 'safeCell'], $row);
    }

    private function notifyScheduledRecipients(AttendanceExportJob $export): void
    {
        $scheduleId = (int) ($export->filters['_scheduled_report_id'] ?? 0);
        if ($scheduleId === 0) {
            return;
        }

        $schedule = AttendanceScheduledReport::withTrashed()->find($scheduleId);
        if (! $schedule) {
            return;
        }

        try {
            foreach ($schedule->recipients ?? [] as $recipient) {
                Mail::raw(
                    "El reporte programado {$schedule->name} ya fue generado. Ingresa al centro de exportaciones de asistencia para descargarlo.",
                    fn ($message) => $message->to($recipient)->subject('Reporte de asistencia disponible'),
                );
            }
        } catch (Throwable $exception) {
            $schedule->update(['last_error' => 'El reporte fue generado, pero falló la notificación por correo: '.mb_strimwidth($exception->getMessage(), 0, 1600)]);
            report($exception);
        }
    }

    private function safeCell(mixed $value): string
    {
        $value = is_bool($value) ? ($value ? 'Sí' : 'No') : (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function percent(?float $value): string
    {
        return $value === null ? 'Sin datos' : number_format($value, 2, ',', '.').' %';
    }

    private function reportTypeLabel(string $reportType): string
    {
        return [
            'executive' => 'Resumen ejecutivo',
            'students' => 'Detalle de estudiantes',
            'courses' => 'Detalle de cursos',
            'risk' => 'Riesgo de asistencia',
            'alerts' => 'Alertas de asistencia',
            'interventions' => 'Intervenciones',
            'goals' => 'Metas institucionales',
            'financial' => 'Impacto financiero',
            'data_quality' => 'Calidad de datos',
            'institutional_management' => 'Gestión institucional de ausencia',
            'course_management' => 'Gestión de ausencia por curso',
            'individual' => 'Ficha individual de asistencia',
            'family_interview' => 'Reporte para entrevista familiar',
            'critical_cases' => 'Casos críticos de asistencia',
            'intervention_effectiveness' => 'Efectividad observada de intervenciones',
        ][$reportType] ?? 'Reporte de asistencia';
    }

    private function reportTitle(string $reportType): string
    {
        return match ($reportType) {
            'institutional_management' => 'Gestión Institucional de Ausencia',
            'course_management' => 'Gestión de Ausencia por Curso',
            'individual' => 'Ficha Individual de Asistencia',
            'family_interview' => 'Entrevista y Acompañamiento Familiar',
            'critical_cases' => 'Casos Críticos de Asistencia',
            'intervention_effectiveness' => 'Efectividad Observada de Intervenciones',
            default => 'Estadísticas Avanzadas de Asistencia',
        };
    }

    private function filterSummary(array $filters, array $dashboard): string
    {
        $periods = [
            'today' => 'Hoy', 'yesterday' => 'Ayer', 'current_week' => 'Semana actual',
            'previous_week' => 'Semana anterior', 'last_7_school_days' => 'Últimos 7 días lectivos',
            'last_14_school_days' => 'Últimos 14 días lectivos', 'last_30_days' => 'Últimos 30 días',
            'current_month' => 'Mes actual', 'previous_month' => 'Mes anterior', 'quarter' => 'Trimestre',
            'semester' => 'Semestre', 'academic_year' => 'Año académico', 'custom' => 'Personalizado',
        ];
        $parts = ['Temporalidad: '.($periods[$filters['period'] ?? 'academic_year'] ?? 'Periodo seleccionado')];
        $catalogs = $dashboard['catalogs'] ?? [];

        if ($filters['education_level_id'] ?? null) {
            $level = collect($catalogs['levels'] ?? [])->firstWhere('id', (int) $filters['education_level_id']);
            $parts[] = 'Nivel: '.($level['name'] ?? $filters['education_level_id']);
        }
        if ($filters['course_section_id'] ?? null) {
            $course = collect($catalogs['courses'] ?? [])->firstWhere('id', (int) $filters['course_section_id']);
            $parts[] = 'Curso: '.($course['display_name'] ?? $filters['course_section_id']);
        }
        foreach ([
            'enrollment_status' => 'Matrícula', 'attendance_status' => 'Estado', 'commune' => 'Comuna',
            'risk' => 'Riesgo', 'attendance_min' => 'Asistencia mínima', 'attendance_max' => 'Asistencia máxima',
        ] as $key => $label) {
            if (($filters[$key] ?? '') !== '') {
                $parts[] = $label.': '.$filters[$key];
            }
        }

        return implode(' · ', $parts);
    }
}
