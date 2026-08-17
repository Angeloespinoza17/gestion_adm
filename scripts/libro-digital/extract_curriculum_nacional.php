<?php

declare(strict_types=1);

/**
 * Extractor reproducible de las fichas públicas de Currículum Nacional.
 *
 * Uso:
 *   php scripts/libro-digital/extract_curriculum_nacional.php \
 *     /private/tmp/cn-grade-subject-map.json \
 *     /private/tmp/cn-curriculum-corpus
 *
 * El script no modifica la base de datos. Archiva el HTML recibido, calcula
 * SHA-256 sobre sus bytes y genera un corpus JSON auditable.
 */

if ($argc < 3) {
    fwrite(STDERR, "Uso: php {$argv[0]} <grade-subject-map.json> <output-dir>\n");
    exit(64);
}

$mapPath = realpath($argv[1]);
$outputDir = rtrim($argv[2], DIRECTORY_SEPARATOR);
$reuseExisting = in_array('--reuse-existing', array_slice($argv, 3), true);
if ($mapPath === false || ! is_file($mapPath)) {
    fwrite(STDERR, "No se pudo leer el mapa de niveles y asignaturas.\n");
    exit(66);
}

if (! is_dir($outputDir) && ! mkdir($outputDir, 0775, true) && ! is_dir($outputDir)) {
    fwrite(STDERR, "No se pudo crear {$outputDir}.\n");
    exit(73);
}
$evidenceDir = $outputDir.DIRECTORY_SEPARATOR.'evidencias-html';
if (! is_dir($evidenceDir) && ! mkdir($evidenceDir, 0775, true) && ! is_dir($evidenceDir)) {
    fwrite(STDERR, "No se pudo crear {$evidenceDir}.\n");
    exit(73);
}

$map = json_decode((string) file_get_contents($mapPath), true, 512, JSON_THROW_ON_ERROR);
$pages = [];
foreach ((array) $map as $gradeEntry) {
    $gradeUrl = (string) ($gradeEntry['gradeUrl'] ?? '');
    $gradeTitle = cleanText((string) ($gradeEntry['title'] ?? ''));
    foreach ((array) ($gradeEntry['links'] ?? []) as $link) {
        $url = normalizeUrl((string) ($link['href'] ?? ''));
        if ($url === null || ! isSubjectPage($url)) {
            continue;
        }
        $pages[$url] = [
            'url' => $url,
            'grade_url' => $gradeUrl,
            'grade_title' => $gradeTitle,
            'subject_name' => cleanText((string) ($link['text'] ?? '')),
        ];
    }
}
ksort($pages, SORT_STRING);

$objectives = [];
$sources = [];
$subjects = [];
$pageResults = [];
$failures = [];
$startedAt = gmdate('c');
$position = 0;
$total = count($pages);

