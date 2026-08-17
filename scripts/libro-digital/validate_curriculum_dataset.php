<?php

declare(strict_types=1);

if ($argc !== 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Uso: php {$argv[0]} <dataset.json>\n");
    exit(64);
}

$data = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (! $condition) {
        $errors[] = $message;
    }
};

$master = (array) ($data['master_objectives'] ?? []);
$objectives = (array) ($data['import_objectives'] ?? []);
$sources = (array) ($data['import_sources'] ?? []);
$relations = (array) ($data['objective_sources'] ?? []);
$stats = (array) ($data['stats'] ?? []);

$assert(count($master) === (int) ($stats['master_rows'] ?? -1), 'Conteo maestro inconsistente.');
$assert(count($objectives) === (int) ($stats['import_rows'] ?? -1), 'Conteo importable inconsistente.');
$assert(count($sources) === (int) ($stats['sources_import'] ?? -1), 'Conteo de fuentes inconsistente.');
$assert(count($relations) === (int) ($stats['objective_source_relations'] ?? -1), 'Conteo de relaciones inconsistente.');

$identities = [];
foreach ($objectives as $row) {
    $identity = implode('|', [
        $row['code'], $row['objective_type'], $row['subject_code'], $row['level_code'],
        $row['grade_code'], $row['curriculum_track'], $row['axis_code'],
    ]);
    $assert(! isset($identities[$identity]), "Identidad importable duplicada: {$identity}");
    $identities[$identity] = $row;
    $assert(in_array($row['objective_type'], ['OA','OAT','OAH','OAA','OAG','OAC'], true), "Tipo no admitido: {$identity}");
    if ($row['curriculum_track'] === 'TP' || $row['subject_code'] === 'LCPOA') {
        $assert($row['active'] === 'NO', "Fila sensible activa indebidamente: {$identity}");
    }
    if ($row['objective_type'] === 'OAC' && in_array($row['curriculum_track'], ['GENERAL','HC'], true)) {
        $assert($row['active'] === 'SI', "OAC oficial GENERAL/HC no activo: {$identity}");
    }
}

$activeGrades = [];
foreach ($objectives as $row) {
    if ($row['active'] === 'SI') {
        $activeGrades[$row['grade_code']] = true;
    }
}
foreach (['NT1','NT2','1B','2B','3B','4B','5B','6B','7B','8B','1M','2M','3M','4M'] as $grade) {
    $assert(isset($activeGrades[$grade]), "No hay objetivo activable para {$grade}.");
}

$sourceMap = [];
foreach ($sources as $source) {
    $key = (string) $source['source_key'];
    $assert(! isset($sourceMap[$key]), "Fuente duplicada: {$key}");
    $sourceMap[$key] = $source;
    $path = (string) ($source['evidence_path'] ?? '');
    $assert($path !== '' && is_file($path), "Evidencia ausente: {$key}");
    if ($path !== '' && is_file($path)) {
        $actual = hash_file('sha256', $path);
        $assert(hash_equals((string) $source['source_sha256'], $actual), "SHA-256 no coincide: {$key}");
    }
}

$relationMap = [];
foreach ($relations as $relation) {
    $identity = implode('|', [
        $relation['objective_code'], $relation['objective_type'], $relation['subject_code'], $relation['level_code'],
        $relation['grade_code'], $relation['curriculum_track'], $relation['axis_code'],
    ]);
    $relationMap[$identity][] = $relation;
    $assert(isset($sourceMap[$relation['source_key']]), "Relación apunta a fuente no incluida: {$relation['source_key']}");
}
foreach ($identities as $identity => $objective) {
    $rows = $relationMap[$identity] ?? [];
    $canonical = array_filter($rows, static fn (array $row): bool => $row['source_role'] === 'canonical_text');
    $legal = array_filter($rows, static fn (array $row): bool => $row['source_role'] === 'legal_basis');
    $assert(count($canonical) === 1, "Canonical_text != 1: {$identity}");
    $assert(count($legal) >= 1, "Falta legal_basis: {$identity}");
    if ($objective['subject_code'] === 'LCPOA') {
        $legalKeys = array_column($legal, 'source_key');
        $assert(in_array('CN_PDF_BASES_LCPOA', $legalKeys, true), "LCPOA sin base DS97: {$identity}");
    }
}

$recordIds = array_column($master, 'record_id');
$assert(count($recordIds) === count(array_unique($recordIds)), 'record_id maestro duplicado.');
foreach ($master as $row) {
    if (in_array($row['objective_type'], ['OF','OFT'], true) && str_contains($row['source_key'], 'ARTISTICA')) {
        $assert($row['curriculum_track'] === 'ARTISTICA', "Fila artística fuera de track ARTISTICA: {$row['record_id']}");
    }
}

$summary = [
    'master_rows' => count($master),
    'canonical_groups' => count(array_unique(array_column($master, 'canonical_group_id'))),
    'import_rows' => count($objectives),
    'active_rows' => count(array_filter($objectives, static fn (array $row): bool => $row['active'] === 'SI')),
    'sources' => count($sources),
    'relations' => count($relations),
    'errors' => $errors,
];

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
exit($errors === [] ? 0 : 1);
