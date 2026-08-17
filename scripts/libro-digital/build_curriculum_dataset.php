<?php

declare(strict_types=1);

/**
 * Consolida la extracción HTML oficial, los OA LCPOA del PDF y los OF/OFT
 * artísticos en un dataset listo para construir el XLSX del Libro Digital.
 *
 * No modifica la base de datos ni declara cumplimiento automático.
 */

if ($argc !== 11) {
    fwrite(STDERR, "Uso: php {$argv[0]} <corpus.json> <correcciones-html.json> <lcpoa.json> <artistica.json> <ds193-oah-oaa.json> <base-1b6b.html> <base-7b2m.html> <base-tp.html> <pdf-dir> <salida.json>\n");
    exit(64);
}

[$script, $corpusPath, $correctionsPath, $lcpoaPath, $artisticaPath, $ds193Path, $base16Path, $base72Path, $baseTpPath, $pdfDir, $outputPath] = $argv;
foreach ([$corpusPath, $correctionsPath, $lcpoaPath, $artisticaPath, $ds193Path, $base16Path, $base72Path, $baseTpPath] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "No existe el insumo: {$path}\n");
        exit(66);
    }
}
if (! is_dir($pdfDir)) {
    fwrite(STDERR, "No existe el directorio PDF: {$pdfDir}\n");
    exit(66);
}

$corpus = readJson($corpusPath);
$corrections = readJson($correctionsPath);
$lcpoa = readJson($lcpoaPath);
$artistica = readJson($artisticaPath);
$ds193 = readJson($ds193Path);
$retrievedAt = '2026-08-14T00:00:00-04:00';
$catalogCode = 'CN_OFICIAL_NT1_4M';
$catalogVersion = '2026-08-14';
$authority = 'Ministerio de Educación de Chile — Unidad de Currículum y Evaluación';

$masterSources = [];
foreach ((array) ($corpus['sources'] ?? []) as $source) {
    $key = (string) $source['source_key'];
    $masterSources[$key] = normalizeSource($source + [
        'document_number' => 'Ficha pública Currículum Nacional',
        'evidence_path' => dirname($corpusPath).'/evidencias-html/'.($source['evidence_filename'] ?? ($key.'.html')),
        'hash_scope' => 'official_html_snapshot',
    ]);
}

$baseSources = [
    'CN_HTML_OAT_1B_6B' => [
        'path' => $base16Path,
        'scope' => 'GENERAL_1B_6B',
        'name' => 'Objetivos de Aprendizaje Transversales — 1° a 6° básico',
        'url' => 'https://www.curriculumnacional.cl/curriculum/1o-6o-basico',
        'track' => 'GENERAL',
        'type' => 'OAT',
    ],
    'CN_HTML_OAT_7B_2M' => [
        'path' => $base72Path,
        'scope' => 'GENERAL_7B_2M',
        'name' => 'Objetivos de Aprendizaje Transversales — 7° básico a 2° medio',
        'url' => 'https://www.curriculumnacional.cl/curriculum/7o-basico-2o-medio',
        'track' => 'GENERAL',
        'type' => 'OAT',
    ],
    'CN_HTML_OAG_TP_3M_4M' => [
        'path' => $baseTpPath,
        'scope' => 'TP_3M_4M',
        'name' => 'Objetivos de Aprendizaje Genéricos — Educación Media Técnico-Profesional',
        'url' => 'https://www.curriculumnacional.cl/curriculum/3o-4o-medio-tecnico-profesional',
        'track' => 'TP',
        'type' => 'OAG',
    ],
];
foreach ($baseSources as $key => $source) {
    $masterSources[$key] = normalizeSource([
        'source_key' => $key,
        'source_scope' => $source['scope'],
        'source_name' => $source['name'],
        'authority' => $authority,
        'document_number' => 'Página base oficial de Currículum Nacional',
        'source_url' => $source['url'],
        'source_sha256' => hash_file('sha256', $source['path']),
        'effective_from' => '',
        'effective_to' => '',
        'curriculum_track' => $source['track'],
        'subject_code' => '',
        'objective_type' => $source['type'],
        'evidence_path' => realpath($source['path']) ?: $source['path'],
        'evidence_filename' => $key.'.html',
        'retrieved_at' => $retrievedAt,
        'hash_scope' => 'official_html_snapshot',
    ]);
}