foreach ($pages as $page) {
    $position++;
    $url = $page['url'];
    [$levelCode, $gradeCodes, $track, $sourceScope] = gradeContext($url, $page['grade_title']);
    try {
        $sourceKey = sourceKey($url, $gradeCodes[0] ?? 'NA');
        $evidencePath = $evidenceDir.DIRECTORY_SEPARATOR.$sourceKey.'.html';
        if ($reuseExisting && is_file($evidencePath)) {
            $html = (string) file_get_contents($evidencePath);
        } else {
            $html = download($url);
            file_put_contents($evidencePath, $html, LOCK_EX);
        }
        $hash = hash('sha256', $html);

        $parsed = parseSubjectPage($html, $url, $page['subject_name']);
        $pageObjectiveCount = 0;
        foreach ($parsed['objectives'] as $rawObjective) {
            foreach ($gradeCodes as $gradeCode) {
                $subjectName = $levelCode === 'PARVULARIA'
                    ? ($rawObjective['axis_name'] ?: $page['subject_name'])
                    : $page['subject_name'];
                $subjectCode = subjectCode($subjectName, $url, $levelCode, $rawObjective['axis_name']);
                $objectiveType = objectiveType($rawObjective['code'], $rawObjective['title']);
                $normativeStatus = normativeStatus($url, $page['subject_name']);
                $activeRecommended = $normativeStatus !== 'PROPUESTA_NO_BASE';
                $objective = [
                    'catalog_code' => 'CN_OFICIAL_NT1_4M',
                    'catalog_version' => '2026-08-14',
                    'code' => $rawObjective['code'],
                    'objective_type' => $objectiveType,
                    'raw_objective_title' => $rawObjective['title'],
                    'subject_code' => $subjectCode,
                    'subject_name' => $subjectName,
                    'level_code' => $levelCode,
                    'grade_code' => $gradeCode,
                    'curriculum_track' => $track,
                    'axis_code' => axisCode($rawObjective['axis_name']),
                    'axis_name' => $rawObjective['axis_name'],
                    'unit_code' => '',
                    'description' => $rawObjective['description'],
                    'indicators' => $rawObjective['indicators'],
                    'active' => $activeRecommended,
                    'normative_status' => $normativeStatus,
                    'source_page' => mb_substr($rawObjective['code'], 0, 80),
                    'objective_url' => $rawObjective['url'],
                    'subject_page_url' => $url,
                    'source_key' => $sourceKey,
                    'source_sha256' => $hash,
                    'retrieved_at' => gmdate('c'),
                ];
                $identity = implode('|', [
                    upper($objective['code']), $objectiveType, $subjectCode, $levelCode,
                    $gradeCode, $track, $objective['axis_code'],
                ]);
                if (! isset($objectives[$identity])) {
                    $objectives[$identity] = $objective;
                    $pageObjectiveCount++;
                }
                $subjectIdentity = $subjectCode.'|'.$track;
                $subjects[$subjectIdentity] ??= [
                    'subject_code' => $subjectCode,
                    'subject_name' => $subjectName,
                    'curriculum_track' => $track,
                    'level_code' => $levelCode,
                    'normative_status' => $normativeStatus,
                    'source_url' => $url,
                    'active_recommended' => $activeRecommended,
                ];
            }
        }

        $sources[$sourceKey] = [
            'source_key' => $sourceKey,
            'source_scope' => $sourceScope,
            'source_name' => $parsed['title'] ?: $page['subject_name'].' '.$page['grade_title'],
            'authority' => 'Ministerio de Educación de Chile — Unidad de Currículum y Evaluación',
            'document_number' => 'Ficha pública Currículum Nacional',
            'source_url' => $url,
            'source_sha256' => $hash,
            'effective_from' => '',
            'effective_to' => '',
            'curriculum_track' => $track,
            'subject_code' => '',
            'objective_type' => '',
            'evidence_filename' => basename($evidencePath),
            'retrieved_at' => gmdate('c'),
        ];
        $pageResults[] = [
            'url' => $url,
            'title' => $parsed['title'],
            'grade_codes' => $gradeCodes,
            'track' => $track,
            'source_key' => $sourceKey,
            'sha256' => $hash,
            'objective_count' => $pageObjectiveCount,
            'status' => $parsed['objectives'] === [] ? 'NO_OBJECTIVES' : 'OK',
        ];
        fwrite(STDERR, sprintf("[%d/%d] %s — %d objetivos\n", $position, $total, $url, $pageObjectiveCount));
    } catch (Throwable $exception) {
        $failures[] = ['url' => $url, 'message' => $exception->getMessage()];
        fwrite(STDERR, sprintf("[%d/%d] ERROR %s — %s\n", $position, $total, $url, $exception->getMessage()));
    }
    if (! $reuseExisting) {
        usleep(120_000);
    }
}

$objectives = array_values($objectives);
usort($objectives, static fn (array $a, array $b): int => [
    gradeOrder($a['grade_code']), $a['curriculum_track'], $a['subject_name'], $a['objective_type'], $a['code'], $a['axis_name'],
] <=> [
    gradeOrder($b['grade_code']), $b['curriculum_track'], $b['subject_name'], $b['objective_type'], $b['code'], $b['axis_name'],
]);
$subjects = array_values($subjects);
usort($subjects, static fn (array $a, array $b): int => [$a['curriculum_track'], $a['subject_name'], $a['subject_code']] <=> [$b['curriculum_track'], $b['subject_name'], $b['subject_code']]);
$sources = array_values($sources);
usort($sources, static fn (array $a, array $b): int => [$a['source_scope'], $a['source_name'], $a['source_key']] <=> [$b['source_scope'], $b['source_name'], $b['source_key']]);

