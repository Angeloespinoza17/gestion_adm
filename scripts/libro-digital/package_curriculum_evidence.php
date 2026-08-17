<?php

declare(strict_types=1);

if ($argc !== 5) {
    fwrite(STDERR, "Uso: php {$argv[0]} <dataset.json> <workbook.xlsx> <salida.zip> <manifest.json>\n");
    exit(64);
}

[$script, $datasetPath, $workbookPath, $zipPath, $manifestPath] = $argv;
foreach ([$datasetPath, $workbookPath] as $path) {
    if (! is_file($path)) {
        throw new RuntimeException("No existe el insumo: {$path}");
    }
}

$data = json_decode((string) file_get_contents($datasetPath), true, 512, JSON_THROW_ON_ERROR);
$requiredKeys = array_fill_keys(array_column((array) ($data['import_sources'] ?? []), 'source_key'), true);
$manifestSources = [];
$seenFiles = [];

foreach ((array) ($data['master_sources'] ?? []) as $source) {
    $key = (string) $source['source_key'];
    $path = (string) $source['evidence_path'];
    $filename = (string) ($source['package_filename'] ?? '');
    if ($key === '' || $path === '' || ! is_file($path) || $filename === '') {
        throw new RuntimeException("Fuente incompleta: {$key}");
    }
    if (isset($seenFiles[$filename])) {
        throw new RuntimeException("Nombre duplicado en paquete: {$filename}");
    }
    $seenFiles[$filename] = true;
    $actualHash = hash_file('sha256', $path);
    if (! hash_equals((string) $source['source_sha256'], $actualHash)) {
        throw new RuntimeException("SHA-256 no coincide para {$key}");
    }
    $manifestSources[] = [
        'source_key' => $key,
        'source_scope' => (string) $source['source_scope'],
        'source_name' => (string) $source['source_name'],
        'source_url' => (string) $source['source_url'],
        'sha256' => $actualHash,
        'bytes' => filesize($path),
        'package_path' => 'evidence_files/'.$filename,
        'required_for_import' => isset($requiredKeys[$key]),
        'hash_scope' => (string) $source['hash_scope'],
    ];
}

$manifest = [
    'schema' => 'cnsc-curriculum-evidence-package/v1',
    'generated_at' => gmdate('c'),
    'consulted_at' => '2026-08-14',
    'authority' => 'Ministerio de Educación de Chile — Unidad de Currículum y Evaluación',
    'source_portal' => 'https://www.curriculumnacional.cl/',
    'notice' => 'Hashes calculados sobre bytes archivados. El paquete no constituye certificación ni aprobación de MINEDUC.',
    'workbook' => [
        'filename' => basename($workbookPath),
        'sha256' => hash_file('sha256', $workbookPath),
        'bytes' => filesize($workbookPath),
    ],
    'dataset_stats' => (array) ($data['stats'] ?? []),
    'source_counts' => [
        'master' => count($manifestSources),
        'required_for_import' => count($requiredKeys),
    ],
    'sources' => $manifestSources,
];

$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
if (! is_dir(dirname($zipPath)) && ! mkdir(dirname($zipPath), 0775, true) && ! is_dir(dirname($zipPath))) {
    throw new RuntimeException('No se pudo crear el directorio de salida.');
}
file_put_contents($manifestPath, $manifestJson, LOCK_EX);

$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('No se pudo crear el ZIP.');
}
try {
    foreach ((array) ($data['master_sources'] ?? []) as $source) {
        $entry = 'evidence_files/'.(string) $source['package_filename'];
        if (! $zip->addFile((string) $source['evidence_path'], $entry)) {
            throw new RuntimeException("No se pudo agregar {$entry}");
        }
    }
    $zip->addFromString('manifest.json', $manifestJson);
    $zip->addFromString('LEEME.txt', implode("\n", [
        'PAQUETE DE EVIDENCIAS — CURRÍCULUM NACIONAL',
        '',
        '1. Extraiga el ZIP.',
        '2. Para validar el Excel, adjunte cada archivo con la clave evidence_files[SOURCE_KEY].',
        '3. El nombre del archivo coincide con SOURCE_KEY y su extensión.',
        '4. El backend vuelve a calcular SHA-256 y falla cerrado ante ausencia o diferencia.',
        '5. Respete las filas active=NO y la hoja Exclusiones.',
        '',
        'Este paquete conserva bytes consultados el 2026-08-14; no es certificación MINEDUC.',
        '',
    ]));
} finally {
    $zip->close();
}

$check = new ZipArchive;
if ($check->open($zipPath, ZipArchive::CHECKCONS) !== true) {
    throw new RuntimeException('El ZIP generado no supera CHECKCONS.');
}
$expectedEntries = count($manifestSources) + 2;
$actualEntries = $check->numFiles;
$check->close();
if ($actualEntries !== $expectedEntries) {
    throw new RuntimeException("ZIP incompleto: {$actualEntries}/{$expectedEntries}");
}

fwrite(STDOUT, json_encode([
    'zip' => $zipPath,
    'zip_sha256' => hash_file('sha256', $zipPath),
    'zip_bytes' => filesize($zipPath),
    'manifest' => $manifestPath,
    'sources' => count($manifestSources),
    'required_for_import' => count($requiredKeys),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