$pdfDefinitions = [
    'CN_PDF_BCEP_DS481' => ['Bases_Parvularia_DS481.pdf', 'PARVULARIA_NT1_NT2', 'Bases Curriculares de Educación Parvularia', 'Decreto 481/2018', 'https://www.curriculumnacional.cl/614/articles-69957_bases.pdf', 'PARVULARIA'],
    'CN_PDF_BASES_1B_6B' => ['Bases_1B_6B_DS439_DS433.pdf', 'GENERAL_1B_6B', 'Bases Curriculares 1° a 6° básico', 'Decretos 439/2012 y 433/2012', 'https://www.curriculumnacional.cl/614/articles-22394_bases.pdf', 'GENERAL'],
    'CN_PDF_BASES_7B_2M' => ['Bases_7B_2M_DS614_DS369.pdf', 'GENERAL_7B_2M', 'Bases Curriculares 7° básico a 2° medio', 'Decretos 614/2013 y 369/2015', 'https://www.curriculumnacional.cl/614/articles-37136_bases.pdf', 'GENERAL'],
    'CN_PDF_BASES_3M_4M' => ['Bases_3M_4M_DS193.pdf', 'HC_3M_4M', 'Bases Curriculares 3° y 4° medio', 'Decreto 193/2019', 'https://www.curriculumnacional.cl/614/articles-91414_bases.pdf', ''],
    'CN_PDF_BASES_TP' => ['Bases_TP_DS452.pdf', 'TP_3M_4M', 'Bases Curriculares Formación Diferenciada Técnico-Profesional', 'Decreto 452/2013', 'https://www.curriculumnacional.cl/614/articles-70892_bases.pdf', 'TP'],
    'CN_PDF_BASES_LCPOA' => ['Bases_LCPOA_DS97.pdf', 'GENERAL_1B_6B', 'Bases Curriculares de LCPOA 1° a 6° básico', 'Decreto 97/2021', 'https://www.curriculumnacional.cl/614/articles-143635_bases.pdf', 'GENERAL'],
    'CN_PDF_LENGUA_INDIGENA' => ['Marco_Lengua_Indigena_DS280.pdf', 'GENERAL_7B_2M', 'Marco curricular Lengua Indígena', 'Decreto 280/2009', 'https://www.curriculumnacional.cl/sites/default/files/adjuntos/recursos/2025-03/DTO-280_2009%20Lengua%20Indigena.pdf', 'GENERAL'],
    'CN_PDF_ARTISTICA_DS3' => ['OF_Terminales_Artistica_DS3.pdf', 'ARTISTICA_1B_4M', 'Objetivos Fundamentales adicionales y terminales de Formación Artística', 'Decreto 3/2007; Decretos Exentos 2507/2007 y 2508/2007', 'https://www.curriculumnacional.cl/614/articles-332179_recurso_pdf.pdf', 'ARTISTICA'],
];
foreach ($pdfDefinitions as $key => [$filename, $scope, $name, $document, $url, $track]) {
    $path = rtrim($pdfDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
    if (! is_file($path)) {
        throw new RuntimeException("Falta el PDF oficial {$filename}");
    }
    $masterSources[$key] = normalizeSource([
        'source_key' => $key,
        'source_scope' => $scope,
        'source_name' => $name,
        'authority' => $authority,
        'document_number' => $document,
        'source_url' => $url,
        'source_sha256' => hash_file('sha256', $path),
        'effective_from' => '',
        'effective_to' => '',
        'curriculum_track' => $track,
        'subject_code' => '',
        'objective_type' => '',
        'evidence_path' => realpath($path) ?: $path,
        'evidence_filename' => $filename,
        'retrieved_at' => $retrievedAt,
        'hash_scope' => 'official_pdf_bytes',
    ]);
}

$masterRows = [];
$descriptionCorrections = [];
foreach ((array) ($corrections['corrections'] ?? []) as $correction) {
    $key = implode('|', [
        (string) ($correction['source_key'] ?? ''),
        (string) ($correction['code'] ?? ''),
        (string) (($correction['grade_codes'][0] ?? '')),
        (string) ($correction['curriculum_track'] ?? ''),
    ]);
    $descriptionCorrections[$key] = (string) ($correction['reconstructed_description'] ?? '');
}
foreach ((array) ($corpus['objectives'] ?? []) as $row) {
    $rawType = rawObjectiveType((string) $row['code'], (string) $row['objective_type']);
    $subjectName = canonicalSubjectName((string) $row['subject_name'], (string) $row['curriculum_track']);
    $subjectCode = canonicalSubjectCode($subjectName, (string) $row['curriculum_track'], (string) $row['level_code']);
    if ($subjectCode === 'LCPOA') {
        continue;
    }
    $status = (string) $row['normative_status'];
    $disposition = disposition($status, $rawType, (string) $row['curriculum_track'], false);
    $correctionKey = implode('|', [
        (string) $row['source_key'],
        (string) $row['code'],
        (string) $row['grade_code'],
        (string) $row['curriculum_track'],
    ]);
    $description = $descriptionCorrections[$correctionKey] ?? (string) $row['description'];
    $masterRows[] = masterRow([
        'official_code' => (string) $row['code'],
        'reference_code' => (string) $row['code'],
        'objective_type' => $rawType,
        'subject_code' => $subjectCode,
        'subject_name' => $subjectName,
        'level_code' => (string) $row['level_code'],
        'grade_codes' => [(string) $row['grade_code']],
        'official_grade_scope' => officialGradeScope((string) $row['grade_code'], (string) $row['curriculum_track'], (string) $row['code']),
        'technical_projection' => isProjection((string) $row['grade_code'], (string) $row['curriculum_track'], (string) $row['code']),
        'curriculum_track' => (string) $row['curriculum_track'],
        'axis_code' => slug((string) ($row['axis_name'] ?: $row['axis_code']), 80),
        'axis_name' => (string) $row['axis_name'],
        'sociolinguistic_context' => '',
        'description' => $description,
        'indicators' => (array) ($row['indicators'] ?? []),
        'source_locator' => mb_substr((string) $row['source_page'], 0, 80),
        'objective_url' => (string) $row['objective_url'],
        'subject_page_url' => (string) $row['subject_page_url'],
        'source_key' => (string) $row['source_key'],
        'source_sha256' => (string) $row['source_sha256'],
        'normative_status' => $status,
        'disposition' => $disposition,
        'import_included' => isImporterSupported($rawType, $status),
        'active_recommended' => $disposition === 'IMPORTABLE_ACTIVABLE',
        'retrieved_at' => (string) $row['retrieved_at'],
    ]);
}

foreach ([
    [$base16Path, 'CN_HTML_OAT_1B_6B', ['1B', '2B', '3B', '4B', '5B', '6B'], 'BASICA', 'GENERAL', 'OAT'],
    [$base72Path, 'CN_HTML_OAT_7B_2M', ['7B', '8B', '1M', '2M'], null, 'GENERAL', 'OAT'],
    [$baseTpPath, 'CN_HTML_OAG_TP_3M_4M', ['3M', '4M'], 'MEDIA', 'TP', 'OAG'],
] as [$path, $sourceKey, $grades, $fixedLevel, $track, $type]) {
    foreach (extractParagraphObjectives($path, $type) as $objective) {
        foreach ($grades as $grade) {
            $level = $fixedLevel ?: (str_ends_with($grade, 'B') ? 'BASICA' : 'MEDIA');
            $disposition = $track === 'TP' ? 'IMPORTABLE_BLOCKED' : 'IMPORTABLE_ACTIVABLE';
            $canonicalSourceKey = $sourceKey;
            $description = $objective['description'];
            if ($track === 'TP' && upperAscii($objective['code']) === 'OAG I') {
                $canonicalSourceKey = 'CN_PDF_BASES_TP';
                $description = 'Utilizar eficientemente los insumos para los procesos productivos y disponer cuidadosamente los desechos, en una perspectiva de eficiencia energética y cuidado ambiental.';
            }
            $masterRows[] = masterRow([
                'official_code' => $objective['code'],
                'reference_code' => $objective['code'],
                'objective_type' => $type,
                'subject_code' => '',
                'subject_name' => $type === 'OAT' ? 'Objetivos de Aprendizaje Transversales' : 'Objetivos de Aprendizaje Genéricos TP',
                'level_code' => $level,
                'grade_codes' => [$grade],
                'official_grade_scope' => implode('|', $grades),
                'technical_projection' => true,
                'curriculum_track' => $track,
                'axis_code' => $type === 'OAT' ? 'OAT_CICLO' : 'OAG_COMUN',
                'axis_name' => $type === 'OAT' ? 'Objetivos transversales del ciclo' : 'Competencias genéricas de egreso TP',
                'sociolinguistic_context' => '',
                'description' => $description,
                'indicators' => [],
                'source_locator' => $objective['code'],
                'objective_url' => $masterSources[$sourceKey]['source_url'],
                'subject_page_url' => $masterSources[$sourceKey]['source_url'],
                'source_key' => $canonicalSourceKey,
                'source_sha256' => $masterSources[$canonicalSourceKey]['source_sha256'],
                'normative_status' => 'PUBLICADO_FUENTE_OFICIAL',
                'disposition' => $disposition,
                'import_included' => true,
                'active_recommended' => $disposition === 'IMPORTABLE_ACTIVABLE',
                'retrieved_at' => $retrievedAt,
            ]);
        }
    }
}

foreach ((array) ($ds193['rows'] ?? []) as $row) {
    $type = (string) ($row['objective_type'] ?? '');
    $code = (string) ($row['official_code'] ?? '');
    $description = (string) ($row['description'] ?? '');
    $tracks = array_values((array) ($row['tracks'] ?? []));
    if (! in_array($type, ['OAH', 'OAA'], true) || $code === '' || $description === '' || $tracks === []) {
        throw new RuntimeException('Fila DS193 OAH/OAA incompleta o inválida.');
    }

    foreach ($tracks as $track) {
        $rawMappings = $type === 'OAA'
            ? (array) ($ds193['oaa_subject_track_mappings'] ?? [])
            : (array) ($row['subject_track_mappings'] ?? []);
        $subjectMappings = array_values(array_map(
            static fn (array $subject): array => [
                'code' => (string) ($subject['subject_code'] ?? ''),
                'name' => (string) ($subject['subject_name'] ?? ''),
            ],
            array_filter($rawMappings, static fn (array $subject): bool => (string) ($subject['track'] ?? '') === (string) $track),
        ));
        if ($subjectMappings === []) {
            throw new RuntimeException("No existe mapeo de asignaturas para {$code} ({$track}).");
        }
        foreach ($subjectMappings as $subject) {
            $locator = sprintf('PDF %d; impresa %d', (int) ($row['pdf_page'] ?? 0), (int) ($row['printed_page'] ?? 0));
            $hasCriticalDiscrepancy = $code === 'HI-HGCS-3y4-OAH-a';
            $masterRows[] = masterRow([
                'canonical_group_key' => (string) ($row['canonical_group_key'] ?? $code),
                'official_code' => $code,
                'reference_code' => $code,
                'objective_type' => $type,
                'subject_code' => $subject['code'],
                'subject_name' => $subject['name'],
                'level_code' => 'MEDIA',
                'grade_codes' => ['3M', '4M'],
                'official_grade_scope' => '3M|4M',
                'technical_projection' => true,
                'curriculum_track' => (string) $track,
                'axis_code' => slug((string) ($row['disciplinary_group'] ?? '').'_'.(string) ($row['axis'] ?? ''), 80),
                'axis_name' => (string) ($row['axis'] ?? $row['disciplinary_group'] ?? ''),
                'sociolinguistic_context' => '',
                'description' => $description,
                'indicators' => [],
                'source_locator' => $locator,
                'objective_url' => (string) ($row['official_code_evidence_url'] ?? $masterSources['CN_PDF_BASES_3M_4M']['source_url']),
                'subject_page_url' => (string) ($row['official_code_evidence_url'] ?? $masterSources['CN_PDF_BASES_3M_4M']['source_url']),
                'source_key' => 'CN_PDF_BASES_3M_4M',
                'source_sha256' => $masterSources['CN_PDF_BASES_3M_4M']['source_sha256'],
                'normative_status' => $hasCriticalDiscrepancy ? 'PUBLICADO_CON_DISCREPANCIA_PDF_PORTAL' : 'PUBLICADO_FUENTE_OFICIAL',
                'disposition' => $hasCriticalDiscrepancy ? 'IMPORTABLE_BLOCKED' : 'IMPORTABLE_ACTIVABLE',
                'import_included' => true,
                'active_recommended' => ! $hasCriticalDiscrepancy,
                'retrieved_at' => $retrievedAt,
            ]);
        }
    }
}

foreach ((array) ($lcpoa['rows'] ?? []) as $row) {
    $masterRows[] = masterRow([
        'official_code' => '',
        'reference_code' => (string) $row['reference_code'],
        'objective_type' => 'OA',
        'subject_code' => 'LCPOA',
        'subject_name' => (string) $row['subject_name'],
        'level_code' => 'BASICA',
        'grade_codes' => [(string) $row['grade_code']],
        'official_grade_scope' => (string) $row['grade_code'],
        'technical_projection' => false,
        'curriculum_track' => 'GENERAL',
        'axis_code' => (string) $row['axis_code'],
        'axis_name' => (string) $row['axis_name'],
        'sociolinguistic_context' => (string) $row['sociolinguistic_context'],
        'description' => (string) $row['description'],
        'indicators' => [],
        'source_locator' => (string) $row['source_locator'],
        'objective_url' => $masterSources['CN_PDF_BASES_LCPOA']['source_url'],
        'subject_page_url' => 'https://www.curriculumnacional.cl/curriculum/1o-6o-basico/lengua-cultura-pueblos-originarios-ancestrales',
        'source_key' => 'CN_PDF_BASES_LCPOA',
        'source_sha256' => $masterSources['CN_PDF_BASES_LCPOA']['source_sha256'],
        'normative_status' => 'PUBLICADO_FUENTE_OFICIAL_CODIGO_TECNICO',
        'disposition' => 'IMPORTABLE_BLOCKED',
        'import_included' => true,
        'active_recommended' => false,
        'retrieved_at' => $retrievedAt,
    ]);
}

foreach ((array) $artistica as $row) {
    $type = (string) $row['objective_type'];
    $area = (string) $row['area'];
    $mention = $row['mention'] === null ? 'Común' : (string) $row['mention'];
    $reference = sprintf(
        'REF-ART-%s-%s-%s-%02d',
        slug($area, 20),
        slug($mention, 20),
        slug((string) $row['official_grade_scope'], 16),
        (int) $row['number'],
    );
    $sourceLocator = sprintf('PDF %d; impresa %d', (int) $row['pdf_page'], (int) $row['printed_page']);
    $masterRows[] = masterRow([
        'official_code' => '',
        'reference_code' => $reference,
        'objective_type' => $type,
        'subject_code' => artisticSubjectCode($area, $mention),
        'subject_name' => $area.($mention !== 'Común' ? ' — '.$mention : ' — objetivos comunes'),
        'level_code' => str_contains(implode('|', (array) $row['grade_codes']), 'M') ? 'MEDIA' : 'BASICA',
        'grade_codes' => array_values((array) $row['grade_codes']),
        'official_grade_scope' => (string) $row['official_grade_scope'],
        'technical_projection' => count((array) $row['grade_codes']) > 1,
        'curriculum_track' => 'ARTISTICA',
        'axis_code' => slug($area.($mention !== 'Común' ? '_'.$mention : ''), 80),
        'axis_name' => $area.($mention !== 'Común' ? ' — '.$mention : ''),
        'sociolinguistic_context' => '',
        'description' => (string) $row['description'],
        'indicators' => [],
        'source_locator' => $sourceLocator,
        'objective_url' => $masterSources['CN_PDF_ARTISTICA_DS3']['source_url'],
        'subject_page_url' => 'https://www.curriculumnacional.cl/recursos/terminales-formacion-diferenciada-artistica-3-4-medio-0',
        'source_key' => 'CN_PDF_ARTISTICA_DS3',
        'source_sha256' => $masterSources['CN_PDF_ARTISTICA_DS3']['source_sha256'],
        'normative_status' => 'PUBLICADO_FUENTE_OFICIAL_CODIGO_TECNICO',
        'disposition' => 'MASTER_ONLY',
        'import_included' => false,
        'active_recommended' => false,
        'retrieved_at' => $retrievedAt,
    ]);
}

usort($masterRows, static fn (array $a, array $b): int => [gradeOrder($a['grade_codes'][0] ?? ''), $a['curriculum_track'], $a['subject_name'], $a['objective_type'], $a['reference_code']] <=> [gradeOrder($b['grade_codes'][0] ?? ''), $b['curriculum_track'], $b['subject_name'], $b['objective_type'], $b['reference_code']]);

$importObjectives = [];
foreach ($masterRows as $row) {
    if (! $row['import_included']) {
        continue;
    }
    foreach ($row['grade_codes'] as $grade) {
        $importObjectives[] = [
            'catalog_code' => $catalogCode,
            'catalog_version' => $catalogVersion,
            'code' => $row['official_code'] ?: $row['reference_code'],
            'objective_type' => $row['objective_type'],
            'subject_code' => $row['subject_code'],
            'level_code' => levelForGrade($grade),
            'grade_code' => $grade,
            'axis_code' => $row['axis_code'],
            'unit_code' => '',
            'description' => $row['description'],
            'indicators_json' => json_encode($row['indicators'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'active' => $row['active_recommended'] ? 'SI' : 'NO',
            'source_page' => mb_substr($row['source_locator'], 0, 80),
            'curriculum_track' => $row['curriculum_track'],
            '_source_key' => legalSourceFor($grade, $row['curriculum_track'], $row['subject_code']),
            '_disposition' => $row['disposition'],
        ];
    }
}

$usedSourceKeys = [];
$objectiveSources = [];
foreach ($importObjectives as $objective) {
    $canonical = $objective['_source_key'];
    $legal = legalSourceFor($objective['grade_code'], $objective['curriculum_track'], $objective['subject_code']);
    $usedSourceKeys[$canonical] = true;
    $usedSourceKeys[$legal] = true;
    foreach ([[$canonical, 'canonical_text', $objective['source_page']], [$legal, 'legal_basis', 'PDF oficial completo']] as [$sourceKey, $role, $locator]) {
        $objectiveSources[] = [
            'objective_code' => $objective['code'],
            'objective_type' => $objective['objective_type'],
            'subject_code' => $objective['subject_code'],
            'level_code' => $objective['level_code'],
            'grade_code' => $objective['grade_code'],
            'curriculum_track' => $objective['curriculum_track'],
            'axis_code' => $objective['axis_code'],
            'source_key' => $sourceKey,
            'source_role' => $role,
            'source_locator' => $locator,
        ];
    }
}
$importSources = array_values(array_filter($masterSources, static fn (array $source): bool => isset($usedSourceKeys[$source['source_key']])));
usort($importSources, static fn (array $a, array $b): int => [$a['source_scope'], $a['source_name'], $a['source_key']] <=> [$b['source_scope'], $b['source_name'], $b['source_key']]);

$subjects = subjectSummary($masterRows, (array) ($corpus['pages'] ?? []));
$coverage = coverageSummary($masterRows, $subjects);
$coverageDetail = coverageDetail($masterRows);
$exclusions = exclusionRows();
$links = [
    linkRow('6830', 2026, 'PM', $catalogCode, $catalogVersion, 'PARVULARIA', 'NT1', 'PARVULARIA'),
    linkRow('6830', 2026, 'PM', $catalogCode, $catalogVersion, 'PARVULARIA', 'NT2', 'PARVULARIA'),
    linkRow('6830', 2026, 'MAT', $catalogCode, $catalogVersion, 'BASICA', '1B', 'GENERAL'),
    linkRow('6830', 2026, 'MAT', $catalogCode, $catalogVersion, 'BASICA', '2B', 'GENERAL'),
];

foreach ($importObjectives as &$objective) {
    unset($objective['_source_key'], $objective['_disposition']);
}
unset($objective);

$stats = [
    'official_subject_pages' => (int) ($corpus['stats']['subject_pages_processed'] ?? 0),
    'master_rows' => count($masterRows),
    'import_rows' => count($importObjectives),
    'import_active_rows' => count(array_filter($importObjectives, static fn (array $row): bool => $row['active'] === 'SI')),
    'sources_master' => count($masterSources),
    'sources_import' => count($importSources),
    'objective_source_relations' => count($objectiveSources),
    'subjects' => count($subjects),
    'lcpoa_rows' => count((array) ($lcpoa['rows'] ?? [])),
    'artistica_rows' => count((array) $artistica),
    'ds193_common_canonical_rows' => count((array) ($ds193['rows'] ?? [])),
    'ds193_common_projected_import_rows' => array_sum(array_map(
        static fn (array $row): int => $row['source_key'] === 'CN_PDF_BASES_3M_4M' && in_array($row['objective_type'], ['OAH', 'OAA'], true)
            ? count($row['grade_codes'])
            : 0,
        $masterRows,
    )),
    'html_text_corrections_applied' => count($descriptionCorrections),
    'canonical_groups' => count(array_unique(array_column($masterRows, 'canonical_group_id'))),
    'coverage_subject_grade_rows' => count($coverageDetail),
    'by_disposition' => countBy($masterRows, 'disposition'),
    'by_type' => countBy($masterRows, 'objective_type'),
    'by_grade_projection' => countMasterGrades($masterRows),
];

$payload = [
    'schema' => 'cnsc-curriculum-national-workbook/v1',
    'generated_at' => gmdate('c'),
    'notice' => 'Corpus asistido obtenido desde páginas/PDF oficiales. Las filas bloqueadas o master-only no deben activarse como OA. Requiere revisión humana y reconciliación por base/acto.',
    'catalog' => [
        'catalog_code' => $catalogCode,
        'catalog_name' => 'Currículum Nacional Chile — catálogo NT1 a 4° medio',
        'version' => $catalogVersion,
        'authority' => $authority,
        'source_url' => 'https://www.curriculumnacional.cl/curriculum/cursos-y-niveles',
        'source_sha256' => '',
        'effective_from' => '',
        'effective_to' => '',
    ],
    'stats' => $stats,
    'master_objectives' => $masterRows,
    'import_objectives' => $importObjectives,
    'master_sources' => array_values($masterSources),
    'import_sources' => $importSources,
    'objective_sources' => $objectiveSources,
    'subjects' => $subjects,
    'coverage' => $coverage,
    'coverage_detail' => $coverageDetail,
    'exclusions' => $exclusions,
    'links' => $links,
    'references' => referenceRows($masterSources, $stats),
];

if (! is_dir(dirname($outputPath)) && ! mkdir(dirname($outputPath), 0775, true) && ! is_dir(dirname($outputPath))) {
    throw new RuntimeException('No se pudo crear el directorio de salida.');
}
file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n", LOCK_EX);
fwrite(STDOUT, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

function readJson(string $path): array
{
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function normalizeSource(array $source): array
{
    $sourceKey = (string) ($source['source_key'] ?? '');
    $evidencePath = (string) ($source['evidence_path'] ?? '');
    $extension = strtolower(pathinfo($evidencePath, PATHINFO_EXTENSION));
    return [
        'source_key' => $sourceKey,
        'source_scope' => (string) ($source['source_scope'] ?? ''),
        'source_name' => clean((string) ($source['source_name'] ?? '')),
        'authority' => clean((string) ($source['authority'] ?? '')),
        'document_number' => clean((string) ($source['document_number'] ?? 'Ficha pública Currículum Nacional')),
        'source_url' => (string) ($source['source_url'] ?? ''),
        'source_sha256' => mb_strtolower((string) ($source['source_sha256'] ?? '')),
        'effective_from' => (string) ($source['effective_from'] ?? ''),
        'effective_to' => (string) ($source['effective_to'] ?? ''),
        'curriculum_track' => (string) ($source['curriculum_track'] ?? ''),
        'subject_code' => (string) ($source['subject_code'] ?? ''),
        'objective_type' => (string) ($source['objective_type'] ?? ''),
        'evidence_path' => $evidencePath,
        'evidence_filename' => (string) ($source['evidence_filename'] ?? basename($evidencePath)),
        'package_filename' => $sourceKey.($extension !== '' ? '.'.$extension : ''),
        'retrieved_at' => (string) ($source['retrieved_at'] ?? ''),
        'hash_scope' => (string) ($source['hash_scope'] ?? 'official_bytes'),
    ];
}

function masterRow(array $row): array
{
    $canonicalIdentity = (string) ($row['canonical_group_key'] ?? implode('|', [
        $row['official_code'] ?: $row['reference_code'], $row['objective_type'], $row['subject_code'],
        $row['curriculum_track'], $row['axis_code'], hash('sha256', $row['description']),
    ]));
    unset($row['canonical_group_key']);
    $identity = implode('|', [
        $row['reference_code'], $row['objective_type'], $row['subject_code'],
        implode(',', $row['grade_codes']), $row['curriculum_track'], $row['axis_code'],
        hash('sha256', $row['description']),
    ]);
    return [
        'record_id' => 'REC_'.strtoupper(substr(hash('sha256', $identity), 0, 20)),
        'canonical_group_id' => 'CAN_'.strtoupper(substr(hash('sha256', $canonicalIdentity), 0, 20)),
    ] + $row;
}

/** @return list<array{code:string,description:string}> */
function extractParagraphObjectives(string $path, string $type): array
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        if (! $document->loadHTMLFile($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)) {
            throw new RuntimeException("HTML inválido: {$path}");
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    $rows = [];
    $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " paragraph--type--oat ")]');
    foreach ($nodes ?: [] as $node) {
        $code = clean((string) ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " field--name-field-oat-numero ")]', $node)->item(0)?->textContent ?? ''));
        if ($code === '') {
            $code = clean((string) ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " field--name-field-titulo ")]//h4', $node)->item(0)?->textContent ?? ''));
        }
        $description = clean((string) ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " field--name-field-descripcion ")]', $node)->item(0)?->textContent ?? ''));
        if ($code !== '' && $description !== '' && str_starts_with(upperAscii($code), $type)) {
            $rows[] = ['code' => $code, 'description' => $description];
        }
    }
    return $rows;
}