$stats = [
    'started_at' => $startedAt,
    'finished_at' => gmdate('c'),
    'source_index' => 'https://www.curriculumnacional.cl/curriculum/cursos-y-niveles',
    'subject_pages_discovered' => $total,
    'subject_pages_processed' => count($pageResults),
    'subject_pages_with_objectives' => count(array_filter($pageResults, static fn (array $row): bool => $row['status'] === 'OK')),
    'subject_pages_without_objectives' => count(array_filter($pageResults, static fn (array $row): bool => $row['status'] === 'NO_OBJECTIVES')),
    'failures' => count($failures),
    'subjects' => count($subjects),
    'objectives' => count($objectives),
    'sources' => count($sources),
    'objectives_by_grade' => counts($objectives, 'grade_code'),
    'objectives_by_track' => counts($objectives, 'curriculum_track'),
    'objectives_by_type' => counts($objectives, 'objective_type'),
    'objectives_by_status' => counts($objectives, 'normative_status'),
];

$payload = [
    'schema' => 'cnsc-curriculum-national-extract/v1',
    'notice' => 'Extracción automatizada de páginas públicas oficiales. Requiere revisión humana de vigencia, completitud y correspondencia normativa antes de activación productiva.',
    'stats' => $stats,
    'subjects' => $subjects,
    'sources' => $sources,
    'objectives' => $objectives,
    'pages' => $pageResults,
    'failures' => $failures,
];
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($outputDir.DIRECTORY_SEPARATOR.'corpus.json', $json."\n", LOCK_EX);
file_put_contents($outputDir.DIRECTORY_SEPARATOR.'stats.json', json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n", LOCK_EX);

fwrite(STDOUT, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
exit($failures === [] ? 0 : 1);

function normalizeUrl(string $url): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
        return null;
    }
    if (str_starts_with($url, '/')) {
        $url = 'https://www.curriculumnacional.cl'.$url;
    }
    if (! str_starts_with($url, 'https://www.curriculumnacional.cl/')) {
        return null;
    }
    return preg_replace('/[?#].*$/', '', $url) ?: null;
}

function isSubjectPage(string $url): bool
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    return preg_match('~^/curriculum/(educacion-parvularia|1o-6o-basico|7o-basico-2o-medio|3o-4o-medio|3o-4o-medio-tecnico-profesional|bases-curriculares-lengua-cultura-pueblos-originarios-ancestrales-1-6-ano-basico)/.+/(nt-nivel-transicion|[1-8]-basico|[12]-medio|[34]-medio-(fg|hc|tp))$~', $path) === 1
        && ! str_contains($path, '/curso/');
}

/** @return array{0:string,1:list<string>,2:string,3:string} */
function gradeContext(string $url, string $gradeTitle): array
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (str_contains($path, '/educacion-parvularia/')) {
        return ['PARVULARIA', ['NT1', 'NT2'], 'PARVULARIA', 'PARVULARIA_NT1_NT2'];
    }
    if (preg_match('~/([1-8])-basico$~', $path, $match)) {
        $grade = $match[1].'B';
        return ['BASICA', [$grade], 'GENERAL', (int) $match[1] <= 6 ? 'GENERAL_1B_6B' : 'GENERAL_7B_2M'];
    }
    if (preg_match('~/([12])-medio$~', $path, $match)) {
        return ['MEDIA', [$match[1].'M'], 'GENERAL', 'GENERAL_7B_2M'];
    }
    if (preg_match('~/([34])-medio-(fg|hc|tp)$~', $path, $match)) {
        $track = match ($match[2]) {
            'tp' => 'TP',
            'hc' => 'HC',
            default => 'GENERAL',
        };
        $scope = $track === 'TP' ? 'TP_3M_4M' : 'HC_3M_4M';
        return ['MEDIA', [$match[1].'M'], $track, $scope];
    }
    throw new RuntimeException("No se pudo resolver el grado: {$gradeTitle} ({$url}).");
}

function download(string $url): string
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('No se pudo iniciar la descarga.');
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'CNSC-LibroDigital-CurriculumArchive/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $type = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
    curl_close($handle);
    if (! is_string($body) || $body === '' || $status !== 200) {
        throw new RuntimeException("HTTP {$status}: ".($error ?: 'respuesta vacía'));
    }
    if (! str_contains($type, 'text/html') && ! str_contains($type, 'application/xhtml+xml')) {
        throw new RuntimeException("Tipo de contenido inesperado: {$type}");
    }
    if (strlen($body) > 5_000_000) {
        throw new RuntimeException('La página supera el límite de 5 MiB.');
    }
    return $body;
}

