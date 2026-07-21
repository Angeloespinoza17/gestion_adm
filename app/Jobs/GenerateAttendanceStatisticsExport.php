<?php

namespace App\Jobs;

use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceDataQualityIssue;
use App\Models\Attendance\AttendanceExportJob;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\Attendance\AttendanceScheduledReport;
use App\Models\Security\SecurityNotification;
use App\Services\Attendance\AttendanceAggregationService;
use App\Services\Attendance\AttendanceFinancialImpactService;
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
    ): void {
        $export = AttendanceExportJob::query()->with('user')->findOrFail($this->exportId);
        $export->update(['status' => 'processing', 'progress' => 10, 'failure_message' => null]);

        try {
            $dashboard = $aggregation->dashboard($export->filters ?? [], $export->user);
            $sections = $this->sections($export, $dashboard, $aggregation, $financial);
            $metadata = [
                'periodo' => ($dashboard['meta']['date_range']['from'] ?? '-').' a '.($dashboard['meta']['date_range']['to'] ?? '-'),
                'año académico' => $dashboard['meta']['academic_year']['name'] ?? '-',
                'tipo de reporte' => $this->reportTypeLabel($export->report_type),
                'generado por' => $export->user?->name ?? '-',
                'fecha' => now()->format('d-m-Y H:i'),
                'filtros' => $this->filterSummary($export->filters ?? [], $dashboard),
            ];
            [$contents, $extension, $mime] = match ($export->format) {
                'pdf' => [$pdf->build('Estadísticas Avanzadas de Asistencia', $metadata, $sections, $dashboard), 'pdf', 'application/pdf'],
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
                'action_url' => '/students/attendance-statistics?section=quality&export='.$export->uuid,
            ]);
            $this->notifyScheduledRecipients($export);
            $audit->log('export_completed', $export, $export->user, newValues: ['format' => $export->format, 'report_type' => $export->report_type, 'mime' => $mime]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'failure_message' => mb_strimwidth($exception->getMessage(), 0, 1900), 'progress' => 0]);
            throw $exception;
        }
    }

    private function sections(AttendanceExportJob $export, array $dashboard, AttendanceAggregationService $aggregation, AttendanceFinancialImpactService $financial): array
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

        if (in_array($export->report_type, ['executive', 'courses'], true)) {
            $sections[] = ['title' => 'Cursos', 'headers' => ['Curso', 'Nivel', 'Estudiantes', 'Días', 'Presentes', 'Ausentes', 'Asistencia'], 'rows' => collect($dashboard['courses'])->map(fn ($row) => [$row['name'], $row['level'], $row['students'], $row['school_days'], $row['present'], $row['absent'], $this->percent($row['attendance_rate'])])->all()];
        }
        if ($export->report_type === 'students' || $export->report_type === 'risk') {
            $rows = collect();
            $page = 1;
            do {
                $result = $aggregation->students([...($export->filters ?? []), 'page' => $page, 'per_page' => 100]);
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
        ][$reportType] ?? 'Reporte de asistencia';
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