function rawObjectiveType(string $code, string $fallback): string
{
    $value = upperAscii($code.' '.$fallback);
    if (preg_match('/\bOFT\b/', $value)) {
        return 'OFT';
    }
    if (preg_match('/(^|\s)OF(?:\s|$)/', $value)) {
        return 'OF';
    }
    foreach (['OAT', 'OAH', 'OAA', 'OAG', 'OAC'] as $type) {
        if (preg_match('/\b'.$type.'\b/', $value)) {
            return $type;
        }
    }
    return 'OA';
}

function canonicalSubjectName(string $name, string $track): string
{
    $name = clean($name);
    if ($track === 'GENERAL') {
        $name = preg_replace('/\s+[34](?:º|°|o)?\s+medio$/iu', '', $name) ?: $name;
    }
    return $name;
}

function canonicalSubjectCode(string $name, string $track, string $level): string
{
    $known = [
        'ARTES VISUALES' => 'ART', 'CIENCIAS NATURALES' => 'CNA',
        'EDUCACION FISICA Y SALUD' => 'EFS', 'HISTORIA GEOGRAFIA Y CIENCIAS SOCIALES' => 'HGCS',
        'INGLES' => 'ING', 'INGLES PROPUESTA' => 'ING_PROP', 'LENGUAJE Y COMUNICACION' => 'LEN',
        'LENGUA Y LITERATURA' => 'LYL', 'LENGUA INDIGENA' => 'LIND',
        'LENGUA Y CULTURA DE LOS PUEBLOS ORIGINARIOS ANCESTRALES' => 'LCPOA',
        'MATEMATICA' => 'MAT', 'MUSICA' => 'MUS', 'ORIENTACION' => 'ORI', 'RELIGION' => 'REL',
        'TECNOLOGIA' => 'TEC', 'PENSAMIENTO MATEMATICO' => 'PM',
        'COMPRENSION DEL ENTORNO SOCIOCULTURAL' => 'CES', 'CONVIVENCIA Y CIUDADANIA' => 'CC',
        'CORPORALIDAD Y MOVIMIENTO' => 'CM', 'EXPLORACION DEL ENTORNO NATURAL' => 'EEN',
        'IDENTIDAD Y AUTONOMIA' => 'IA', 'LENGUAJES ARTISTICOS' => 'LA', 'LENGUAJE VERBAL' => 'LV',
        'EDUCACION CIUDADANA' => 'EDC', 'FILOSOFIA' => 'FIL', 'DANZA' => 'DAN', 'TEATRO' => 'TEA',
        'EDUCACION FISICA Y SALUD 1' => 'EFS1', 'EDUCACION FISICA Y SALUD 2' => 'EFS2',
    ];
    $key = upperAscii($name);
    if (isset($known[$key])) {
        return $known[$key];
    }
    $prefix = match ($track) {
        'HC' => 'HC_', 'TP' => 'TP_', 'PARVULARIA' => 'PAR_', default => 'GEN_',
    };
    $slug = slug($name, 41);
    $candidate = $prefix.$slug;
    if (strlen($candidate) <= 48) {
        return $candidate;
    }
    return substr($candidate, 0, 38).'_'.strtoupper(substr(hash('sha256', $track.'|'.$name), 0, 8));
}