/** @return array{title:string,objectives:list<array<string,mixed>>} */
function parseSubjectPage(string $html, string $pageUrl, string $fallbackSubject): array
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)) {
            throw new RuntimeException('HTML oficial inválido.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    $title = cleanText((string) ($xpath->query('//title')->item(0)?->textContent ?? $fallbackSubject));
    $items = $xpath->query('//main//div[contains(concat(" ", normalize-space(@class), " "), " item-wrapper ")][.//h4[contains(concat(" ", normalize-space(@class), " "), " wrapper-title-oa ")]]');
    $objectives = [];
    foreach ($items ?: [] as $item) {
        if (! $item instanceof DOMElement) {
            continue;
        }
        $code = cleanText((string) ($xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " number-title ")]', $item)->item(0)?->textContent ?? ''));
        $objectiveTitle = cleanText((string) ($xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " oa-title ")]', $item)->item(0)?->textContent ?? ''));
        $descriptionNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " field--name-description ")]', $item)->item(0);
        $description = $descriptionNode instanceof DOMElement ? htmlText($document, $descriptionNode) : '';
        if ($code === '' || $description === '') {
            continue;
        }
        $link = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " link-more ")]', $item)->item(0);
        $href = $link instanceof DOMElement ? $link->getAttribute('href') : '';
        $objectiveUrl = absoluteUrl($href, $pageUrl) ?? $pageUrl;
        $axisName = '';
        $ancestor = $item->parentNode;
        while ($ancestor instanceof DOMElement) {
            if (hasClass($ancestor, 'items-wrapper')) {
                $axisName = cleanText((string) ($xpath->query('./h3[1]', $ancestor)->item(0)?->textContent ?? ''));
                break;
            }
            $ancestor = $ancestor->parentNode;
        }
        $indicators = [];
        if ($descriptionNode instanceof DOMElement) {
            foreach ($xpath->query('.//li', $descriptionNode) ?: [] as $indicatorNode) {
                $indicator = $indicatorNode instanceof DOMElement ? htmlText($document, $indicatorNode) : '';
                if ($indicator !== '') {
                    $indicators[] = $indicator;
                }
            }
        }
        $objectives[] = [
            'code' => $code,
            'title' => $objectiveTitle,
            'description' => $description,
            'axis_name' => $axisName,
            'indicators' => array_values(array_unique($indicators)),
            'url' => $objectiveUrl,
        ];
    }
    return ['title' => $title, 'objectives' => $objectives];
}

function htmlText(DOMDocument $document, DOMElement $element): string
{
    $html = (string) $document->saveHTML($element);
    $text = preg_replace('/<[^>]+>/u', ' ', $html) ?? $html;
    $text = cleanText($text);
    $text = preg_replace('/\s+([,.;:!?%\)\]])/u', '$1', $text) ?? $text;
    $text = preg_replace('/([¿¡\(\[])\s+/u', '$1', $text) ?? $text;
    return $text;
}

function hasClass(DOMElement $element, string $class): bool
{
    return in_array($class, preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], true);
}

function absoluteUrl(string $href, string $base): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '') {
        return null;
    }
    if (str_starts_with($href, 'https://www.curriculumnacional.cl/')) {
        return preg_replace('/[?#].*$/', '', $href) ?: null;
    }
    if (str_starts_with($href, 'http://www.curriculumnacional.cl/')) {
        return preg_replace('/[?#].*$/', '', 'https://'.substr($href, 7)) ?: null;
    }
    if (str_starts_with($href, '/')) {
        return 'https://www.curriculumnacional.cl'.(preg_replace('/[?#].*$/', '', $href) ?: $href);
    }
    $directory = rtrim(dirname((string) parse_url($base, PHP_URL_PATH)), '/');
    return 'https://www.curriculumnacional.cl'.$directory.'/'.$href;
}

