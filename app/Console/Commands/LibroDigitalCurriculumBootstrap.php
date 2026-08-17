<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\LibroDigital\School;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CurriculumImportService;
use App\Services\LibroDigital\CurriculumXlsxReader;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LibroDigitalCurriculumBootstrap extends Command
{
    protected $signature = 'lcd:curriculum:bootstrap
        {--school= : ID, public_id o RBD del establecimiento}
        {--year= : ID o año académico}
        {--xlsx= : Ruta absoluta del XLSX gobernado}
        {--evidence-dir= : Directorio que contiene los documentos oficiales}
        {--operator-email= : Cuenta real que solicita la validación}
        {--system-request : Registra la solicitud como proceso técnico, sin atribuirla a una persona}
        {--apply : Crea únicamente las asignaturas faltantes y registra el lote validado}
        {--validate-only : Confirma que el comando nunca aprobará ni activará el lote}
        {--confirm= : Debe ser CREAR-Y-VALIDAR al aplicar}';

    protected $description = 'Prepara asignaturas desde el XLSX curricular y registra un lote validado, sin aprobarlo ni activarlo.';

    /** @var array<string, string> */
    private const TRACK_COLORS = [
        'PARVULARIA' => '#0F766E',
        'GENERAL' => '#2563EB',
        'HC' => '#7C3AED',
        'TP' => '#B45309',
        'ARTISTICA' => '#BE185D',
    ];

    public function __construct(
        private readonly CurriculumXlsxReader $reader,
        private readonly CurriculumImportService $imports,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $xlsx = $this->absoluteFile((string) $this->option('xlsx'), 'XLSX');
            $evidenceDir = $this->absoluteDirectory((string) $this->option('evidence-dir'), 'directorio de evidencias');
            $school = $this->school((string) $this->option('school'));
            $year = $this->academicYear($school, (string) $this->option('year'));
            $actor = $this->actor($school);
            $workbook = $this->reader->read($xlsx);
            $subjects = $this->normalizedSubjects($this->reader->readSubjects($xlsx));
            $activeCodes = $this->activeLinkCodes((array) $workbook['links']);
            $evidenceFiles = $this->resolveEvidenceFiles((array) $workbook['sources'], $evidenceDir);
            $existing = ScheduleSubject::query()->whereIn('code', array_keys($subjects))->get()->keyBy('code');
            $conflicts = $this->subjectConflicts($subjects, $existing->all());

            if ($conflicts !== []) {
                $this->components->error('Hay códigos existentes con otro nombre; no se modificó ningún registro.');
                foreach ($conflicts as $conflict) {
                    $this->line(' - '.$conflict);
                }

                return self::FAILURE;
            }

            $missingCodes = array_values(array_diff(array_keys($subjects), $existing->keys()->all()));
            $summary = [
                'mode' => $this->option('apply') ? 'apply' : 'dry-run',
                'school' => ['id' => $school->id, 'rbd' => $school->rbd, 'name' => $school->name],
                'academic_year' => ['id' => $year->id, 'year' => $year->year],
                'workbook' => [
                    'path' => $xlsx,
                    'sha256' => (string) $workbook['file_hash'],
                    'bytes' => (int) $workbook['size_bytes'],
                ],
                'requester' => $actor
                    ? ['type' => 'user', 'id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email]
                    : ['type' => 'system'],
                'subjects' => [
                    'declared' => count($subjects),
                    'existing' => $existing->count(),
                    'to_create' => count($missingCodes),
                    'active_from_links' => $activeCodes,
                    'inactive' => count($subjects) - count($activeCodes),
                ],
                'curriculum' => [
                    'objective_rows' => count((array) $workbook['objectives']),
                    'source_rows' => count((array) $workbook['sources']),
                    'objective_source_rows' => count((array) $workbook['objective_sources']),
                    'link_rows' => count((array) $workbook['links']),
                    'verified_evidence_files' => array_keys($evidenceFiles),
                ],
            ];

            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            if (! $this->option('apply')) {
                $this->components->info('Dry-run finalizado: no se escribieron asignaturas, lotes ni evidencias.');

                return self::SUCCESS;
            }
            if (! $this->option('validate-only')) {
                $this->components->error('Usa --validate-only para dejar explícito que no habrá aprobación ni activación automática.');

                return self::INVALID;
            }
            if ((string) $this->option('confirm') !== 'CREAR-Y-VALIDAR') {
                $this->components->error('Para aplicar debes usar --confirm=CREAR-Y-VALIDAR.');

                return self::INVALID;
            }

            $request = $this->request($school, $year, $actor, $xlsx, $evidenceFiles, (string) $workbook['file_hash']);
            $created = $this->createSubjects($subjects, $activeCodes);
            if ($created !== []) {
                $this->audit->write(
                    eventType: 'curriculum.subject_catalog.bootstrapped',
                    action: 'create',
                    auditable: $school,
                    actor: $actor,
                    schoolId: $school->id,
                    academicYearId: $year->id,
                    before: ['workbook_subjects_present' => count($subjects) - count($created)],
                    after: [
                        'workbook_subjects_present' => count($subjects),
                        'created_count' => count($created),
                        'created_codes_hash' => hash('sha256', implode('|', $created)),
                        'active_codes' => $activeCodes,
                        'workbook_sha256' => $workbook['file_hash'],
                    ],
                    reason: 'Alta idempotente del catálogo curricular desde XLSX; solo las asignaturas vinculadas a la oferta local quedaron activas.',
                    request: $request,
                );
            }

            $xlsxUpload = $request->file('file');
            if (! $xlsxUpload instanceof UploadedFile) {
                throw new RuntimeException('No se pudo construir la carga interna del XLSX.');
            }
            $result = $this->imports->validateUpload($xlsxUpload, $school, $year, $actor, $request);
            $batch = $result['batch']->fresh();
            $final = [
                'created_subjects' => count($created),
                'subject_total' => ScheduleSubject::query()->whereIn('code', array_keys($subjects))->count(),
                'batch' => [
                    'id' => $batch->id,
                    'public_id' => $batch->public_id,
                    'status' => $batch->status,
                    'lock_version' => $batch->lock_version,
                    'created' => (bool) $result['created'],
                    'objective_count' => $batch->objective_count,
                    'error_count' => $batch->error_count,
                    'warning_count' => $batch->warning_count,
                    'source_evidence' => data_get($batch->manifest, 'source_evidence'),
                ],
                'next_step' => 'Revisión y aprobación humana independiente; este comando no aprueba ni activa.',
            ];
            $this->newLine();
            $this->line(json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            if ($batch->status !== 'validated') {
                $this->components->error('El lote fue registrado, pero no quedó validado. Revisa validation_errors.');

                return self::FAILURE;
            }

            $this->components->info('Catálogo preparado y lote validado. No se ejecutó aprobación ni activación.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @param list<array<string, mixed>> $rows @return array<string, array<string, mixed>> */
    private function normalizedSubjects(array $rows): array
    {
        $subjects = [];
        foreach ($rows as $row) {
            $code = mb_strtoupper(trim((string) ($row['codigo_tecnico'] ?? '')));
            $name = trim((string) ($row['asignatura_ambito'] ?? ''));
            $track = mb_strtoupper(trim((string) ($row['trayectoria'] ?? '')));
            if (! preg_match('/^[A-Z0-9._-]{1,50}$/', $code)) {
                throw new RuntimeException("Código de asignatura inválido en la fila {$row['_row']}: {$code}");
            }
            if ($name === '' || mb_strlen($name) > 255) {
                throw new RuntimeException("Nombre de asignatura inválido en la fila {$row['_row']}: {$code}");
            }
            if (! isset(self::TRACK_COLORS[$track])) {
                throw new RuntimeException("Trayectoria curricular inválida en la fila {$row['_row']}: {$track}");
            }
            if (isset($subjects[$code]) && $subjects[$code]['name'] !== $name) {
                throw new RuntimeException("El código {$code} aparece con dos nombres distintos en la hoja Asignaturas.");
            }
            $subjects[$code] = [
                'name' => $name,
                'code' => $code,
                'color' => self::TRACK_COLORS[$track],
                'area' => 'Currículum Nacional · '.$track,
                'track' => $track,
            ];
        }
        ksort($subjects);

        if (count($subjects) !== 129) {
            throw new RuntimeException('El inventario debe contener exactamente 129 asignaturas; se leyeron '.count($subjects).'.');
        }

        return $subjects;
    }

    /** @param list<array<string, mixed>> $rows @return list<string> */
    private function activeLinkCodes(array $rows): array
    {
        $codes = collect($rows)
            ->filter(fn (array $row): bool => $this->truthy($row['active'] ?? null))
            ->map(fn (array $row): string => mb_strtoupper(trim((string) ($row['subject_code'] ?? ''))))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($codes === []) {
            throw new RuntimeException('El XLSX no declara asignaturas activas en la hoja Vinculos.');
        }

        return $codes;
    }

    /** @param array<string, array<string, mixed>> $subjects @param array<string, ScheduleSubject> $existing @return list<string> */
    private function subjectConflicts(array $subjects, array $existing): array
    {
        $conflicts = [];
        foreach ($existing as $code => $model) {
            $expected = $subjects[$code]['name'] ?? null;
            if ($expected !== null && $this->fold($model->name) !== $this->fold($expected)) {
                $conflicts[] = "{$code}: existe como «{$model->name}», XLSX declara «{$expected}»";
            }
        }

        return $conflicts;
    }

    /** @param array<string, array<string, mixed>> $subjects @param list<string> $activeCodes @return list<string> */
    private function createSubjects(array $subjects, array $activeCodes): array
    {
        $active = array_fill_keys($activeCodes, true);

        return DB::transaction(function () use ($subjects, $active): array {
            $created = [];
            foreach ($subjects as $code => $payload) {
                $existing = ScheduleSubject::query()->where('code', $code)->first();
                if ($existing) {
                    if ($this->fold($existing->name) !== $this->fold($payload['name'])) {
                        throw new RuntimeException("Conflicto concurrente para la asignatura {$code}; no se sobrescribió.");
                    }

                    continue;
                }
                ScheduleSubject::query()->create([
                    'name' => $payload['name'],
                    'code' => $code,
                    'color' => $payload['color'],
                    'area' => $payload['area'],
                    'active' => isset($active[$code]),
                ]);
                $created[] = $code;
            }

            sort($created);

            return $created;
        }, 3);
    }

    /** @param list<array<string, mixed>> $sources @return array<string, string> */
    private function resolveEvidenceFiles(array $sources, string $directory): array
    {
        $hashToKey = [];
        foreach ($sources as $source) {
            $key = mb_strtoupper(trim((string) ($source['source_key'] ?? '')));
            $hash = mb_strtolower(trim((string) ($source['source_sha256'] ?? '')));
            if (! preg_match('/^[A-Z0-9._-]{1,100}$/', $key) || ! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new RuntimeException('La hoja Fuentes contiene una clave o SHA-256 inválido.');
            }
            $hashToKey[$hash] = $key;
        }

        $matches = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if (! $entry->isFile() || ! $entry->isReadable()) {
                continue;
            }
            $path = $entry->getPathname();
            $hash = hash_file('sha256', $path);
            if (isset($hashToKey[$hash])) {
                $matches[$hashToKey[$hash]] = $path;
            }
        }
        ksort($matches);
        $missing = array_values(array_diff(array_values($hashToKey), array_keys($matches)));
        if ($missing !== []) {
            throw new RuntimeException('Faltan evidencias oficiales o no coinciden sus hashes: '.implode(', ', $missing));
        }

        return $matches;
    }

    /** @param array<string, string> $evidenceFiles */
    private function request(School $school, AcademicYear $year, ?User $actor, string $xlsx, array $evidenceFiles, string $sourceHash): Request
    {
        $evidenceUploads = [];
        foreach ($evidenceFiles as $key => $path) {
            $evidenceUploads[$key] = new UploadedFile(
                $path,
                basename($path),
                mime_content_type($path) ?: 'application/octet-stream',
                null,
                true,
            );
        }
        $request = Request::create(
            '/internal/libro-digital/curriculum/bootstrap',
            'POST',
            ['school_id' => $school->id, 'academic_year_id' => $year->id],
            [],
            [
                'file' => new UploadedFile(
                    $xlsx,
                    basename($xlsx),
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                ),
                'evidence_files' => $evidenceUploads,
            ],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'lcd-curriculum-bootstrap-'.$school->id.'-'.$year->id.'-'.substr($sourceHash, 0, 20),
                'HTTP_X_REQUEST_ID' => (string) Str::ulid(),
            ],
        );
        $request->attributes->set('lcd_correlation_id', (string) Str::ulid());
        $request->setUserResolver(fn (): ?User => $actor);

        return $request;
    }

    private function actor(School $school): ?User
    {
        $email = trim((string) $this->option('operator-email'));
        $system = (bool) $this->option('system-request');
        if ($email !== '' && $system) {
            throw new RuntimeException('Usa --operator-email o --system-request, no ambos.');
        }
        if (! $this->option('apply')) {
            return $email !== '' ? User::query()->where('email', $email)->first() : null;
        }
        if ($system) {
            return null;
        }
        if ($email === '') {
            throw new RuntimeException('Al aplicar debes indicar --operator-email o --system-request.');
        }
        $actor = User::query()->where('email', $email)->where('active', true)->first();
        if (! $actor) {
            throw new RuntimeException('No existe un operador activo con el correo indicado.');
        }
        if (! $actor->hasPermission('libro_digital.subject_catalog.manage')
            || ! $actor->hasPermission('libro_digital.curriculum.import')) {
            throw new RuntimeException('El operador no tiene permisos para administrar asignaturas e importar currículo.');
        }
        $hasSchoolAccess = $actor->isSuperAdmin() || $school->users()
            ->where('users.id', $actor->id)
            ->wherePivot('active', true)
            ->exists();
        if (! $hasSchoolAccess) {
            throw new RuntimeException('El operador no tiene alcance vigente en el establecimiento.');
        }

        return $actor;
    }

    private function school(string $identifier): School
    {
        if ($identifier === '') {
            throw new RuntimeException('Debes indicar --school.');
        }
        $school = School::query()
            ->where('active', true)
            ->where(function ($query) use ($identifier): void {
                if (ctype_digit($identifier)) {
                    $query->whereKey((int) $identifier)->orWhere('rbd', $identifier);
                } else {
                    $query->where('public_id', $identifier)->orWhere('rbd', $identifier);
                }
            })
            ->first();
        if (! $school) {
            throw new RuntimeException('No existe un establecimiento activo para --school.');
        }

        return $school;
    }

    private function academicYear(School $school, string $identifier): AcademicYear
    {
        if ($identifier === '') {
            throw new RuntimeException('Debes indicar --year.');
        }
        $query = $school->academicYears()->wherePivot('active', true);
        $year = ctype_digit($identifier) && (int) $identifier >= 1900
            ? $query->where('academic_years.year', (int) $identifier)->first()
            : $query->where('academic_years.id', (int) $identifier)->first();
        if (! $year) {
            throw new RuntimeException('El año académico no está vinculado y activo para el establecimiento.');
        }
        if ($year->is_closed) {
            throw new RuntimeException('El año académico está cerrado.');
        }

        return $year;
    }

    private function absoluteFile(string $path, string $label): string
    {
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real) || ! str_starts_with($real, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("La ruta de {$label} debe ser absoluta, legible y existente.");
        }

        return $real;
    }

    private function absoluteDirectory(string $path, string $label): string
    {
        $real = realpath($path);
        if ($real === false || ! is_dir($real) || ! is_readable($real) || ! str_starts_with($real, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("La ruta de {$label} debe ser absoluta, legible y existente.");
        }

        return $real;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(mb_strtoupper(trim((string) $value)), ['1', 'SI', 'SÍ', 'TRUE', 'YES'], true);
    }

    private function fold(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