function artisticSubjectCode(string $area, string $mention): string
{
    return 'FDA_'.slug($area.'_'.$mention, 43);
}

function disposition(string $status, string $type, string $track, bool $generatedCode): string
{
    if ($status === 'PROPUESTA_NO_BASE') {
        return 'MASTER_REFERENCE_ONLY';
    }
    if (in_array($type, ['OF', 'OFT'], true)) {
        return 'MASTER_ONLY';
    }
    if ($generatedCode || $track === 'TP') {
        return 'IMPORTABLE_BLOCKED';
    }
    return 'IMPORTABLE_ACTIVABLE';
}

function isImporterSupported(string $type, string $status): bool
{
    return $status !== 'PROPUESTA_NO_BASE' && in_array($type, ['OA', 'OAT', 'OAH', 'OAA', 'OAG', 'OAC'], true);
}

function officialGradeScope(string $grade, string $track, string $code): string
{
    if (in_array($grade, ['NT1', 'NT2'], true)) {
        return 'NT (proyección técnica NT1|NT2)';
    }
    if (in_array($grade, ['3M', '4M'], true) && ($track !== 'GENERAL' || str_contains(mb_strtolower($code), '3y4'))) {
        return '3M|4M';
    }
    return $grade;
}

function isProjection(string $grade, string $track, string $code): bool
{
    return in_array($grade, ['NT1', 'NT2'], true)
        || (in_array($grade, ['3M', '4M'], true) && ($track !== 'GENERAL' || str_contains(mb_strtolower($code), '3y4')));
}

