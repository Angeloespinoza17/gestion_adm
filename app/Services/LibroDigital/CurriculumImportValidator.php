<?php

namespace App\Services\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\School;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Schedule\StudyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CurriculumImportValidator
{
    public const LEVEL_CODES = ['PARVULARIA', 'BASICA', 'MEDIA'];

    public const GRADE_CODES = [
        'NT1', 'NT2',
        '1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B',
        '1M', '2M', '3M', '4M',
    ];

    public const OBJECTIVE_TYPES = ['OA', 'OAT', 'OAH', 'OAA', 'OAG', 'OAC'];

    public const CURRICULUM_TRACKS = ['PARVULARIA', 'GENERAL', 'HC', 'TP', 'ARTISTICA'];

    public const SOURCE_SCOPES = [
        'PARVULARIA_NT1_NT2', 'GENERAL_1B_6B', 'GENERAL_7B_2M',
        'HC_3M_4M', 'TP_3M_4M', 'ARTISTICA_3M_4M',
    ];

    public const SOURCE_ROLES = ['canonical_text', 'legal_basis', 'amendment', 'supersedes', 'complementary'];

    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly CurriculumCorpusHasher $corpus,
        private readonly CurriculumGradeResolver $grades,
    ) {}

    /**
     * @param  array<string, mixed>  $workbook
     * @return array{
     *   valid:bool,
     *   payload:array<string, mixed>,
     *   errors:list<array<string, mixed>>,
     *   warnings:list<array<string, mixed>>,
     *   manifest:array<string, mixed>,
     *   manifest_hash:string
     * }
     */
    public function validate(array $workbook, School $school, AcademicYear $academicYear): array
    {
        $errors = [];
        $warnings = [];
        $catalogRows = array_values((array) ($workbook['catalogs'] ?? []));
        if (count($catalogRows) !== 1) {
            $errors[] = $this->error('Catalogo', null, 'catalog_code', 'catalog_count', 'La importación debe contener exactamente una fila de catálogo.');
        }
        $catalog = $this->catalog($catalogRows[0] ?? [], $errors);

        $subjects = ScheduleSubject::query()
            ->whereNotNull('code')
            ->get(['id', 'code', 'name', 'active'])
            ->keyBy(fn (ScheduleSubject $subject): string => $this->code($subject->code));

        $sources = $this->sources((array) ($workbook['sources'] ?? []), $subjects, $errors);

        $objectives = [];
        $objectiveCodes = [];
        foreach ((array) ($workbook['objectives'] ?? []) as $row) {
            $normalized = $this->objective((array) $row, $catalog, $subjects, $errors);
            if ($normalized === null) {
                continue;
            }
            $identity = $normalized['objective_key'];
            if (isset($objectiveCodes[$identity])) {
                $errors[] = $this->error(
                    (string) ($row['_sheet'] ?? 'Objetivos'),
                    (int) ($row['_row'] ?? 0),
                    'code',
                    'duplicate_objective',
                    "El objetivo {$normalized['code']} repite exactamente tipo, asignatura, grado, track y eje; también aparece en la fila {$objectiveCodes[$identity]}.",
                );

                continue;
            }
            $objectiveCodes[$identity] = (int) ($row['_row'] ?? 0);
            $objectives[] = $normalized;
        }

        $objectiveSources = $this->objectiveSources(
            (array) ($workbook['objective_sources'] ?? []),
            $objectives,
            $sources,
            $errors,
            $warnings,
        );

        $links = [];
        $linkScopes = [];
        foreach ((array) ($workbook['links'] ?? []) as $row) {
            $normalized = $this->link((array) $row, $catalog, $school, $academicYear, $subjects, $errors);
            if ($normalized === null) {
                continue;
            }
            $identity = implode('|', [
                $normalized['schedule_subject_id'],
                $normalized['level_code'],
                $normalized['grade_code'],
                $normalized['curriculum_track'] ?? '',
            ]);
            if (isset($linkScopes[$identity])) {
                $errors[] = $this->error(
                    (string) ($row['_sheet'] ?? 'Vinculos'),
                    (int) ($row['_row'] ?? 0),
                    'subject_code',
                    'duplicate_link',
                    "El vínculo de asignatura, nivel y grado está repetido; también aparece en la fila {$linkScopes[$identity]}.",
                );

                continue;
            }
            $linkScopes[$identity] = (int) ($row['_row'] ?? 0);
            $links[] = $normalized;
        }

        if ($objectives === []) {
            $errors[] = $this->error('Objetivos', null, 'code', 'objectives_empty', 'El archivo no contiene objetivos curriculares válidos.');
        }
        if ($links === []) {
            $errors[] = $this->error('Vinculos', null, 'subject_code', 'links_empty', 'El archivo no contiene vínculos curriculares válidos.');
        }

        $this->validateLinkedObjectives($objectives, $links, $errors);
        $coverage = $this->relevantCoverage($school, $academicYear, $links, $objectives, $errors, $warnings);
        $payload = [
            'catalog' => $catalog,
            'sources' => $sources,
            'objectives' => $objectives,
            'objective_sources' => $objectiveSources,
            'links' => $links,
            'references' => array_values((array) ($workbook['references'] ?? [])),
            'coverage' => $coverage,
        ];
        $catalogPayload = $this->corpus->payload($catalog, $sources, $objectives, $objectiveSources);
        $manifest = [
            'schema' => 'lcd-curriculum-import/v1',
            'school' => ['id' => $school->id, 'public_id' => $school->public_id, 'rbd' => $school->rbd],
            'academic_year' => ['id' => $academicYear->id, 'year' => $academicYear->year],
            'catalog' => [
                'code' => $catalog['code'] ?? null,
                'name' => $catalog['name'] ?? null,
                'version' => $catalog['version'] ?? null,
                'declared_source_hash' => $catalog['declared_source_hash'] ?? null,
            ],
            'artifact_hash' => (string) ($workbook['file_hash'] ?? ''),
            'catalog_payload_hash' => $this->canonical->hash($catalogPayload),
            'corpus_hashes' => [
                'catalog' => $this->canonical->hash($catalogPayload['catalog']),
                'sources' => $this->canonical->hash($catalogPayload['sources']),
                'objectives' => $this->canonical->hash($catalogPayload['objectives']),
                'objective_sources' => $this->canonical->hash($catalogPayload['objective_sources']),
            ],
            'size_bytes' => (int) ($workbook['size_bytes'] ?? 0),
            'counts' => [
                'catalogs' => count($catalogRows),
                'objectives' => count($objectives),
                'active_objectives' => collect($objectives)->where('active', true)->count(),
                'sources' => count($sources),
                'objective_sources' => count($objectiveSources),
                'links' => count($links),
                'active_links' => collect($links)->where('active', true)->count(),
                'errors' => count($errors),
                'warnings' => count($warnings),
            ],
            'coverage' => $coverage,
            'normalized_payload_hash' => $this->canonical->hash($payload),
        ];

        return [
            'valid' => $errors === [],
            'payload' => $payload,
            'errors' => array_slice($errors, 0, 500),
            'warnings' => array_slice($warnings, 0, 200),
            'manifest' => $manifest,
            'manifest_hash' => $this->canonical->hash($manifest),
        ];
    }

    /** @param array<string, mixed> $row @param list<array<string, mixed>> $errors @return array<string, mixed> */
    private function catalog(array $row, array &$errors): array
    {
        $sheet = (string) ($row['_sheet'] ?? 'Catalogo');
        $number = (int) ($row['_row'] ?? 0);
        $code = $this->code($row['catalog_code'] ?? null);
        $name = $this->text($row['catalog_name'] ?? null);
        $version = $this->identifier($row['version'] ?? null);
        $authority = $this->text($row['authority'] ?? null);
        $sourceUrl = $this->text($row['source_url'] ?? null);
        $declaredHash = mb_strtolower($this->text($row['source_sha256'] ?? null) ?? '');
        $effectiveFrom = $this->date($row['effective_from'] ?? null);
        $effectiveTo = $this->date($row['effective_to'] ?? null);

        $this->required($code, $sheet, $number, 'catalog_code', $errors);
        $this->required($name, $sheet, $number, 'catalog_name', $errors);
        $this->required($version, $sheet, $number, 'version', $errors);
        $this->required($authority, $sheet, $number, 'authority', $errors);
        if ($code !== null && ! preg_match('/^[A-Z0-9._-]{1,100}$/', $code)) {
            $errors[] = $this->error($sheet, $number, 'catalog_code', 'format', 'Usa solo letras, números, punto, guion o guion bajo en catalog_code.');
        }
        if ($version !== null && ! preg_match('/^[A-Za-z0-9._-]{1,50}$/', $version)) {
            $errors[] = $this->error($sheet, $number, 'version', 'format', 'La versión contiene caracteres no permitidos.');
        }
        if ($name !== null && mb_strlen($name) > 255) {
            $errors[] = $this->error($sheet, $number, 'catalog_name', 'max', 'El nombre del catálogo supera 255 caracteres.');
        }
        if ($authority !== null && mb_strlen($authority) > 160) {
            $errors[] = $this->error($sheet, $number, 'authority', 'max', 'La autoridad supera 160 caracteres.');
        }
        if ($sourceUrl !== null && (! filter_var($sourceUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true))) {
            $errors[] = $this->error($sheet, $number, 'source_url', 'url', 'source_url debe ser una URL HTTP o HTTPS válida.');
        }
        if ($declaredHash !== '' && ! preg_match('/^[a-f0-9]{64}$/', $declaredHash)) {
            $errors[] = $this->error($sheet, $number, 'source_sha256', 'sha256', 'source_sha256 debe contener 64 caracteres hexadecimales.');
        }
        if (($row['effective_from'] ?? null) !== null && $effectiveFrom === null) {
            $errors[] = $this->error($sheet, $number, 'effective_from', 'date', 'effective_from debe usar formato YYYY-MM-DD.');
        }
        if (($row['effective_to'] ?? null) !== null && $this->text($row['effective_to']) !== null && $effectiveTo === null) {
            $errors[] = $this->error($sheet, $number, 'effective_to', 'date', 'effective_to debe usar formato YYYY-MM-DD.');
        }
        if ($effectiveFrom && $effectiveTo && $effectiveTo < $effectiveFrom) {
            $errors[] = $this->error($sheet, $number, 'effective_to', 'date_order', 'effective_to no puede ser anterior a effective_from.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'version' => $version,
            'authority' => $authority,
            'source_url' => $sourceUrl,
            'declared_source_hash' => $declaredHash !== '' ? $declaredHash : null,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<string, ScheduleSubject>  $subjects
     * @param  list<array<string, mixed>>  $errors
     * @return list<array<string, mixed>>
     */
    private function sources(array $rows, Collection $subjects, array &$errors): array
    {
        $sources = [];
        $keys = [];
        $hashes = [];
        foreach ($rows as $row) {
            $sheet = (string) ($row['_sheet'] ?? 'Fuentes');
            $number = (int) ($row['_row'] ?? 0);
            $key = $this->code($row['source_key'] ?? null);
            $scope = $this->code($row['source_scope'] ?? null);
            $name = $this->text($row['source_name'] ?? null);
            $authority = $this->text($row['authority'] ?? null);
            $documentNumber = $this->text($row['document_number'] ?? null);
            $url = $this->text($row['source_url'] ?? null);
            $hash = mb_strtolower($this->text($row['source_sha256'] ?? null) ?? '');
            $effectiveFrom = $this->date($row['effective_from'] ?? null);
            $effectiveTo = $this->date($row['effective_to'] ?? null);
            $track = $this->code($row['curriculum_track'] ?? null);
            $subjectCode = $this->code($row['subject_code'] ?? null);
            $type = $this->code($row['objective_type'] ?? null);

            foreach (['source_key' => $key, 'source_scope' => $scope, 'source_name' => $name, 'authority' => $authority, 'document_number' => $documentNumber, 'source_url' => $url, 'source_sha256' => $hash] as $field => $value) {
                $this->required($value, $sheet, $number, $field, $errors);
            }
            if ($key !== null && ! preg_match('/^[A-Z0-9._-]{1,100}$/', $key)) {
                $errors[] = $this->error($sheet, $number, 'source_key', 'format', 'source_key debe ser un identificador estable con letras, números, punto, guion o guion bajo.');
            }
            if ($key !== null && isset($keys[$key])) {
                $errors[] = $this->error($sheet, $number, 'source_key', 'duplicate_source_key', "source_key {$key} ya aparece en la fila {$keys[$key]}.");
            }
            if ($key !== null) {
                $keys[$key] = $number;
            }
            if ($scope !== null && ! in_array($scope, self::SOURCE_SCOPES, true)) {
                $errors[] = $this->error($sheet, $number, 'source_scope', 'enum', 'source_scope no corresponde a un tramo curricular permitido.');
            }
            if ($name !== null && mb_strlen($name) > 255) {
                $errors[] = $this->error($sheet, $number, 'source_name', 'max', 'source_name supera 255 caracteres.');
            }
            if ($authority !== null && mb_strlen($authority) > 160) {
                $errors[] = $this->error($sheet, $number, 'authority', 'max', 'authority supera 160 caracteres.');
            }
            if ($documentNumber !== null && mb_strlen($documentNumber) > 100) {
                $errors[] = $this->error($sheet, $number, 'document_number', 'max', 'document_number supera 100 caracteres.');
            }
            if ($url !== null && (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true))) {
                $errors[] = $this->error($sheet, $number, 'source_url', 'url', 'source_url debe ser una URL HTTP o HTTPS válida.');
            }
            if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                $errors[] = $this->error($sheet, $number, 'source_sha256', 'sha256', 'source_sha256 debe contener 64 caracteres hexadecimales.');
            } elseif (isset($hashes[$hash]) && $hashes[$hash] !== $key) {
                $errors[] = $this->error($sheet, $number, 'source_sha256', 'duplicate_source_hash', "El mismo archivo fue declarado con dos source_key ({$hashes[$hash]} y {$key}). Usa una sola fuente y relaciónala N:M.");
            } else {
                $hashes[$hash] = $key;
            }
            if (($row['effective_from'] ?? null) !== null && $this->text($row['effective_from']) !== null && $effectiveFrom === null) {
                $errors[] = $this->error($sheet, $number, 'effective_from', 'date', 'effective_from debe usar YYYY-MM-DD.');
            }
            if (($row['effective_to'] ?? null) !== null && $this->text($row['effective_to']) !== null && $effectiveTo === null) {
                $errors[] = $this->error($sheet, $number, 'effective_to', 'date', 'effective_to debe usar YYYY-MM-DD.');
            }
            if ($effectiveFrom && $effectiveTo && $effectiveTo < $effectiveFrom) {
                $errors[] = $this->error($sheet, $number, 'effective_to', 'date_order', 'effective_to no puede ser anterior a effective_from.');
            }
            if ($track !== null && ! in_array($track, self::CURRICULUM_TRACKS, true)) {
                $errors[] = $this->error($sheet, $number, 'curriculum_track', 'enum', 'curriculum_track de la fuente no es válido.');
            }
            if ($subjectCode !== null && ! $subjects->has($subjectCode)) {
                $errors[] = $this->error($sheet, $number, 'subject_code', 'subject_missing', "No existe una asignatura con código {$subjectCode}.");
            }
            if ($type !== null && ! in_array($type, self::OBJECTIVE_TYPES, true)) {
                $errors[] = $this->error($sheet, $number, 'objective_type', 'enum', 'objective_type de la fuente no es válido.');
            }

            $sources[] = [
                'row' => $number,
                'source_key' => $key,
                'source_scope' => $scope,
                'source_name' => $name,
                'authority' => $authority,
                'document_number' => $documentNumber,
                'source_url' => $url,
                'source_sha256' => $hash,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'curriculum_track' => $track,
                'schedule_subject_id' => $subjectCode !== null ? $subjects->get($subjectCode)?->id : null,
                'subject_code' => $subjectCode,
                'objective_type' => $type,
            ];
        }
        if ($sources === []) {
            $errors[] = $this->error('Fuentes', null, 'source_key', 'sources_empty', 'La importación debe declarar sus fuentes oficiales.');
        }

        return $sources;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $objectives
     * @param  list<array<string, mixed>>  $sources
     * @param  list<array<string, mixed>>  $errors
     * @param  list<array<string, mixed>>  $warnings
     * @return list<array<string, mixed>>
     */
    private function objectiveSources(array $rows, array $objectives, array $sources, array &$errors, array &$warnings): array
    {
        $objectivesByKey = collect($objectives)->keyBy('objective_key');
        $sourcesByKey = collect($sources)->keyBy('source_key');
        $relationships = [];
        $seen = [];
        foreach ($rows as $row) {
            $sheet = (string) ($row['_sheet'] ?? 'ObjetivoFuentes');
            $number = (int) ($row['_row'] ?? 0);
            $identity = [
                'code' => $this->identifier($row['objective_code'] ?? null),
                'objective_type' => $this->code($row['objective_type'] ?? null),
                'subject_code' => $this->code($row['subject_code'] ?? null),
                'level_code' => $this->code($row['level_code'] ?? null),
                'grade_code' => $this->code($row['grade_code'] ?? null),
                'curriculum_track' => $this->code($row['curriculum_track'] ?? null),
                'axis_code' => $this->code($row['axis_code'] ?? null),
            ];
            $objectiveKey = CurriculumObjectiveIdentity::key($identity);
            $sourceKey = $this->code($row['source_key'] ?? null);
            $role = mb_strtolower($this->text($row['source_role'] ?? null) ?? '');
            $locator = $this->text($row['source_locator'] ?? null);
            foreach (['objective_code' => $identity['code'], 'objective_type' => $identity['objective_type'], 'level_code' => $identity['level_code'], 'grade_code' => $identity['grade_code'], 'source_key' => $sourceKey, 'source_role' => $role, 'source_locator' => $locator] as $field => $value) {
                $this->required($value, $sheet, $number, $field, $errors);
            }
            $objective = $objectivesByKey->get($objectiveKey);
            if (! $objective) {
                $errors[] = $this->error($sheet, $number, 'objective_code', 'objective_reference_missing', 'La identidad del objetivo no coincide exactamente con una fila de Objetivos.');
            }
            $source = $sourceKey !== null ? $sourcesByKey->get($sourceKey) : null;
            if (! $source) {
                $errors[] = $this->error($sheet, $number, 'source_key', 'source_reference_missing', "No existe la fuente {$sourceKey}.");
            }
            if ($role !== '' && ! in_array($role, self::SOURCE_ROLES, true)) {
                $errors[] = $this->error($sheet, $number, 'source_role', 'enum', 'source_role debe ser canonical_text, legal_basis, amendment, supersedes o complementary.');
            }
            if ($locator !== null && mb_strlen($locator) > 160) {
                $errors[] = $this->error($sheet, $number, 'source_locator', 'max', 'source_locator supera 160 caracteres.');
            }
            if ($objective && $source && ! $this->sourceCompatible($source, $objective)) {
                $errors[] = $this->error($sheet, $number, 'source_key', 'source_scope_incompatible', "La fuente {$sourceKey} no cubre el grado, track, asignatura o tipo del objetivo.");
            }
            if (! $objective || ! $source || ! in_array($role, self::SOURCE_ROLES, true) || $locator === null) {
                continue;
            }
            $relationshipIdentity = $objectiveKey.'|'.$sourceKey.'|'.$role.'|'.$locator;
            if (isset($seen[$relationshipIdentity])) {
                $errors[] = $this->error($sheet, $number, 'source_key', 'duplicate_objective_source', "La misma relación objetivo-fuente ya aparece en la fila {$seen[$relationshipIdentity]}.");

                continue;
            }
            $seen[$relationshipIdentity] = $number;
            if ($objective && $role === 'canonical_text' && filled($objective['source_page'] ?? null) && $objective['source_page'] !== $locator) {
                $warnings[] = [
                    'code' => 'source_locator_differs_from_source_page',
                    'message' => "El localizador canónico {$locator} no coincide literalmente con source_page del objetivo {$objective['code']}.",
                    'row' => $number,
                ];
            }
            $relationships[] = [
                'row' => $number,
                'objective_key' => $objectiveKey,
                'source_key' => $sourceKey,
                'source_role' => $role,
                'source_locator' => $locator,
            ];
        }

        $canonicalCounts = collect($relationships)->where('source_role', 'canonical_text')->countBy('objective_key');
        foreach ($objectives as $objective) {
            $count = (int) ($canonicalCounts[$objective['objective_key']] ?? 0);
            if ($count !== 1) {
                $errors[] = $this->error('ObjetivoFuentes', null, 'source_role', 'canonical_source_count', "El objetivo {$objective['code']} ({$objective['grade_code']}) debe tener exactamente una fuente canonical_text; tiene {$count}.");
            }
        }

        return $relationships;
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $objective */
    private function sourceCompatible(array $source, array $objective): bool
    {
        $expectedScope = match (true) {
            in_array($objective['grade_code'], ['NT1', 'NT2'], true) => 'PARVULARIA_NT1_NT2',
            preg_match('/^[1-6]B$/', (string) $objective['grade_code']) === 1 => 'GENERAL_1B_6B',
            in_array($objective['grade_code'], ['7B', '8B', '1M', '2M'], true) => 'GENERAL_7B_2M',
            ($objective['curriculum_track'] ?? null) === 'TP' => 'TP_3M_4M',
            ($objective['curriculum_track'] ?? null) === 'ARTISTICA' => 'ARTISTICA_3M_4M',
            default => 'HC_3M_4M',
        };
        $expectedTrack = match ($expectedScope) {
            'PARVULARIA_NT1_NT2' => 'PARVULARIA',
            'GENERAL_1B_6B', 'GENERAL_7B_2M' => 'GENERAL',
            'TP_3M_4M' => 'TP',
            'ARTISTICA_3M_4M' => 'ARTISTICA',
            default => ($objective['curriculum_track'] ?? null) === 'GENERAL' ? 'GENERAL' : 'HC',
        };

        return $source['source_scope'] === $expectedScope
            && ($source['curriculum_track'] === null || $source['curriculum_track'] === $expectedTrack)
            && ($source['subject_code'] === null || $source['subject_code'] === $objective['subject_code'])
            && ($source['objective_type'] === null || $source['objective_type'] === $objective['objective_type']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $catalog
     * @param  Collection<string, ScheduleSubject>  $subjects
     * @param  list<array<string, mixed>>  $errors
     * @return array<string, mixed>|null
     */
    private function objective(array $row, array $catalog, $subjects, array &$errors): ?array
    {
        $sheet = (string) ($row['_sheet'] ?? 'Objetivos');
        $number = (int) ($row['_row'] ?? 0);
        $catalogCode = $this->code($row['catalog_code'] ?? null);
        $catalogVersion = $this->identifier($row['catalog_version'] ?? null);
        $code = $this->identifier($row['code'] ?? null);
        $type = $this->code($row['objective_type'] ?? null);
        $subjectCode = $this->code($row['subject_code'] ?? null);
        $level = $this->code($row['level_code'] ?? null);
        $grade = $this->code($row['grade_code'] ?? null);
        $track = $this->code($row['curriculum_track'] ?? null);
        $axis = $this->code($row['axis_code'] ?? null);
        $unit = $this->code($row['unit_code'] ?? null);
        $description = $this->text($row['description'] ?? null);
        $sourcePage = $this->text($row['source_page'] ?? null);
        $active = $this->boolean($row['active'] ?? null);
        $indicators = $this->json($row['indicators_json'] ?? null, $sheet, $number, $errors);

        foreach (['code' => $code, 'objective_type' => $type, 'level_code' => $level, 'grade_code' => $grade, 'description' => $description] as $field => $value) {
            $this->required($value, $sheet, $number, $field, $errors);
        }
        if ($catalogCode !== ($catalog['code'] ?? null) || $catalogVersion !== ($catalog['version'] ?? null)) {
            $errors[] = $this->error($sheet, $number, 'catalog_code', 'catalog_mismatch', 'El objetivo no coincide con el código y versión de la hoja Catalogo.');
        }
        if ($type !== null && ! in_array($type, self::OBJECTIVE_TYPES, true)) {
            $errors[] = $this->error($sheet, $number, 'objective_type', 'enum', 'objective_type debe ser OA, OAT, OAH, OAA, OAG u OAC.');
        }
        if ($level !== null && ! in_array($level, self::LEVEL_CODES, true)) {
            $errors[] = $this->error($sheet, $number, 'level_code', 'enum', 'level_code debe ser PARVULARIA, BASICA o MEDIA.');
        }
        if ($grade !== null && ! in_array($grade, self::GRADE_CODES, true)) {
            $errors[] = $this->error($sheet, $number, 'grade_code', 'enum', 'grade_code no corresponde a NT1–4M.');
        }
        if ($level && $grade && ! $this->gradeMatchesLevel($grade, $level)) {
            $errors[] = $this->error($sheet, $number, 'grade_code', 'level_grade_mismatch', "El grado {$grade} no corresponde al nivel {$level}.");
        }
        if ($track !== null && ! in_array($track, self::CURRICULUM_TRACKS, true)) {
            $errors[] = $this->error($sheet, $number, 'curriculum_track', 'enum', 'curriculum_track debe ser PARVULARIA, GENERAL, HC, TP o ARTISTICA.');
        }
        if ($track === 'PARVULARIA' && $level !== 'PARVULARIA') {
            $errors[] = $this->error($sheet, $number, 'curriculum_track', 'track_level_mismatch', 'El track PARVULARIA solo corresponde al nivel PARVULARIA.');
        }
        if ($grade !== null && ! $this->trackMatchesGrade($track, $grade)) {
            $errors[] = $this->error($sheet, $number, 'curriculum_track', 'track_grade_mismatch', 'curriculum_track no corresponde al grado; 3M–4M exige GENERAL, HC, TP o ARTISTICA.');
        }
        if ($code !== null && mb_strlen($code) > 100) {
            $errors[] = $this->error($sheet, $number, 'code', 'max', 'El código del objetivo supera 100 caracteres.');
        }
        if ($description !== null && mb_strlen($description) > 10_000) {
            $errors[] = $this->error($sheet, $number, 'description', 'max', 'La descripción supera 10.000 caracteres.');
        }
        if ($axis !== null && mb_strlen($axis) > 80) {
            $errors[] = $this->error($sheet, $number, 'axis_code', 'max', 'axis_code supera 80 caracteres.');
        }
        if ($unit !== null && mb_strlen($unit) > 80) {
            $errors[] = $this->error($sheet, $number, 'unit_code', 'max', 'unit_code supera 80 caracteres.');
        }
        if ($sourcePage !== null && mb_strlen($sourcePage) > 80) {
            $errors[] = $this->error($sheet, $number, 'source_page', 'max', 'source_page supera 80 caracteres.');
        }
        if ($active === null) {
            $errors[] = $this->error($sheet, $number, 'active', 'boolean', 'active debe indicar SI o NO.');
        }
        $subject = $subjectCode !== null ? $subjects->get($subjectCode) : null;
        if ($subjectCode !== null && ! $subject) {
            $errors[] = $this->error($sheet, $number, 'subject_code', 'subject_missing', "No existe una asignatura con código {$subjectCode}.");
        }
        if ($type === 'OA' && $subjectCode === null && $level !== 'PARVULARIA') {
            $errors[] = $this->error($sheet, $number, 'subject_code', 'subject_required', 'Los objetivos OA de Básica y Media deben indicar subject_code; en Parvularia el ámbito/núcleo puede expresarse mediante axis_code.');
        }

        $objectiveKey = CurriculumObjectiveIdentity::key([
            'code' => $code,
            'objective_type' => $type,
            'subject_code' => $subjectCode,
            'level_code' => $level,
            'grade_code' => $grade,
            'curriculum_track' => $track,
            'axis_code' => $axis,
        ]);

        return [
            'row' => $number,
            'code' => $code,
            'objective_key' => $objectiveKey,
            'objective_type' => $type,
            'schedule_subject_id' => $subject?->id,
            'subject_code' => $subjectCode,
            'level_code' => $level,
            'grade_code' => $grade,
            'curriculum_track' => $track,
            'axis_code' => $axis,
            'unit_code' => $unit,
            'description' => $description,
            'indicators' => $indicators,
            'active' => $active ?? false,
            'source_page' => $sourcePage,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $catalog
     * @param  Collection<string, ScheduleSubject>  $subjects
     * @param  list<array<string, mixed>>  $errors
     * @return array<string, mixed>|null
     */
    private function link(array $row, array $catalog, School $school, AcademicYear $year, $subjects, array &$errors): ?array
    {
        $sheet = (string) ($row['_sheet'] ?? 'Vinculos');
        $number = (int) ($row['_row'] ?? 0);
        $rbd = $this->text($row['school_rbd'] ?? null);
        $academicYear = $this->integer($row['academic_year'] ?? null);
        $subjectCode = $this->code($row['subject_code'] ?? null);
        $catalogCode = $this->code($row['catalog_code'] ?? null);
        $catalogVersion = $this->identifier($row['catalog_version'] ?? null);
        $level = $this->code($row['level_code'] ?? null);
        $grade = $this->code($row['grade_code'] ?? null);
        $track = $this->code($row['curriculum_track'] ?? null);
        $validFrom = $this->date($row['valid_from'] ?? null);
        $validTo = $this->date($row['valid_to'] ?? null);
        $active = $this->boolean($row['active'] ?? null);

        foreach (['school_rbd' => $rbd, 'academic_year' => $academicYear, 'subject_code' => $subjectCode, 'level_code' => $level, 'grade_code' => $grade] as $field => $value) {
            $this->required($value, $sheet, $number, $field, $errors);
        }
        if ($this->rbd($rbd) !== $this->rbd($school->rbd)) {
            $errors[] = $this->error($sheet, $number, 'school_rbd', 'school_mismatch', 'El RBD no coincide con el establecimiento seleccionado.');
        }
        if ($academicYear !== (int) $year->year) {
            $errors[] = $this->error($sheet, $number, 'academic_year', 'year_mismatch', 'El año no coincide con el contexto académico seleccionado.');
        }
        if ($catalogCode !== ($catalog['code'] ?? null) || $catalogVersion !== ($catalog['version'] ?? null)) {
            $errors[] = $this->error($sheet, $number, 'catalog_code', 'catalog_mismatch', 'El vínculo no coincide con el catálogo y versión declarados.');
        }
        $subject = $subjectCode !== null ? $subjects->get($subjectCode) : null;
        if (! $subject) {
            $errors[] = $this->error($sheet, $number, 'subject_code', 'subject_missing', "No existe una asignatura con código {$subjectCode}.");
        }
        if ($subject && ! $subject->active) {
            $errors[] = $this->error($sheet, $number, 'subject_code', 'subject_inactive', "La asignatura {$subjectCode} está inactiva.");
        }
        if ($level !== null && ! in_array($level, self::LEVEL_CODES, true)) {
            $errors[] = $this->error($sheet, $number, 'level_code', 'enum', 'level_code debe ser PARVULARIA, BASICA o MEDIA.');
        }
        if ($grade !== null && ! in_array($grade, self::GRADE_CODES, true)) {
            $errors[] = $this->error($sheet, $number, 'grade_code', 'enum', 'grade_code no corresponde a NT1–4M.');
        }
        if ($level && $grade && ! $this->gradeMatchesLevel($grade, $level)) {
            $errors[] = $this->error($sheet, $number, 'grade_code', 'level_grade_mismatch', "El grado {$grade} no corresponde al nivel {$level}.");
        }
        if ($track !== null && ! in_array($track, self::CURRICULUM_TRACKS, true)) {
            $errors[] = $this->error($sheet, $number, 'curriculum_track', 'enum', 'curriculum_track debe ser PARVULARIA, GENERAL, HC, TP o ARTISTICA.');
        }
        if ($track === 'PARVULARIA' && $level !== 'PARVULARIA') {
            $errors[] = $this->error($sheet, $number, 'curriculum_track', 'track_level_mismatch', 'El track PARVULARIA solo corresponde al nivel PARVULARIA.');
        }
        if ($grade !== null && ! $this->trackMatchesGrade($track, $grade)) {
            $errors[] = $this->error($sheet, $number, 'curriculum_track', 'track_grade_mismatch', 'curriculum_track no corresponde al grado; 3M–4M exige GENERAL, HC, TP o ARTISTICA.');
        }
        if (($row['valid_from'] ?? null) !== null && $this->text($row['valid_from']) !== null && $validFrom === null) {
            $errors[] = $this->error($sheet, $number, 'valid_from', 'date', 'valid_from debe usar formato YYYY-MM-DD.');
        }
        if (($row['valid_to'] ?? null) !== null && $this->text($row['valid_to']) !== null && $validTo === null) {
            $errors[] = $this->error($sheet, $number, 'valid_to', 'date', 'valid_to debe usar formato YYYY-MM-DD.');
        }
        if ($validFrom && $validTo && $validTo < $validFrom) {
            $errors[] = $this->error($sheet, $number, 'valid_to', 'date_order', 'valid_to no puede ser anterior a valid_from.');
        }
        if ($active === null) {
            $errors[] = $this->error($sheet, $number, 'active', 'boolean', 'active debe indicar SI o NO.');
        }

        return [
            'row' => $number,
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'schedule_subject_id' => $subject?->id,
            'subject_code' => $subjectCode,
            'level_code' => $level,
            'grade_code' => $grade,
            'curriculum_track' => $track,
            'scope_key' => ($level ?: 'ALL').':'.($grade ?: 'ALL').':'.($track ?: 'ALL'),
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'active' => $active ?? false,
        ];
    }

    /** @param list<array<string, mixed>> $objectives @param list<array<string, mixed>> $links @param list<array<string, mixed>> $errors */
    private function validateLinkedObjectives(array $objectives, array $links, array &$errors): void
    {
        $activeLinks = collect($links)->where('active', true);
        // Un catálogo oficial debe cubrir NT1–4M aunque el establecimiento no
        // imparta todos los grados. Por eso solo los vínculos declarados exigen
        // objetivos; no se obliga a vincular oferta inexistente de la escuela.
        foreach ($activeLinks as $link) {
            $hasObjective = collect($objectives)->contains(fn (array $objective): bool => $objective['active']
                && ((int) ($objective['schedule_subject_id'] ?? 0) === (int) $link['schedule_subject_id'] || $objective['schedule_subject_id'] === null)
                && $objective['level_code'] === $link['level_code']
                && $objective['grade_code'] === $link['grade_code']
                && ($objective['curriculum_track'] ?? null) === ($link['curriculum_track'] ?? null)
            );
            if (! $hasObjective) {
                $errors[] = $this->error(
                    'Vinculos',
                    (int) $link['row'],
                    'subject_code',
                    'link_without_objectives',
                    "El vínculo {$link['subject_code']} {$link['grade_code']} no tiene objetivos activos.",
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @param  list<array<string, mixed>>  $objectives
     * @param  list<array<string, mixed>>  $errors
     * @param  list<array<string, mixed>>  $warnings
     * @return array<string, mixed>
     */
    private function relevantCoverage(
        School $school,
        AcademicYear $year,
        array $links,
        array $objectives,
        array &$errors,
        array &$warnings,
    ): array {
        $activeLinks = collect($links)->where('active', true);
        $activeObjectives = collect($objectives)->where('active', true);
        $coverageMatrix = collect(self::GRADE_CODES)->mapWithKeys(function (string $grade) use ($activeObjectives): array {
            $gradeObjectives = $activeObjectives->where('grade_code', $grade);

            return [$grade => [
                'level_code' => $this->grades->levelForGrade($grade),
                'objective_count' => $gradeObjectives->count(),
                'oa_count' => $gradeObjectives->where('objective_type', 'OA')->count(),
                'oat_count' => $gradeObjectives->where('objective_type', 'OAT')->count(),
                'subject_count' => $gradeObjectives->pluck('subject_code')->filter()->unique()->count(),
                'tracks' => $gradeObjectives->pluck('curriculum_track')->filter()->unique()->values()->all(),
            ]];
        });
        $missingGradeCodes = $coverageMatrix
            ->filter(fn (array $entry): bool => $entry['objective_count'] < 1)
            ->keys()->values();
        foreach ($missingGradeCodes as $grade) {
            $errors[] = $this->error(
                'Objetivos',
                null,
                'grade_code',
                'catalog_grade_missing',
                "El catálogo debe incluir al menos un objetivo activo para {$grade}.",
            );
        }
        $courses = CourseSection::query()->with('educationLevel')
            ->where('academic_year_id', $year->id)
            ->where('active', true)
            ->get();
        $relevantGrades = $courses->map(fn (CourseSection $course): ?string => $this->grades->fromEducationLevel($course->educationLevel))
            ->filter()->unique()->values();
        foreach ($relevantGrades as $grade) {
            if (! $activeLinks->contains(fn (array $link): bool => $link['grade_code'] === $grade)) {
                $errors[] = $this->error('Vinculos', null, 'grade_code', 'relevant_grade_missing', "Falta cobertura curricular activa para el grado {$grade} presente en el año seleccionado.");
            }
        }

        $plans = StudyPlan::query()->with(['educationLevel', 'courseSection.educationLevel', 'subjects.scheduleSubject'])
            ->where('academic_year_id', $year->id)
            ->where('active', true)
            ->get();
        $requiredPairs = collect();
        $offeredPairs = collect();
        foreach ($plans as $plan) {
            $grade = $this->grades->fromEducationLevel($plan->courseSection?->educationLevel ?: $plan->educationLevel);
            if (! $grade) {
                continue;
            }
            foreach ($plan->subjects as $planSubject) {
                if (! $planSubject->scheduleSubject?->active) {
                    continue;
                }
                $pair = [
                    'schedule_subject_id' => (int) $planSubject->schedule_subject_id,
                    'subject_code' => $planSubject->scheduleSubject->code,
                    'grade_code' => $grade,
                ];
                $offeredPairs->push($pair);
                if ($planSubject->required) {
                    $requiredPairs->push($pair);
                }
            }
        }
        $offeredPairs = $offeredPairs->unique(fn (array $pair): string => $pair['schedule_subject_id'].'|'.$pair['grade_code'])->values();
        $requiredPairs = $requiredPairs->unique(fn (array $pair): string => $pair['schedule_subject_id'].'|'.$pair['grade_code'])->values();

        foreach ($activeLinks as $link) {
            $offered = $plans->isNotEmpty()
                ? $offeredPairs->contains(fn (array $pair): bool => $pair['schedule_subject_id'] === (int) $link['schedule_subject_id']
                    && $pair['grade_code'] === $link['grade_code'])
                : $relevantGrades->contains($link['grade_code']);
            if (! $offered) {
                $errors[] = $this->error(
                    'Vinculos',
                    (int) $link['row'],
                    'subject_code',
                    'link_outside_current_offering',
                    "El vínculo {$link['subject_code']} {$link['grade_code']} no corresponde a la oferta vigente del establecimiento.",
                );
            }
        }
        foreach ($requiredPairs as $pair) {
            if (! $activeLinks->contains(fn (array $link): bool => (int) $link['schedule_subject_id'] === $pair['schedule_subject_id']
                && $link['grade_code'] === $pair['grade_code']
            )) {
                $errors[] = $this->error(
                    'Vinculos',
                    null,
                    'subject_code',
                    'study_plan_scope_missing',
                    "Falta el vínculo requerido para {$pair['subject_code']} en {$pair['grade_code']}.",
                );
            }
        }
        if ($plans->isEmpty()) {
            $warnings[] = [
                'code' => 'study_plans_unavailable',
                'message' => 'No hay planes de estudio activos; la cobertura relevante se validó solo por los grados con cursos activos.',
            ];
        }

        return [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'relevant_grades' => $relevantGrades->all(),
            'required_subject_grade_pairs' => $requiredPairs->count(),
            'offered_subject_grade_pairs' => $offeredPairs->count(),
            'active_links' => $activeLinks->count(),
            'active_objectives' => $activeObjectives->count(),
            'required_grade_codes' => self::GRADE_CODES,
            'missing_grade_codes' => $missingGradeCodes->all(),
            'complete_nt1_4m' => $missingGradeCodes->isEmpty(),
            'matrix' => $coverageMatrix->all(),
        ];
    }

    private function gradeMatchesLevel(string $grade, string $level): bool
    {
        return match ($level) {
            'PARVULARIA' => in_array($grade, ['NT1', 'NT2'], true),
            'BASICA' => str_ends_with($grade, 'B'),
            'MEDIA' => str_ends_with($grade, 'M'),
            default => false,
        };
    }

    private function trackMatchesGrade(?string $track, string $grade): bool
    {
        if (in_array($grade, ['3M', '4M'], true)) {
            return in_array($track, ['GENERAL', 'HC', 'TP', 'ARTISTICA'], true);
        }
        if (in_array($grade, ['NT1', 'NT2'], true)) {
            return $track === null || $track === 'PARVULARIA';
        }

        return $track === null || $track === 'GENERAL';
    }

    /** @param list<array<string, mixed>> $errors */
    private function required(mixed $value, string $sheet, int $row, string $field, array &$errors): void
    {
        if ($value === null || $value === '') {
            $errors[] = $this->error($sheet, $row ?: null, $field, 'required', "El campo {$field} es obligatorio.");
        }
    }

    /** @param list<array<string, mixed>> $errors */
    private function json(mixed $value, string $sheet, int $row, array &$errors): array
    {
        $text = $this->text($value);
        if ($text === null) {
            return [];
        }
        try {
            $decoded = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $errors[] = $this->error($sheet, $row, 'indicators_json', 'json', 'indicators_json no contiene JSON válido.');

            return [];
        }
        if (! is_array($decoded) || count($decoded) > 100) {
            $errors[] = $this->error($sheet, $row, 'indicators_json', 'shape', 'indicators_json debe ser un arreglo u objeto JSON de hasta 100 elementos.');

            return [];
        }

        return $decoded;
    }

    private function boolean(mixed $value): ?bool
    {
        $key = $this->code($value);

        return match ($key) {
            'SI', 'S', 'YES', 'TRUE', '1', 'ACTIVO', 'ACTIVA' => true,
            'NO', 'N', 'FALSE', '0', 'INACTIVO', 'INACTIVA' => false,
            default => null,
        };
    }

    private function date(mixed $value): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $text, 'UTC');

            return $date && $date->format('Y-m-d') === $text ? $text : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function integer(mixed $value): ?int
    {
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        $text = $this->text($value);

        return $text !== null && preg_match('/^\d+$/', $text) ? (int) $text : null;
    }

    private function code(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : mb_strtoupper($text);
    }

    private function identifier(mixed $value): ?string
    {
        return $this->text($value);
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rbd(mixed $value): string
    {
        return preg_replace('/[^0-9K]/', '', mb_strtoupper((string) $value)) ?? '';
    }

    /** @return array<string, mixed> */
    private function error(string $sheet, ?int $row, string $field, string $code, string $message): array
    {
        return [
            'sheet' => $sheet,
            'row' => $row,
            'field' => $field,
            'code' => $code,
            'message' => $message,
        ];
    }
}