function objectiveType(string $code, string $title): string
{
    $value = upper($code.' '.$title);
    if (preg_match('/\bOFT\b/u', $value)) {
        return 'OFT';
    }
    if (preg_match('/(^|\s)OF(?:\s|$)/u', $value)) {
        return 'OF';
    }
    if (str_contains($value, 'TRANSVERSAL') || preg_match('/\bOAT\b/u', $value)) {
        return 'OAT';
    }
    foreach (['OAH', 'OAA', 'OAG', 'OAC'] as $type) {
        if (preg_match('/\b'.$type.'\b/u', $value)) {
            return $type;
        }
    }
    return 'OA';
}

function normativeStatus(string $url, string $subjectName): string
{
    $value = lower($url.' '.$subjectName);
    if (str_contains($value, 'ingles-propuesta') || str_contains($value, 'inglés (propuesta)')) {
        return 'PROPUESTA_NO_BASE';
    }
    return 'PUBLICADO_PORTAL_OFICIAL';
}

function sourceKey(string $url, string $grade): string
{
    return 'CN_HTML_'.$grade.'_'.strtoupper(substr(hash('sha256', $url), 0, 16));
}

function subjectCode(string $name, string $url, string $level, string $axisName): string
{
    $canonicalName = $level === 'PARVULARIA' && $axisName !== '' ? $axisName : $name;
    $known = [
        'ARTES VISUALES' => 'ART',
        'CIENCIAS NATURALES' => 'CNA',
        'EDUCACION FISICA Y SALUD' => 'EFS',
        'HISTORIA GEOGRAFIA Y CIENCIAS SOCIALES' => 'HGCS',
        'INGLES' => 'ING',
        'INGLES PROPUESTA' => 'ING_PROP',
        'LENGUA Y CULTURA DE LOS PUEBLOS ORIGINARIOS ANCESTRALES' => 'LCPOA',
        'LENGUA INDIGENA' => 'LIND',
        'LENGUAJE Y COMUNICACION' => 'LEN',
        'LENGUA Y LITERATURA' => 'LYL',
        'MATEMATICA' => 'MAT',
        'MUSICA' => 'MUS',
        'ORIENTACION' => 'ORI',
        'RELIGION' => 'REL',
        'TECNOLOGIA' => 'TEC',
        'PENSAMIENTO MATEMATICO' => 'PM',
        'COMPRENSION DEL ENTORNO SOCIOCULTURAL' => 'CES',
        'CONVIVENCIA Y CIUDADANIA' => 'CC',
        'CORPORALIDAD Y MOVIMIENTO' => 'CM',
        'EXPLORACION DEL ENTORNO NATURAL' => 'EEN',
        'IDENTIDAD Y AUTONOMIA' => 'IA',
        'LENGUAJES ARTISTICOS' => 'LA',
        'LENGUAJE VERBAL' => 'LV',
    ];
    $key = asciiUpper($canonicalName);
    if (isset($known[$key])) {
        return $known[$key];
    }
    $slug = preg_replace('/[^A-Z0-9]+/', '_', $key) ?: 'ASIGNATURA';
    $slug = trim($slug, '_');
    $candidate = 'CN_'.$slug;
    if (strlen($candidate) <= 48) {
        return $candidate;
    }
    return substr($candidate, 0, 37).'_'.strtoupper(substr(hash('sha256', $url.'|'.$canonicalName), 0, 8));
}

function axisCode(string $axis): string
{
    $axis = asciiUpper($axis);
    if ($axis === '') {
        return '';
    }
    $code = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $axis), '_');
    return substr($code, 0, 80);
}

function cleanText(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x{00A0}\s]+/u', ' ', $value) ?? $value;
    return trim($value);
}

function upper(string $value): string
{
    return mb_strtoupper(cleanText($value), 'UTF-8');
}

function lower(string $value): string
{
    return mb_strtolower(cleanText($value), 'UTF-8');
}

function asciiUpper(string $value): string
{
    $value = upper($value);
    $value = strtr($value, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return cleanText(is_string($ascii) ? $ascii : $value);
}

function gradeOrder(string $grade): int
{
    static $order = ['NT1', 'NT2', '1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B', '1M', '2M', '3M', '4M'];
    $index = array_search($grade, $order, true);
    return $index === false ? 999 : $index;
}

/** @param list<array<string,mixed>> $rows @return array<string,int> */
function counts(array $rows, string $field): array
{
    $counts = [];
    foreach ($rows as $row) {
        $value = (string) ($row[$field] ?? '');
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);
    return $counts;
}