function legalSourceFor(string $grade, string $track, string $subjectCode): string
{
    return match (true) {
        in_array($grade, ['NT1', 'NT2'], true) => 'CN_PDF_BCEP_DS481',
        $subjectCode === 'LCPOA' => 'CN_PDF_BASES_LCPOA',
        preg_match('/^[1-6]B$/', $grade) === 1 => 'CN_PDF_BASES_1B_6B',
        in_array($grade, ['7B', '8B', '1M', '2M'], true) => 'CN_PDF_BASES_7B_2M',
        $track === 'TP' => 'CN_PDF_BASES_TP',
        default => 'CN_PDF_BASES_3M_4M',
    };
}

function linkRow(string $rbd, int $year, string $subject, string $catalog, string $version, string $level, string $grade, string $track): array
{
    return [
        'school_rbd' => $rbd, 'academic_year' => $year, 'subject_code' => $subject,
        'catalog_code' => $catalog, 'catalog_version' => $version, 'level_code' => $level,
        'grade_code' => $grade, 'valid_from' => $year.'-01-01', 'valid_to' => $year.'-12-31',
        'active' => 'SI', 'curriculum_track' => $track,
    ];
}

function subjectSummary(array $rows, array $pages): array
{
    $subjects = [];
    foreach ($rows as $row) {
        if ($row['subject_code'] === '') {
            continue;
        }
        $key = $row['subject_code'].'|'.$row['curriculum_track'];
        $subjects[$key] ??= [
            'subject_code' => $row['subject_code'], 'subject_name' => $row['subject_name'],
            'curriculum_track' => $row['curriculum_track'], 'level_codes' => [], 'grade_codes' => [],
            'objective_rows' => 0, 'active_recommended_rows' => 0, 'dispositions' => [],
            'source_url' => $row['subject_page_url'],
        ];
        $subjects[$key]['level_codes'][] = $row['level_code'];
        $subjects[$key]['grade_codes'] = array_merge($subjects[$key]['grade_codes'], $row['grade_codes']);
        $subjects[$key]['objective_rows']++;
        $subjects[$key]['active_recommended_rows'] += $row['active_recommended'] ? 1 : 0;
        $subjects[$key]['dispositions'][] = $row['disposition'];
    }
    foreach ($pages as $page) {
        if (($page['status'] ?? '') !== 'NO_OBJECTIVES') {
            continue;
        }
        $title = preg_replace('/\s+(?:NT|[1-8]° Básico|[1-4]° Medio(?: FG)?)\s*\|.*$/u', '', (string) ($page['title'] ?? '')) ?: (string) $page['title'];
        $name = clean($title);
        $code = str_contains(upperAscii($name), 'RELIGION') ? 'REL' : (str_contains(upperAscii($name), 'LENGUA Y CULTURA') ? 'LCPOA' : canonicalSubjectCode($name, (string) $page['track'], ''));
        $key = $code.'|'.(string) $page['track'];
        $subjects[$key] ??= [
            'subject_code' => $code, 'subject_name' => $name, 'curriculum_track' => (string) $page['track'],
            'level_codes' => [], 'grade_codes' => [], 'objective_rows' => 0,
            'active_recommended_rows' => 0, 'dispositions' => ['MASTER_ONLY'], 'source_url' => (string) $page['url'],
        ];
        $subjects[$key]['grade_codes'] = array_merge($subjects[$key]['grade_codes'], (array) $page['grade_codes']);
    }
    foreach ($subjects as &$subject) {
        $subject['level_codes'] = implode('|', array_values(array_unique($subject['level_codes'])));
        usort($subject['grade_codes'], static fn (string $a, string $b): int => gradeOrder($a) <=> gradeOrder($b));
        $subject['grade_codes'] = implode('|', array_values(array_unique($subject['grade_codes'])));
        $subject['dispositions'] = implode('|', array_values(array_unique($subject['dispositions'])));
        $subject['status'] = $subject['active_recommended_rows'] > 0 ? 'CON_OBJETIVOS_ACTIVABLES' : 'REQUIERE_GOBERNANZA';
    }
    unset($subject);
    $subjects = array_values($subjects);
    usort($subjects, static fn (array $a, array $b): int => [$a['curriculum_track'], $a['subject_name'], $a['subject_code']] <=> [$b['curriculum_track'], $b['subject_name'], $b['subject_code']]);
    return $subjects;
}

