<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskCatalogItem;
use App\Models\RiskPrevention\RiskImportBatch;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\RiskPrevention\RiskMethodology;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class LegacyRiskMatrixImportService
{
    private const HEADERS = [
        'actividad' => ['actividad'], 'task' => ['tarea'], 'position' => ['puesto de trabajo', 'puesto'],
        'location' => ['lugar especifico', 'lugar'], 'women' => ['exposicion f', 'f'], 'men' => ['exposicion m', 'm'],
        'other' => ['exposicion otro', 'otro'], 'routine' => ['rutinaria no rutinaria', 'rutinaria'],
        'factor' => ['factor de riesgo', 'factor'], 'hazard' => ['peligro'], 'risk' => ['riesgo especifico', 'riesgo'],
        'harm' => ['dano posible', 'posible dano', 'consecuencia dano'], 'probability' => ['probabilidad'],
        'consequence' => ['consecuencia'], 'magnitude' => ['magnitud', 'vep'], 'classification' => ['clasificacion'],
        'hierarchy' => ['tipo de control', 'jerarquia'], 'control' => ['medida de control', 'medida'],
        'controlled' => ['riesgo controlado'], 'responsible' => ['responsable'], 'periodicity' => ['periodicidad'],
    ];

    public function __construct(
        private readonly LegacyRiskValueNormalizer $normalizer,
        private readonly VepRiskCalculator $calculator,
        private readonly RiskMatrixService $matrices,
        private readonly RiskMatrixVersionService $versions,
        private readonly RiskMatrixStructureService $structures,
        private readonly RiskMatrixAuditService $audit,
    ) {}

    public function preview(UploadedFile $file, User $actor, ?int $workCenterId = null): RiskImportBatch
    {
        $this->assertSafeArchive($file->getRealPath(), $file->getSize());
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs('risk-prevention/imports/'.now()->format('Y/m'), $hash.'.xlsx', 'local');
        $batch = RiskImportBatch::query()->create([
            'company_key' => config('risk_matrix.company.key'),
            'work_center_id' => $workCenterId,
            'uploaded_by' => $actor->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'file_hash' => $hash,
            'file_size' => $file->getSize(),
            'status' => 'analyzing',
        ]);

        try {
            $result = $this->analyze(Storage::disk('local')->path($path));
            $duplicates = RiskImportBatch::query()->where('file_hash', $hash)->where('id', '!=', $batch->id)->where('status', 'committed')->count();
            if ($duplicates > 0) {
                $result['summary']['duplicate_file_warning'] = 'Este archivo ya fue confirmado anteriormente. La nueva confirmación requiere decisión explícita.';
            }
            $batch->update([
                'status' => 'preview_ready',
                'total_rows' => count($result['rows']),
                'valid_rows' => collect($result['rows'])->where('has_error', false)->count(),
                'warning_rows' => collect($result['rows'])->where('has_warning', true)->count(),
                'error_rows' => collect($result['rows'])->where('has_error', true)->count(),
                'duplicate_rows' => $result['summary']['duplicate_rows'],
                'mapping_configuration' => $result['mapping'],
                'preview_payload' => ['rows' => $result['rows']],
                'summary' => $result['summary'],
            ]);
            foreach ($result['issues'] as $issue) {
                $batch->issues()->create($issue);
            }
            $this->audit->record($batch, 'import_previewed', [], ['rows' => count($result['rows']), 'hash' => $hash]);

            return $batch->fresh('issues');
        } catch (\Throwable $exception) {
            $batch->update(['status' => 'failed', 'summary' => ['error' => $exception->getMessage()]]);
            throw $exception;
        }
    }

    public function commit(RiskImportBatch $batch, array $destination, User $actor): RiskMatrixVersion
    {
        abort_unless($batch->status === 'preview_ready', 409, 'La importación no está lista para confirmar.');
        if ($batch->error_rows > 0 && ! ($destination['skip_error_rows'] ?? false)) {
            throw ValidationException::withMessages(['batch' => 'Resuelva u omita explícitamente las filas con error antes de confirmar.']);
        }
        $previousCommit = RiskImportBatch::query()->where('file_hash', $batch->file_hash)->where('id', '!=', $batch->id)->where('status', 'committed')->exists();
        if ($previousCommit && ! ($destination['confirm_duplicate_file'] ?? false)) {
            throw ValidationException::withMessages(['file' => 'El archivo ya fue importado. Confirme explícitamente si desea continuar.']);
        }

        return DB::transaction(function () use ($batch, $destination, $actor) {
            $targetType = $destination['type'] ?? 'new_matrix';
            if ($targetType === 'new_matrix') {
                $sourceMetadata = data_get($batch->summary, 'matrix_metadata', []);
                $created = $this->matrices->create([
                    'code' => $destination['code'],
                    'folio' => filled($destination['folio'] ?? null) ? $destination['folio'] : ($sourceMetadata['source_folio'] ?? null),
                    'name' => $destination['name'],
                    'description' => 'Matriz creada mediante importación histórica trazable desde '.$batch->original_filename.'.',
                    'work_center_id' => $destination['work_center_id'] ?? $batch->work_center_id,
                    'work_center_name' => $sourceMetadata['work_center_name'] ?? null,
                    'company_name' => $sourceMetadata['company_name'] ?? null,
                    'company_tax_id' => $sourceMetadata['company_tax_id'] ?? null,
                    'company_address' => $sourceMetadata['company_address'] ?? null,
                    'economic_activity_code' => $sourceMetadata['economic_activity_code'] ?? null,
                    'prepared_on' => $sourceMetadata['prepared_on'] ?? null,
                    'updated_on' => $sourceMetadata['updated_on'] ?? null,
                    'total_workers' => $sourceMetadata['total_workers'] ?? null,
                    'program_responsible_id' => $actor->id,
                    'program_responsible_name' => $sourceMetadata['program_responsible_name'] ?? null,
                    'program_responsible_position' => $sourceMetadata['program_responsible_position'] ?? null,
                    'legal_representative_name' => $sourceMetadata['legal_representative_name'] ?? null,
                ], $actor);
                $version = $created['version'];
            } else {
                $matrix = RiskMatrix::query()
                    ->where('company_key', config('risk_matrix.company.key'))
                    ->findOrFail($destination['matrix_id']);
                $source = $matrix->versions()->findOrFail($destination['version_id']);
                $version = $source->isEditable()
                    ? $source
                    : $this->versions->createFrom($matrix, $source, $actor, 'Importación histórica sobre nueva versión');
            }

            $processes = $this->toStructure($batch->preview_payload['rows'] ?? [], (bool) ($destination['skip_error_rows'] ?? false));
            $version = $this->structures->replace($version->load('methodology'), $processes, $version->lock_version, $actor->id);
            $version->update([
                'source_import_batch_id' => $batch->id,
                'updated_on' => data_get($batch->summary, 'matrix_metadata.updated_on', $version->updated_on),
            ]);
            $batch->update(['status' => 'committed', 'target_matrix_id' => $version->risk_matrix_id, 'target_version_id' => $version->id]);
            $this->audit->record($batch, 'import_committed', [], ['target_version_id' => $version->id]);

            return $version;
        });
    }

    /** @return array<string, mixed> */
    private function analyze(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);
        $sheets = [];
        $criteriaHashes = [];
        $issues = [];
        $rows = [];
        $mapping = [];
        $seen = [];
        $duplicateRows = 0;
        $ignoredFooterRows = 0;
        $matrixMetadata = [];
        $methodology = RiskMethodology::query()->where('code', config('risk_matrix.methodology_code'))->where('active', true)->latest('version_number')->firstOrFail();

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $sheetKey = $this->normalizer->key($sheet->getTitle());
            $sheetType = match (true) {
                str_contains($sheetKey, 'criterios') && str_contains($sheetKey, 'iper') => 'criteria',
                str_contains($sheetKey, 'programa') && str_contains($sheetKey, 'trabajo') => 'program',
                str_contains($sheetKey, 'iper') => 'matrix',
                default => 'unknown',
            };
            $sheets[] = ['name' => $sheet->getTitle(), 'type' => $sheetType];
            if ($sheetType === 'criteria') {
                $criteriaHashes[$sheet->getTitle()] = hash('sha256', json_encode($sheet->toArray(null, true, true, false), JSON_THROW_ON_ERROR));

                continue;
            }
            if ($sheetType !== 'matrix') {
                continue;
            }

            if ($matrixMetadata === []) {
                $matrixMetadata = $this->extractMatrixMetadata($sheet);
            }

            [$headerRow, $columns] = $this->detectHeaders($sheet);
            $mapping[$sheet->getTitle()] = ['header_row' => $headerRow, 'columns' => $columns];
            if (! $headerRow) {
                $issues[] = $this->issue($sheet->getTitle(), 0, null, null, null, 'error', 'headers_not_found', 'No se detectó una fila de encabezados IPER.');

                continue;
            }

            $maxRow = min($sheet->getHighestDataRow(), $headerRow + config('risk_matrix.import.max_rows', 10000));
            for ($rowNumber = $headerRow + 1; $rowNumber <= $maxRow; $rowNumber++) {
                $raw = [];
                foreach ($columns as $field => $column) {
                    $raw[$field] = $sheet->getCell([$column, $rowNumber])->getCalculatedValue();
                }
                if (collect($raw)->filter(fn ($value) => filled($this->normalizer->text($value)))->isEmpty()) {
                    continue;
                }
                if ($this->isDocumentFooterRow($raw)) {
                    $ignoredFooterRows++;

                    continue;
                }
                $normalized = $this->normalizeRow($raw, $methodology);
                $rowIssues = [];
                foreach (['activity' => 'Actividad', 'task' => 'Tarea', 'risk' => 'Riesgo específico'] as $field => $label) {
                    if (blank($normalized[$field])) {
                        $rowIssues[] = $this->issue($sheet->getTitle(), $rowNumber, $label, $raw[$field] ?? null, null, 'error', "{$field}_missing", "Falta {$label}.");
                    }
                }
                if ($normalized['evaluation_method'] === 'vep' && (! in_array($normalized['probability'], [1, 2, 4], true) || ! in_array($normalized['consequence'], [1, 2, 4], true))) {
                    $rowIssues[] = $this->issue($sheet->getTitle(), $rowNumber, 'VEP', null, null, 'error', 'vep_invalid', 'Probabilidad y consecuencia deben ser 1, 2 o 4.');
                }
                foreach ($normalized['normalization_warnings'] as $warning) {
                    $rowIssues[] = $this->issue($sheet->getTitle(), $rowNumber, $warning['column'], $warning['raw'], $warning['normalized'], 'warning', $warning['code'], $warning['message']);
                }
                if ($normalized['score'] === 8 && blank($normalized['control'])) {
                    $rowIssues[] = $this->issue($sheet->getTitle(), $rowNumber, 'Medida', null, null, 'warning', 'important_without_measure', 'Riesgo importante sin medida propuesta.');
                }
                if ($normalized['score'] === 16) {
                    $rowIssues[] = $this->issue($sheet->getTitle(), $rowNumber, 'Clasificación', $raw['classification'] ?? null, 'Intolerable', 'warning', 'intolerable_immediate_action', 'Riesgo intolerable: la aprobación quedará bloqueada hasta documentar respuesta inmediata.');
                }
                $duplicateKey = $this->normalizer->key(implode('|', collect($normalized)->only(['activity', 'task', 'position', 'location', 'risk', 'hazard', 'control'])->all()));
                if (isset($seen[$duplicateKey])) {
                    $duplicateRows++;
                    $rowIssues[] = $this->issue($sheet->getTitle(), $rowNumber, null, null, null, 'duplicate', 'possible_duplicate', "Posible duplicado de la fila {$seen[$duplicateKey]}.");
                } else {
                    $seen[$duplicateKey] = $rowNumber;
                }
                $issues = [...$issues, ...$rowIssues];
                $rows[] = [...$normalized, 'sheet_name' => $sheet->getTitle(), 'row_number' => $rowNumber, 'has_error' => collect($rowIssues)->contains('severity', 'error'), 'has_warning' => collect($rowIssues)->contains(fn ($issue) => in_array($issue['severity'], ['warning', 'duplicate'], true))];
            }
        }
        $workbook->disconnectWorksheets();

        return [
            'rows' => $rows,
            'issues' => $issues,
            'mapping' => $mapping,
            'summary' => [
                'sheets' => $sheets,
                'criteria_sheets' => array_keys($criteriaHashes),
                'criteria_sheets_differ' => count(array_unique($criteriaHashes)) > 1,
                'matrix_metadata' => $matrixMetadata,
                'duplicate_rows' => $duplicateRows,
                'ignored_footer_rows' => $ignoredFooterRows,
                'normalizations' => collect($issues)->where('severity', 'warning')->count(),
            ],
        ];
    }

    private function isDocumentFooterRow(array $raw): bool
    {
        $meaningful = collect($raw)
            ->filter(fn ($value) => filled($this->normalizer->text($value)));

        return $meaningful->count() <= 2
            && blank($this->normalizer->text($raw['task'] ?? null))
            && blank($this->normalizer->text($raw['risk'] ?? null))
            && blank($this->normalizer->text($raw['probability'] ?? null))
            && blank($this->normalizer->text($raw['consequence'] ?? null))
            && blank($this->normalizer->text($raw['hazard'] ?? null))
            && blank($this->normalizer->text($raw['control'] ?? null));
    }

    /** @return array<string, mixed> */
    private function extractMatrixMetadata(Worksheet $sheet): array
    {
        $metadata = [
            'source_code' => $this->metadataValue($sheet, ['codigo iper']),
            'source_folio' => $this->metadataValue($sheet, ['folio iper']),
            'company_tax_id' => $this->metadataValue($sheet, ['rut entidad empleadora']),
            'company_name' => $this->metadataValue($sheet, ['nombre razon social']),
            'company_address' => $this->metadataValue($sheet, ['direccion']),
            'work_center_name' => $this->metadataValue($sheet, ['nombre centro de trabajo']),
            'total_workers' => $this->metadataValue($sheet, ['numero de trabajadores']),
            'prepared_on' => $this->metadataValue($sheet, ['fecha elaboracion matriz']),
            'updated_on' => $this->metadataValue($sheet, ['fecha actualizacion']),
            'legal_representative_name' => $this->metadataValue($sheet, ['nombre representante legal']),
            'program_responsible_name' => $this->metadataValue($sheet, ['responsable programa plan de trabajo']),
            'program_responsible_position' => $this->metadataValue($sheet, ['cargo']),
        ];

        $economicActivity = $this->metadataValue($sheet, ['nombre proceso operacional apoyo', 'codigo ciiu']);
        if (preg_match('/CIIUSII[_\s-]*(\d+)/i', (string) $economicActivity, $matches)) {
            $metadata['economic_activity_code'] = $matches[1];
        } elseif (filled($economicActivity)) {
            $metadata['economic_activity_code'] = mb_substr((string) $economicActivity, 0, 80);
        }

        foreach (['prepared_on', 'updated_on'] as $dateField) {
            if (is_numeric($metadata[$dateField] ?? null)) {
                $metadata[$dateField] = SpreadsheetDate::excelToDateTimeObject((float) $metadata[$dateField])->format('Y-m-d');
            }
        }
        if (is_numeric($metadata['total_workers'] ?? null)) {
            $metadata['total_workers'] = (int) $metadata['total_workers'];
        }

        return collect($metadata)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function metadataValue(Worksheet $sheet, array $labels): mixed
    {
        $labelKeys = collect($labels)->map(fn ($label) => $this->normalizer->key($label))->all();
        $highestColumn = min(40, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = 1; $row <= min(30, $sheet->getHighestDataRow()); $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $key = $this->normalizer->key($sheet->getCell([$column, $row])->getValue());
                if (! in_array($key, $labelKeys, true)) {
                    continue;
                }
                for ($candidate = $column + 1; $candidate <= min($highestColumn, $column + 10); $candidate++) {
                    $value = $sheet->getCell([$candidate, $row])->getCalculatedValue();
                    $normalized = $this->normalizer->text($value);
                    if (filled($normalized) && $normalized !== '2') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /** @return array{0: ?int, 1: array<string, int>} */
    private function detectHeaders(Worksheet $sheet): array
    {
        for ($row = 1; $row <= min(30, $sheet->getHighestDataRow()); $row++) {
            $columns = [];
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($column = 1; $column <= min(60, $highestColumn); $column++) {
                $key = $this->normalizer->key($sheet->getCell([$column, $row])->getValue());
                foreach (self::HEADERS as $field => $synonyms) {
                    if (in_array($key, $synonyms, true) && ! isset($columns[$field])) {
                        $columns[$field] = $column;
                    }
                }
            }
            if (count($columns) >= 4 && isset($columns['task'], $columns['risk'])) {
                return [$row, $columns];
            }
        }

        return [null, []];
    }

    /** @return array<string, mixed> */
    private function normalizeRow(array $raw, RiskMethodology $methodology): array
    {
        $probability = $this->normalizer->vepFactor($raw['probability'] ?? null, 'probability');
        $consequence = $this->normalizer->vepFactor($raw['consequence'] ?? null, 'consequence');
        $warnings = [];
        foreach (['Probabilidad' => [$probability, $raw['probability'] ?? null], 'Consecuencia' => [$consequence, $raw['consequence'] ?? null]] as $column => [$factor, $source]) {
            if ($factor['warning']) {
                $warnings[] = ['column' => $column, 'raw' => $this->normalizer->text($source), 'normalized' => $factor['value'], 'code' => 'label_number_mismatch', 'message' => $factor['warning']];
            }
        }
        $calculation = (in_array($probability['value'], [1, 2, 4], true) && in_array($consequence['value'], [1, 2, 4], true))
            ? $this->calculator->calculate($probability['value'], $consequence['value'], $methodology)
            : null;
        $importedClass = $this->normalizer->classification($raw['classification'] ?? null);
        if ($calculation && $importedClass && $importedClass !== $calculation['risk_level_code']) {
            $warnings[] = ['column' => 'Clasificación', 'raw' => $this->normalizer->text($raw['classification'] ?? null), 'normalized' => $calculation['risk_level_label'], 'code' => 'classification_recalculated', 'message' => 'La clasificación importada no coincide con el VEP recalculado; se usó el resultado del backend.'];
        }
        if ($calculation && is_numeric($raw['magnitude'] ?? null) && (int) $raw['magnitude'] !== $calculation['vep']) {
            $warnings[] = ['column' => 'Magnitud', 'raw' => (string) $raw['magnitude'], 'normalized' => $calculation['vep'], 'code' => 'magnitude_recalculated', 'message' => 'La magnitud importada no coincide con P x C; se recalculó en backend.'];
        }

        return [
            'activity' => $this->normalizer->text($raw['actividad'] ?? null), 'task' => $this->normalizer->text($raw['task'] ?? null),
            'position' => $this->normalizer->text($raw['position'] ?? null), 'location' => $this->normalizer->text($raw['location'] ?? null),
            'women' => max(0, (int) ($raw['women'] ?? 0)), 'men' => max(0, (int) ($raw['men'] ?? 0)), 'other' => max(0, (int) ($raw['other'] ?? 0)),
            'routine_type' => $this->normalizer->routineType($raw['routine'] ?? null),
            'factor' => $this->normalizer->text($raw['factor'] ?? null), 'hazard' => $this->normalizer->text($raw['hazard'] ?? null),
            'risk' => $this->normalizer->text($raw['risk'] ?? null), 'harm' => $this->normalizer->text($raw['harm'] ?? null) ?? 'Daño no especificado en origen',
            'evaluation_method' => 'vep', 'probability' => $probability['value'], 'consequence' => $consequence['value'],
            'score' => $calculation['vep'] ?? null, 'risk_level_code' => $calculation['risk_level_code'] ?? null,
            'hierarchy_type' => $this->normalizer->hierarchy($raw['hierarchy'] ?? null), 'control' => $this->normalizer->text($raw['control'] ?? null),
            'controlled' => $this->normalizer->boolean($raw['controlled'] ?? null), 'responsible' => $this->normalizer->text($raw['responsible'] ?? null),
            'periodicity' => $this->normalizer->periodicity($raw['periodicity'] ?? null),
            'source_payload' => $raw, 'normalization_warnings' => $warnings,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function toStructure(array $rows, bool $skipErrors): array
    {
        $catalogs = RiskCatalogItem::query()->where('methodology_id', RiskMethodology::query()->where('code', config('risk_matrix.methodology_code'))->latest('version_number')->value('id'))->get()->groupBy('catalog_type');
        $familyId = $catalogs['risk_family']->firstWhere('code', 'work_safety')->id;
        $exposureIds = $catalogs['exposure_category']->pluck('id', 'code');
        $tasks = [];
        foreach ($rows as $row) {
            if ($row['has_error'] && $skipErrors) {
                continue;
            }
            $key = $this->normalizer->key(implode('|', [$row['activity'], $row['task'], $row['position'], $row['location']]));
            $tasks[$key] ??= [
                'activity_name' => $row['activity'], 'task_name' => $row['task'], 'routine_type' => $row['routine_type'],
                'job_position_text' => $row['position'], 'specific_location' => $row['location'],
                'zero_exposure_justification' => (($row['women'] + $row['men'] + $row['other']) === 0) ? 'Sin exposición cuantificada en la matriz histórica importada.' : null,
                'exposures' => [], 'risks' => [],
            ];
            foreach (['women' => 'women', 'men' => 'men', 'other' => 'other_or_unspecified'] as $field => $code) {
                $tasks[$key]['exposures'][$code] = ['exposure_category_id' => $exposureIds[$code], 'count' => max($tasks[$key]['exposures'][$code]['count'] ?? 0, $row[$field])];
            }
            $assessment = ['phase' => 'current', 'method' => 'vep', 'probability' => $row['probability'], 'consequence' => $row['consequence'], 'instrument_date' => now()->toDateString(), 'source_payload' => $row['source_payload']];
            $controls = [];
            if (filled($row['control'])) {
                $controls[] = [
                    'control_stage' => 'proposed', 'hierarchy_type' => $row['hierarchy_type'], 'description' => $row['control'],
                    'responsible_text' => $row['responsible'], 'status' => 'pending', 'periodicity_type' => $row['periodicity'],
                    'creates_program_action' => true,
                ];
            }
            $tasks[$key]['risks'][] = [
                'risk_family_id' => $familyId, 'specific_risk_name' => $row['risk'], 'possible_harm' => $row['harm'], 'evaluation_method' => 'vep',
                'declared_controlled_status' => $row['controlled'] === true ? 'unverified_legacy' : 'not_controlled',
                'source_payload' => $row['source_payload'], 'source_row_number' => $row['row_number'],
                'hazard_factors' => [['category' => null, 'hazard_description' => $row['hazard'] ?: 'Peligro no especificado en origen', 'risk_factor_description' => $row['factor']]],
                'assessments' => [$assessment], 'controls' => $controls,
            ];
        }

        return [[
            'name' => 'Procesos importados', 'process_type' => 'operational',
            'observations' => 'Estructura normalizada desde libro histórico; revisar antes de enviar a aprobación.',
            'tasks' => array_values(array_map(function ($task) {
                $task['exposures'] = array_values($task['exposures']);

                return $task;
            }, $tasks)),
        ]];
    }

    /** @return array<string, mixed> */
    private function issue(string $sheet, int $row, ?string $column, mixed $raw, mixed $normalized, string $severity, string $code, string $message): array
    {
        return ['sheet_name' => $sheet, 'row_number' => $row, 'column_name' => $column, 'raw_value' => $this->normalizer->text($raw), 'normalized_value' => $this->normalizer->text($normalized), 'severity' => $severity, 'issue_code' => $code, 'message' => $message, 'resolution_status' => 'pending'];
    }

    private function assertSafeArchive(string $path, int $size): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'El XLSX no es un archivo ZIP válido.']);
        }
        $uncompressed = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $uncompressed += (int) ($zip->statIndex($index)['size'] ?? 0);
        }
        $zip->close();
        if ($uncompressed > max($size * 100, 200 * 1024 * 1024)) {
            throw ValidationException::withMessages(['file' => 'El XLSX posee una tasa de compresión insegura.']);
        }
    }
}
