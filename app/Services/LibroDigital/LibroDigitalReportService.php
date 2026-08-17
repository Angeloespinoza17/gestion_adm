<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Jobs\GenerateLibroDigitalReport;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ReportExport;
use App\Models\LibroDigital\School;
use App\Models\User;
use App\Services\Attendance\AttendancePdfBuilder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LibroDigitalReportService
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly AttendancePdfBuilder $pdf,
        private readonly XlsxReportBuilder $xlsx,
        private readonly AuditEventWriter $audit,
        private readonly CurriculumObjectiveReportService $curriculumObjectives,
    ) {}

    /** @param array<string, mixed> $filters */
    public function request(School $school, User $user, string $type, string $format, array $filters, ?Book $book = null): ReportExport
    {
        // Fail before creating an audit/report row if configuration could expose
        // plaintext report artifacts through a public disk or URL.
        $this->reportDisk();
        $title = $this->title($type);

        $export = ReportExport::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $book?->academic_year_id ?? ($filters['academic_year_id'] ?? null),
            'book_id' => $book?->id,
            'requested_by' => $user->id,
            'report_type' => $type,
            'format' => $format,
            'title' => $title,
            'filters_snapshot' => $filters,
            'status' => 'queued',
            'progress' => 0,
            // A closed book alone does not make a generated file suitable for
            // fiscalization. Release requires a separate approved workflow.
            'draft_watermark' => true,
            'requested_at' => now('UTC'),
            'expires_at' => now('UTC')->addDays((int) config('libro_digital.reports.expires_days', 7)),
        ]);

        try {
            $export->loadMissing(['school', 'academicYear', 'book.courseSection', 'book.academicYear', 'requester']);
            $snapshot = DB::transaction(fn (): array => $this->snapshot($export), 3);
            $encoded = $this->canonical->encode($snapshot);
            $snapshotPath = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/')
                .'/report-snapshots/'.$school->public_id.'/'.$export->public_id.'.json.enc';
            $disk = $this->reportDisk();
            if (! $disk->put($snapshotPath, Crypt::encryptString($encoded))) {
                throw new RuntimeException('No se pudo sellar la instantánea privada del reporte.');
            }
            $export->forceFill([
                'source_snapshot_hash' => hash('sha256', $encoded),
                'source_private_path' => $snapshotPath,
            ])->save();
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'failed',
                'failure_code' => 'LCD_REPORT_SNAPSHOT_FAILED',
                'failure_message' => mb_strimwidth($exception->getMessage(), 0, 1800),
            ])->save();

            throw $exception;
        }

        $this->audit->write(
            'lcd.report.requested',
            'request',
            $export,
            actor: $user,
            schoolId: $school->id,
            academicYearId: $export->academic_year_id,
            after: $export->only(['public_id', 'report_type', 'format', 'filters_snapshot', 'source_snapshot_hash']),
        );

        GenerateLibroDigitalReport::dispatch($export->id);

        return $export->fresh();
    }

    public function generate(ReportExport $export): void
    {
        $export->forceFill(['status' => 'processing', 'progress' => 10, 'started_at' => now('UTC')])->save();

        try {
            $export->loadMissing(['school', 'academicYear', 'book.courseSection', 'book.academicYear', 'requester']);
            $snapshot = $this->loadSnapshot($export);
            if ($export->report_type === 'curriculum_objectives') {
                [$contents, $extension, $mime] = $this->curriculumObjectives->generate($export, $snapshot);
            } else {
                $metadata = $snapshot['metadata'];
                $sections = $snapshot['sections'];
                $dashboard = $this->dashboard($export);
                [$contents, $extension, $mime] = match ($export->format) {
                    'pdf' => [$this->pdf->build($export->title, $metadata, $sections, $dashboard), 'pdf', 'application/pdf'],
                    'json' => [$this->canonical->encode(['metadata' => $metadata, 'sections' => $sections])."\n", 'json', 'application/json'],
                    'csv' => [$this->csv($metadata, $sections), 'csv', 'text/csv; charset=UTF-8'],
                    'xlsx' => [$this->xlsx->build($metadata, $sections), 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    default => throw new RuntimeException('Formato de reporte no soportado.'),
                };
            }

            $path = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/')
                .'/reports/'.$export->school->public_id.'/'.$export->public_id.'.'.$extension;
            $disk = $this->reportDisk();
            if (! $disk->put($path, $contents)) {
                throw new RuntimeException('No se pudo guardar el reporte en almacenamiento privado.');
            }

            $export->forceFill([
                'status' => 'completed',
                'progress' => 100,
                'private_path' => $path,
                'mime_type' => $mime,
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'completed_at' => now('UTC'),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();

            $this->audit->write(
                'lcd.report.completed',
                'generate',
                $export,
                actor: $export->requester,
                schoolId: $export->school_id,
                academicYearId: $export->academic_year_id,
                after: $export->only(['public_id', 'status', 'sha256', 'size_bytes', 'source_snapshot_hash']),
            );
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'failed',
                'progress' => 0,
                'failure_code' => $exception instanceof LibroDigitalException
                    ? $exception->errorCode
                    : 'LCD_REPORT_GENERATION_FAILED',
                'failure_message' => mb_strimwidth($exception->getMessage(), 0, 1800),
            ])->save();

            throw $exception;
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function dataset(ReportExport $export): array
    {
        $school = $export->school;
        $book = $export->book;
        $filters = $export->filters_snapshot ?? [];
        $sessions = DB::table('lcd_class_sessions')
            ->where('school_id', $school->id)
            ->when($book, fn ($query) => $query->where('book_id', $book->id))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('session_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('session_date', '<=', $date));

        $sessionIds = (clone $sessions)->pluck('id');
        $attendance = DB::table('lcd_session_attendance')->whereIn('class_session_id', $sessionIds);
        $counts = (clone $attendance)->selectRaw('status, COUNT(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $recorded = (int) $counts->sum();
        $present = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);
        $rate = $recorded > 0 ? round(($present / $recorded) * 100, 2) : 0.0;

        $periodFrom = $filters['date_from'] ?? $book?->academicYear?->starts_at?->format('d-m-Y') ?? '-';
        $periodTo = $filters['date_to'] ?? $book?->academicYear?->ends_at?->format('d-m-Y') ?? '-';
        $period = $periodFrom.' a '.$periodTo;
        $metadata = [
            'periodo' => $period,
            'año académico' => $book?->academicYear?->name ?? ($export->academic_year_id ?: '-'),
            'tipo de reporte' => $export->title,
            'generado por' => $export->requester?->name ?? 'Sistema',
            'fecha' => now((string) ($school->timezone ?: config('libro_digital.timezone')))->format('d-m-Y H:i'),
            'filtros' => $this->filterSummary($filters, $book),
        ];
        $sections = [[
            'title' => 'Resumen ejecutivo',
            'headers' => ['Indicador', 'Valor'],
            'rows' => [
                ['Asistencia', number_format($rate, 2, ',', '.').' %'],
                ['Meta', 'No configurada'],
                ['Presentes', $present],
                ['Ausentes', (int) ($counts['absent'] ?? 0)],
                ['Justificadas', (clone $attendance)->where('justification_status', 'justified')->count()],
                ['Injustificadas', (clone $attendance)->where('status', 'absent')->where('justification_status', '<>', 'justified')->count()],
                ['Atrasos', (int) ($counts['late'] ?? 0)],
                ['Estudiantes en riesgo', DB::table('lcd_absence_cases')->where('school_id', $school->id)->where('status', 'open')->count()],
                ['Alertas abiertas', DB::table('lcd_absence_cases')->where('school_id', $school->id)->whereIn('status', ['open', 'monitoring'])->count()],
            ],
        ]];

        $sections = [...$sections, ...$this->detailSections($export, $sessions, $sessionIds)];

        return [$metadata, $sections];
    }

    /** @return array<int, array<string, mixed>> */
    private function detailSections(ReportExport $export, $sessions, $sessionIds): array
    {
        if ($export->report_type === 'audit') {
            $events = DB::table('lcd_audit_events')->where('school_id', $export->school_id)
                ->latest('sequence_number')->limit(1000)->get();

            return [[
                'title' => 'Bitácora de auditoría',
                'headers' => ['Secuencia', 'Fecha', 'Evento', 'Acción', 'Registro', 'Hash'],
                'rows' => $events->map(fn ($row) => [$row->sequence_number, $row->occurred_at, $row->event, $row->action, $row->auditable_type.'#'.$row->auditable_id, $row->event_hash])->all(),
            ]];
        }

        if ($export->report_type === 'assessments') {
            $rows = DB::table('lcd_assessments as a')->leftJoin('lcd_student_results as r', 'r.assessment_id', '=', 'a.id')
                ->where('a.school_id', $export->school_id)
                ->when($export->book_id, fn ($query, $id) => $query->where('a.book_id', $id))
                ->groupBy('a.id', 'a.assessment_date', 'a.name', 'a.assessment_type', 'a.status')
                ->orderBy('a.assessment_date')->get([
                    'a.assessment_date', 'a.name', 'a.assessment_type', 'a.status',
                    DB::raw('COUNT(r.id) as result_count'), DB::raw('AVG(r.numeric_value) as average_value'),
                ]);

            return [[
                'title' => 'Evaluaciones',
                'headers' => ['Fecha', 'Evaluación', 'Tipo', 'Estado', 'Resultados', 'Promedio'],
                'rows' => $rows->map(fn ($row) => [$row->assessment_date, $row->name, $row->assessment_type, $row->status, $row->result_count, $row->average_value === null ? '-' : number_format((float) $row->average_value, 1, ',', '.')])->all(),
            ]];
        }

        $sessionRows = (clone $sessions)
            ->leftJoin('lcd_session_attendance as att', 'att.class_session_id', '=', 'lcd_class_sessions.id')
            ->groupBy('lcd_class_sessions.id', 'lcd_class_sessions.session_date', 'lcd_class_sessions.subject_snapshot', 'lcd_class_sessions.teacher_name_snapshot', 'lcd_class_sessions.status', 'lcd_class_sessions.objective_summary', 'lcd_class_sessions.content_summary')
            ->orderBy('lcd_class_sessions.session_date')
            ->get([
                'lcd_class_sessions.session_date', 'lcd_class_sessions.subject_snapshot', 'lcd_class_sessions.teacher_name_snapshot',
                'lcd_class_sessions.status', 'lcd_class_sessions.objective_summary', 'lcd_class_sessions.content_summary',
                DB::raw("SUM(CASE WHEN att.status IN ('present','late') THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN att.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw('COUNT(att.id) as attendance_count'),
            ]);

        if ($export->report_type === 'lesson_records') {
            return [[
                'title' => 'Leccionario',
                'headers' => ['Fecha', 'Asignatura', 'Docente', 'Estado', 'Objetivo', 'Contenido'],
                'rows' => $sessionRows->map(fn ($row) => [$row->session_date, $row->subject_snapshot, $row->teacher_name_snapshot, $row->status, $row->objective_summary, $row->content_summary])->all(),
            ]];
        }

        return [[
            'title' => 'Asistencia por clase',
            'headers' => ['Fecha', 'Asignatura', 'Docente', 'Estado', 'Registrados', 'Presentes', 'Ausentes'],
            'rows' => $sessionRows->map(fn ($row) => [$row->session_date, $row->subject_snapshot, $row->teacher_name_snapshot, $row->status, $row->attendance_count, $row->present_count, $row->absent_count])->all(),
        ]];
    }

    /** @return array<string, mixed> */
    private function loadSnapshot(ReportExport $export): array
    {
        if (! $export->source_private_path || ! $export->source_snapshot_hash) {
            throw new RuntimeException('El reporte no posee una instantánea fuente sellada.');
        }

        $encrypted = $this->reportDisk()->get($export->source_private_path);
        $encoded = Crypt::decryptString($encrypted);
        if (! hash_equals((string) $export->source_snapshot_hash, hash('sha256', $encoded))) {
            throw new RuntimeException('La instantánea fuente del reporte no supera su verificación de integridad.');
        }

        $snapshot = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($snapshot['metadata'] ?? null) || ! is_array($snapshot['sections'] ?? null)) {
            throw new RuntimeException('La instantánea fuente del reporte tiene un formato inválido.');
        }

        return $snapshot;
    }

    public function reportDisk(): Filesystem
    {
        $name = (string) config('libro_digital.storage.disk', 'local');
        $configuration = config('filesystems.disks.'.$name);
        if (! is_array($configuration)) {
            throw new LibroDigitalException(
                'El almacenamiento privado de reportes no está configurado.',
                'LCD_REPORT_STORAGE_NOT_PRIVATE',
                503,
            );
        }

        $visibility = mb_strtolower((string) ($configuration['visibility'] ?? ''));
        $root = rtrim((string) ($configuration['root'] ?? ''), DIRECTORY_SEPARATOR);
        $publicRoot = rtrim(storage_path('app/public'), DIRECTORY_SEPARATOR);
        $insidePublicRoot = $root !== '' && ($root === $publicRoot || str_starts_with($root, $publicRoot.DIRECTORY_SEPARATOR));
        $exposesUrlWithoutPrivateInvariant = filled($configuration['url'] ?? null) && $visibility !== 'private';
        if ($name === 'public' || $visibility === 'public' || $insidePublicRoot || $exposesUrlWithoutPrivateInvariant) {
            throw new LibroDigitalException(
                'El disco configurado para reportes puede exponer archivos sin autenticación; selecciona un disco con visibilidad privada y sin URL pública.',
                'LCD_REPORT_STORAGE_NOT_PRIVATE',
                503,
            );
        }

        return Storage::disk($name);
    }

    /** @return array<string, mixed> */
    private function snapshot(ReportExport $export): array
    {
        if ($export->report_type === 'curriculum_objectives') {
            return $this->curriculumObjectives->snapshot($export);
        }

        [$metadata, $sections] = $this->dataset($export);

        return ['metadata' => $metadata, 'sections' => $sections];
    }

    /** @return array<string, mixed> */
    private function dashboard(ReportExport $export): array
    {
        return [
            'summary' => ['target_rate' => null],
            'branding' => [
                'organization_name' => $export->school->legal_name ?: $export->school->name,
                'report_trace' => 'RBD '.$export->school->rbd.' · ID '.$export->public_id.' · fuente '.substr((string) $export->source_snapshot_hash, 0, 16),
                'source_label' => 'Libro Digital, instantánea sellada '.substr((string) $export->source_snapshot_hash, 0, 16),
                'watermark' => $export->draft_watermark ? 'BORRADOR · NO VALIDADO PARA FISCALIZACIÓN' : '',
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filterSummary(array $filters, ?Book $book): string
    {
        $parts = array_filter([
            $book ? 'Libro '.$book->code : null,
            isset($filters['date_from']) ? 'Desde '.$filters['date_from'] : null,
            isset($filters['date_to']) ? 'Hasta '.$filters['date_to'] : null,
        ]);

        return $parts === [] ? 'Sin filtros adicionales' : implode(' · ', $parts);
    }

    private function title(string $type): string
    {
        return match ($type) {
            'attendance' => 'Informe de asistencia por clase',
            'lesson_records' => 'Informe de leccionario',
            'assessments' => 'Informe de evaluaciones y resultados',
            'audit' => 'Informe de trazabilidad y auditoría',
            'curriculum_objectives' => 'Catálogo de objetivos curriculares',
            default => 'Informe ejecutivo del Libro Digital',
        };
    }

    private function csv(array $metadata, array $sections): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($metadata as $label => $value) {
            fputcsv($stream, [$this->safeCsv($label), $this->safeCsv($value)], ';');
        }
        fputcsv($stream, [], ';');
        foreach ($sections as $section) {
            fputcsv($stream, [$this->safeCsv($section['title'] ?? '')], ';');
            fputcsv($stream, array_map([$this, 'safeCsv'], $section['headers'] ?? []), ';');
            foreach ($section['rows'] ?? [] as $row) {
                fputcsv($stream, array_map([$this, 'safeCsv'], (array) $row), ';');
            }
            fputcsv($stream, [], ';');
        }
        rewind($stream);
        $contents = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $contents;
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