function coverageSummary(array $rows, array $subjects): array
{
    $coverage = [];
    foreach (['NT1','NT2','1B','2B','3B','4B','5B','6B','7B','8B','1M','2M','3M','4M'] as $grade) {
        foreach (['PARVULARIA','GENERAL','HC','TP','ARTISTICA'] as $track) {
            $gradeRows = array_values(array_filter($rows, static fn (array $row): bool => in_array($grade, $row['grade_codes'], true) && $row['curriculum_track'] === $track));
            if ($gradeRows === []) {
                continue;
            }
            $types = countBy($gradeRows, 'objective_type');
            $coverage[] = [
                'coverage_key' => $track.'.'.$grade,
                'level_code' => levelForGrade($grade), 'grade_code' => $grade, 'curriculum_track' => $track,
                'subject_count' => count(array_unique(array_filter(array_column($gradeRows, 'subject_code')))),
                'master_rows' => count($gradeRows),
                'import_included_rows' => count(array_filter($gradeRows, static fn (array $row): bool => $row['import_included'])),
                'active_recommended_rows' => count(array_filter($gradeRows, static fn (array $row): bool => $row['active_recommended'])),
                'oa' => $types['OA'] ?? 0, 'oat' => $types['OAT'] ?? 0, 'oah' => $types['OAH'] ?? 0,
                'oaa' => $types['OAA'] ?? 0, 'oag' => $types['OAG'] ?? 0, 'oac' => $types['OAC'] ?? 0,
                'of' => $types['OF'] ?? 0, 'oft' => $types['OFT'] ?? 0,
                'completion_rule' => 'AGREGADO_DESDE_CORPUS_PROYECTADO',
                'status' => count(array_filter($gradeRows, static fn (array $row): bool => $row['active_recommended'])) > 0 ? 'CON_FILAS_ACTIVABLES' : 'SOLO_MAESTRO/BLOQUEADO',
            ];
        }
    }
    return $coverage;
}

