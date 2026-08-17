<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\CreateReportRequest;
use App\Models\LibroDigital\ReportExport;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CurriculumObjectiveReportService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\LibroDigitalReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly LibroDigitalReportService $reports,
        private readonly CurriculumObjectiveReportService $curriculumObjectives,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $this->school($request);

        $definitions = [
            ['official_roster', 'Nómina oficial', 'Matrícula y vigencias del curso'],
            ['enrollment_movements', 'Matrículas y retiros', 'Altas, retiros y cambios históricos'],
            ['session_attendance', 'Asistencia por sesión', 'Detalle por bloque pedagógico'],
            ['daily_attendance', 'Asistencia diaria', 'Consolidado diario derivado de las sesiones'],
            ['monthly_attendance', 'Asistencia mensual', 'Totales y cuadratura del mes'],
            ['student_absences', 'Inasistencia por estudiante', 'Ausencias, atrasos y justificaciones'],
            ['lesson_records', 'Leccionario', 'Objetivos, contenidos y actividades'],
            ['curriculum_coverage', 'Cobertura curricular', 'Avance declarado por OA y asignatura'],
            ['assessments', 'Evaluaciones y calificaciones', 'Instrumentos, resultados y cierres'],
            ['coexistence', 'Convivencia', 'Registros autorizados de convivencia escolar'],
            ['pie', 'Actividades PIE', 'Trabajo colaborativo y apoyos'],
            ['early_withdrawals', 'Salidas anticipadas', 'Retiros, autorizaciones y retornos'],
            ['prolonged_absences', 'Ausencias prolongadas', 'Casos, acciones y resultados'],
            ['parvularia_book', 'Libro Técnico Pedagógico', 'Planificación y evaluación parvularia'],
            ['amendment_history', 'Historial de modificaciones', 'Revisiones, correcciones y enmiendas'],
            ['audit', 'Auditoría', 'Eventos y cadena de integridad'],
            ['closure_status', 'Estado de cierres', 'Sesiones, días y meses'],
            ['executive', 'Informe ejecutivo', 'Síntesis institucional del periodo'],
            ['curriculum_objectives', 'Objetivos curriculares', 'Catálogo oficial, estado y trazabilidad de fuentes', ['pdf', 'xlsx']],
        ];

        return $this->collectionResponse(collect($definitions)->map(fn (array $definition) => [
            'code' => $definition[0], 'name' => $definition[1], 'description' => $definition[2],
            'formats' => $definition[3] ?? ['pdf', 'xlsx', 'csv', 'json'],
        ])->all());
    }

    public function store(CreateReportRequest $request): JsonResponse
    {
        try {
            $school = $this->school($request);
        } catch (ValidationException) {
            abort(403, 'No tienes acceso vigente al establecimiento solicitado.');
        }
        $book = null;
        if (filled($request->input('book_id'))) {
            $book = $this->book((string) $request->input('book_id'));
            if (! $this->access->canAccessSchool($request->user(), (int) $book->school_id)
                || (int) $book->school_id !== (int) $school->id) {
                throw new LibroDigitalException('El libro no pertenece al establecimiento seleccionado.', 'LCD_REPORT_BOOK_SCOPE_INVALID', 403);
            }
        }
        $filters = [...($request->input('filters', [])), ...array_filter([
            'academic_year_id' => $request->input('academic_year_id'),
            'period' => $request->input('period'),
            'date_from' => $request->input('from'),
            'date_to' => $request->input('to'),
            'teacher_staff_id' => $request->input('teacher_staff_id'),
            'signature_status' => $request->input('signature_status'),
            'normative_profile_id' => $request->input('normative_profile_id'),
        ], fn ($value) => $value !== null && $value !== '')];
        if ($request->string('report_type')->toString() === 'curriculum_objectives') {
            $filters = $this->curriculumObjectives->normalizeFilters(
                $school,
                $book,
                $filters,
                $request->string('format')->toString(),
            );
        }
        $export = $this->reports->request($school, $request->user(), $request->string('report_type')->toString(), $request->string('format')->toString(), $filters, $book);

        return $this->dataResponse($this->payload($export), 202);
    }

    public function history(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $exports = ReportExport::query()->where('school_id', $school->id)
            ->when(! $request->user()->isSuperAdmin() && ! $request->user()->hasPermission('libro_digital.closures.manage'), fn (Builder $query) => $query->where('requested_by', $request->user()->id))
            ->latest()->paginate(min(100, max(1, $request->integer('per_page', 25))));

        return $this->collectionResponse($exports->getCollection()->map(fn (ReportExport $export) => $this->payload($export))->all(), [
            'current_page' => $exports->currentPage(), 'last_page' => $exports->lastPage(), 'per_page' => $exports->perPage(), 'total' => $exports->total(),
        ]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $export = $this->aggregate(ReportExport::class, $report);
        $this->assertReportAccess($request, $export);

        return $this->dataResponse($this->payload($export));
    }

    public function download(Request $request, string $report): StreamedResponse
    {
        $export = $this->aggregate(ReportExport::class, $report);
        $this->assertReportAccess($request, $export, requireExport: true);
        if ($this->statusValue($export->status) !== 'completed' || blank($export->private_path)) {
            throw new LibroDigitalException('El informe aún no está disponible para descarga.', 'LCD_REPORT_NOT_READY', 409);
        }
        if ($export->expires_at?->isPast()) {
            throw new LibroDigitalException('El archivo privado expiró; genera un nuevo informe.', 'LCD_REPORT_EXPIRED', 410);
        }
        $disk = $this->reports->reportDisk();
        if (! $disk->exists($export->private_path)) {
            throw new LibroDigitalException('El archivo no está disponible en el almacenamiento privado.', 'LCD_REPORT_FILE_MISSING', 404);
        }
        if (blank($export->sha256)) {
            throw new LibroDigitalException('El informe no tiene una huella de integridad verificable.', 'LCD_REPORT_HASH_MISSING', 409);
        }

        $stream = $disk->readStream($export->private_path);
        if (! is_resource($stream)) {
            throw new LibroDigitalException('No fue posible verificar el archivo privado.', 'LCD_REPORT_FILE_UNREADABLE', 409);
        }

        $verified = tmpfile();
        if (! is_resource($verified)) {
            fclose($stream);
            throw new LibroDigitalException('No fue posible preparar la descarga verificada.', 'LCD_REPORT_FILE_UNREADABLE', 503);
        }

        try {
            $hash = hash_init('sha256');
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new LibroDigitalException('No fue posible verificar el archivo privado.', 'LCD_REPORT_FILE_UNREADABLE', 409);
                }
                if ($chunk === '') {
                    continue;
                }
                hash_update($hash, $chunk);
                $offset = 0;
                $length = strlen($chunk);
                while ($offset < $length) {
                    $written = fwrite($verified, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new LibroDigitalException('No fue posible preparar la descarga verificada.', 'LCD_REPORT_FILE_UNREADABLE', 503);
                    }
                    $offset += $written;
                }
            }
            $actualHash = hash_final($hash);
        } catch (\Throwable $exception) {
            fclose($verified);

            throw $exception;
        } finally {
            fclose($stream);
        }

        if (! hash_equals(strtolower((string) $export->sha256), strtolower($actualHash))) {
            fclose($verified);
            throw new LibroDigitalException('La integridad del informe no pudo ser verificada.', 'LCD_REPORT_HASH_MISMATCH', 409);
        }
        rewind($verified);

        $this->audit->write(
            'lcd.report.downloaded',
            'download',
            $export,
            actor: $request->user(),
            schoolId: $export->school_id,
            academicYearId: $export->academic_year_id,
            after: $export->only(['public_id', 'report_type', 'format', 'sha256', 'source_snapshot_hash', 'size_bytes']),
            request: $request,
        );

        $filename = 'libro-digital-'.$export->report_type.'-'.$export->public_id.'.'.$export->format;

        $statistics = fstat($verified);
        $size = is_array($statistics) ? ($statistics['size'] ?? null) : null;

        return response()->streamDownload(function () use ($verified): void {
            try {
                while (! feof($verified)) {
                    $chunk = fread($verified, 1024 * 1024);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                }
            } finally {
                fclose($verified);
            }
        }, $filename, array_filter([
            'Content-Type' => $export->mime_type ?: 'application/octet-stream',
            'Content-Length' => is_int($size) ? (string) $size : null,
            'Cache-Control' => 'private, no-store, max-age=0',
        ], fn ($value) => $value !== null));
    }

    private function assertReportAccess(Request $request, ReportExport $export, bool $requireExport = false): void
    {
        $user = $request->user();
        $canView = $user->hasPermission('libro_digital.reports.view');
        $canExportOwn = $user->hasPermission('libro_digital.reports.export')
            && (int) $export->requested_by === (int) $user->id;
        if (! $this->access->canAccessSchool($user, (int) $export->school_id)
            || ($requireExport && ! $user->hasPermission('libro_digital.reports.export'))
            || (! $canView && ! $canExportOwn)) {
            abort(403);
        }
    }

    /** @return array<string, mixed> */
    private function payload(ReportExport $export): array
    {
        return [
            'id' => $export->id, 'public_id' => $export->public_id, 'report_identifier' => $export->public_id,
            'school_id' => $export->school_id, 'academic_year_id' => $export->academic_year_id, 'book_id' => $export->book_id,
            'report_type' => $export->report_type, 'report_name' => $export->title, 'title' => $export->title,
            'format' => $export->format, 'status' => $this->statusValue($export->status), 'progress' => (int) $export->progress,
            'progress_percent' => (int) $export->progress,
            'draft_watermark' => (bool) $export->draft_watermark, 'filename' => $export->private_path ? basename($export->private_path) : null,
            'mime_type' => $export->mime_type, 'size_bytes' => $export->size_bytes, 'sha256' => $export->sha256,
            'source_snapshot_hash' => $export->source_snapshot_hash, 'filters' => $export->filters_snapshot,
            'failure_code' => $export->failure_code, 'failure_message' => $export->failure_message,
            'requested_at' => $export->requested_at?->toIso8601String(), 'started_at' => $export->started_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(), 'expires_at' => $export->expires_at?->toIso8601String(),
            'created_at' => $export->created_at?->toIso8601String(),
        ];
    }
}