function coverageDetail(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        foreach ($row['grade_codes'] as $grade) {
            $key = implode('|', [$grade, $row['curriculum_track'], $row['subject_code'], $row['subject_name']]);
            $groups[$key] ??= [
                'coverage_key' => $key,
                'level_code' => levelForGrade($grade),
                'grade_code' => $grade,
                'curriculum_track' => $row['curriculum_track'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'master_rows' => 0,
                'canonical_groups' => [],
                'import_included_rows' => 0,
                'active_recommended_rows' => 0,
                'objective_types' => [],
                'dispositions' => [],
                'source_keys' => [],
                'source_url' => $row['subject_page_url'],
            ];
            $groups[$key]['master_rows']++;
            $groups[$key]['canonical_groups'][] = $row['canonical_group_id'];
            $groups[$key]['import_included_rows'] += $row['import_included'] ? 1 : 0;
            $groups[$key]['active_recommended_rows'] += $row['active_recommended'] ? 1 : 0;
            $groups[$key]['objective_types'][] = $row['objective_type'];
            $groups[$key]['dispositions'][] = $row['disposition'];
            $groups[$key]['source_keys'][] = $row['source_key'];
        }
    }
    foreach ($groups as &$group) {
        $group['canonical_groups'] = count(array_unique($group['canonical_groups']));
        $group['objective_types'] = implode('|', array_values(array_unique($group['objective_types'])));
        $group['dispositions'] = implode('|', array_values(array_unique($group['dispositions'])));
        $group['source_count'] = count(array_unique($group['source_keys']));
        unset($group['source_keys']);
        $group['status'] = match (true) {
            $group['active_recommended_rows'] > 0 && str_contains($group['dispositions'], 'BLOCKED') => 'ACTIVABLE_CON_FILAS_BLOQUEADAS',
            $group['active_recommended_rows'] > 0 => 'ACTIVABLE',
            default => 'SOLO_MAESTRO/BLOQUEADO',
        };
    }
    unset($group);
    $groups = array_values($groups);
    usort($groups, static fn (array $a, array $b): int => [gradeOrder($a['grade_code']), $a['curriculum_track'], $a['subject_name']] <=> [gradeOrder($b['grade_code']), $b['curriculum_track'], $b['subject_name']]);
    return $groups;
}

function exclusionRows(): array
{
    return [
        ['exclusion_key'=>'EXC.RELIGION_GENERIC_NO_CREDO_ACT','scope'=>'1B–4M','disposition'=>'EXCLUDE','reason_code'=>'NO_UNIVERSAL_CORPUS','reason'=>'Religión no tiene un único corpus nacional: exige credo, autoridad religiosa, acto de aprobación y versión exactos.','activation_condition'=>'Adjuntar programa oficial específico y acto aprobatorio.','official_source_url'=>'https://www.bcn.cl/leychile/navegar?idNorma=16238'],
        ['exclusion_key'=>'EXC.INGLES_PROPUESTA','scope'=>'NT1–2M','disposition'=>'MASTER_REFERENCE_ONLY','reason_code'=>'PROPOSAL_NOT_BASE','reason'=>'Las fichas rotuladas Inglés (Propuesta) no se activan como Base vigente.','activation_condition'=>'Solo acto normativo posterior expreso.','official_source_url'=>'https://www.curriculumnacional.cl/curriculum/cursos-y-niveles'],
        ['exclusion_key'=>'EXC.LENGUA_INDIGENA_OF_CMO','scope'=>'7B–8B','disposition'=>'MASTER_ONLY','reason_code'=>'OF_CMO_UNSUPPORTED','reason'=>'El corpus oficial usa OF/CMO y el importador vigente acepta OA/OAT/OAH/OAA/OAG/OAC.','activation_condition'=>'Implementar taxonomía OF/CMO nativa.','official_source_url'=>'https://www.curriculumnacional.cl/curriculum/7o-basico-2o-medio/lengua-indigena'],
        ['exclusion_key'=>'EXC.LCPOA_CONTEXT','scope'=>'1B–6B','disposition'=>'IMPORTABLE_BLOCKED','reason_code'=>'LCPOA_CONTEXT_NOT_SELECTED','reason'=>'La Base contiene tres líneas sociolingüísticas y no imprime códigos oficiales por objetivo.','activation_condition'=>'Seleccionar exactamente un contexto y aprobar códigos internos auditados.','official_source_url'=>'https://www.curriculumnacional.cl/614/articles-143635_bases.pdf'],
        ['exclusion_key'=>'EXC.TP_DIMENSIONS','scope'=>'3M–4M TP','disposition'=>'IMPORTABLE_BLOCKED','reason_code'=>'TP_TERMINAL_DIMENSIONS_UNSUPPORTED','reason'=>'El modelo no representa formalmente sector, especialidad y mención; las filas TP quedan inactivas.','activation_condition'=>'Implementar y validar jerarquía TP.','official_source_url'=>'https://www.curriculumnacional.cl/614/articles-70892_bases.pdf'],
        ['exclusion_key'=>'EXC.ARTISTICA_OF_OFT','scope'=>'1B–4M Artística','disposition'=>'MASTER_ONLY','reason_code'=>'OF_OFT_UNSUPPORTED','reason'=>'La formación artística adicional/terminal usa OF y OFT, no OA; se conserva completa solo en el maestro.','activation_condition'=>'Implementar OF/OFT y jerarquía área/subárea/mención.','official_source_url'=>'https://www.curriculumnacional.cl/614/articles-332179_recurso_pdf.pdf'],
        ['exclusion_key'=>'EXC.CYCLE_PROJECTION','scope'=>'NT y 3M–4M','disposition'=>'TECHNICAL_PROJECTION','reason_code'=>'SHARED_OFFICIAL_SCOPE','reason'=>'NT y numerosos objetivos 3y4 son alcances compartidos; la hoja técnica los proyecta por grado sin renumerarlos.','activation_condition'=>'Conservar código/texto y marcar alcance oficial compartido.','official_source_url'=>'https://www.curriculumnacional.cl/curriculum/cursos-y-niveles'],
        ['exclusion_key'=>'EXC.API_UNDOCUMENTED','scope'=>'Todos','disposition'=>'EXCLUDE_AS_COMPLETE_SOURCE','reason_code'=>'SOURCE_COMPLETENESS_UNPROVEN','reason'=>'El JSON:API anónimo oculta texto, grado, eje y relaciones de OA; no garantiza corpus completo.','activation_condition'=>'Export oficial o permisos documentados, con reconciliación contra PDF/HTML.','official_source_url'=>'https://www.curriculumnacional.cl/jsonapi'],
        ['exclusion_key'=>'EXC.UNITS_UNAVAILABLE','scope'=>'Todos','disposition'=>'INFORMATION_GAP','reason_code'=>'OFFICIAL_UNIT_RELATION_NOT_PUBLIC','reason'=>'El portal de OA y su JSON:API público no exponen una relación oficial de unidad.','activation_condition'=>'Fuente oficial versionada que vincule unidades y objetivos.','official_source_url'=>'https://www.curriculumnacional.cl/jsonapi'],
        ['exclusion_key'=>'EXC.PRIORIZACION_HISTORICA','scope'=>'2023–2025','disposition'=>'HISTORICAL_ONLY','reason_code'=>'PRIORITIZATION_ENDED','reason'=>'La priorización curricular 2023 terminó en diciembre de 2025; el catálogo 2026 usa las Bases Curriculares sin reducirse a objetivos priorizados.','activation_condition'=>'No activar recursos de priorización como universo curricular 2026.','official_source_url'=>'https://www.curriculumnacional.cl/sites/default/files/adjuntos/recursos/2026-04/Cartilla_N1.pdf'],
        ['exclusion_key'=>'EXC.DS193_HGCS_A_DISCREPANCY','scope'=>'3M–4M HC · HI-HGCS-3y4-OAH-a','disposition'=>'IMPORTABLE_BLOCKED','reason_code'=>'OFFICIAL_SOURCE_TEXT_CONFLICT','reason'=>'La Base DS193 impresa usa «mentalidad», mientras la ficha del portal oficial usa «realidad». Se conserva la lectura del PDF, pero no se activa automáticamente.','activation_condition'=>'Resolución documental UCE/MINEDUC y revisión humana de la versión aplicable.','official_source_url'=>'https://www.curriculumnacional.cl/portal/Diferenciado-Humanista-Cientifico/Historia-geografia-y-ciencias-sociales/Comprension-historica-del-presente/'],
    ];
}

function referenceRows(array $sources, array $stats): array
{
    $rows = [
        ['category'=>'GENERACION','key'=>'fecha_consulta','value'=>'2026-08-14','source_url'=>'https://www.curriculumnacional.cl/','sha256'=>'','notes'=>'Extracción asistida reproducible; no certificación MINEDUC.'],
        ['category'=>'GENERACION','key'=>'master_rows','value'=>(string) $stats['master_rows'],'source_url'=>'','sha256'=>'','notes'=>'Incluye proyecciones técnicas y taxonomías master-only.'],
        ['category'=>'GENERACION','key'=>'import_rows','value'=>(string) $stats['import_rows'],'source_url'=>'','sha256'=>'','notes'=>'Filas con tipos admitidos; las bloqueadas están activas=NO.'],
    ];
    foreach ($sources as $source) {
        $rows[] = [
            'category'=>'FUENTE','key'=>$source['source_key'],'value'=>$source['source_name'],
            'source_url'=>$source['source_url'],'sha256'=>$source['source_sha256'],
            'notes'=>$source['document_number'].'; '.$source['hash_scope'],
        ];
    }
    return $rows;
}

function countBy(array $rows, string $field): array
{
    $counts = [];
    foreach ($rows as $row) {
        $key = (string) ($row[$field] ?? '');
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);
    return $counts;
}

function countMasterGrades(array $rows): array
{
    $counts = [];
    foreach ($rows as $row) {
        foreach ($row['grade_codes'] as $grade) {
            $counts[$grade] = ($counts[$grade] ?? 0) + 1;
        }
    }
    uksort($counts, static fn (string $a, string $b): int => gradeOrder($a) <=> gradeOrder($b));
    return $counts;
}

function levelForGrade(string $grade): string
{
    return match (true) {
        in_array($grade, ['NT1','NT2'], true) => 'PARVULARIA',
        str_ends_with($grade, 'B') => 'BASICA',
        default => 'MEDIA',
    };
}

function gradeOrder(string $grade): int
{
    static $order = ['NT1','NT2','1B','2B','3B','4B','5B','6B','7B','8B','1M','2M','3M','4M'];
    $index = array_search($grade, $order, true);
    return $index === false ? 999 : $index;
}

function clean(string $value): string
{
    return trim(preg_replace('/[\x{00A0}\s]+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $value);
}

function upperAscii(string $value): string
{
    $value = mb_strtoupper(clean($value), 'UTF-8');
    return strtr($value, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','º'=>'','°'=>'']);
}

function slug(string $value, int $max): string
{
    $value = preg_replace('/[^A-Z0-9]+/', '_', upperAscii($value)) ?: 'SIN_CODIGO';
    $value = trim($value, '_');
    if (strlen($value) <= $max) {
        return $value;
    }
    return substr($value, 0, max(1, $max - 9)).'_'.strtoupper(substr(hash('sha256', $value), 0, 8));
}

function normalizeCode(string $value, int $max): string
{
    return $value === '' ? '' : slug($value, $max);
}
