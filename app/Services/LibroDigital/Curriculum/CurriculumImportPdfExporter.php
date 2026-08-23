<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\ReportExport;
use App\Services\Attendance\AttendancePdfBuilder;
use App\Services\LibroDigital\CanonicalJson;

class CurriculumImportPdfExporter
{
    public function __construct(
        private readonly CurriculumProgramCatalogService $catalog,
        private readonly AttendancePdfBuilder $pdf,
        private readonly CanonicalJson $canonical,
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(ReportExport $export): array
    {
        $export->loadMissing(['school', 'academicYear', 'requester']);
        $program = $this->program((string) data_get($export->filters_snapshot, 'program_id'));
        $payload = $this->catalog->detail($program);
        $fingerprint = $this->canonical->hash([
            'program' => $program->only(['id', 'revision', 'status', 'updated_at']),
            'documents' => collect($payload['documents'])->pluck('sha256')->all(),
            'units' => collect($payload['units'])->map(fn (array $unit): array => ['id' => $unit['id'], 'hours' => $unit['hours'], 'objectives' => collect($unit['objectives'])->pluck('id')->all()])->all(),
        ]);

        return [
            'metadata' => $this->metadata($export, $payload),
            // Mantiene el contrato común de instantáneas de reportes; las
            // secciones definitivas se materializan desde el manifiesto.
            'sections' => [],
            'curriculum_program_manifest' => [
                'schema_version' => 1,
                'program_id' => $program->public_id,
                'revision' => (int) $program->revision,
                'fingerprint' => $fingerprint,
                'captured_at' => now('UTC')->toIso8601String(),
            ],
        ];
    }

    /** @param array<string,mixed> $snapshot @return array{0:string,1:string,2:string} */
    public function generate(ReportExport $export, array $snapshot): array
    {
        $manifest = (array) ($snapshot['curriculum_program_manifest'] ?? []);
        if ((int) ($manifest['schema_version'] ?? 0) !== 1) {
            throw new LibroDigitalException('La instantánea del programa es inválida.', 'LCD_CURRICULUM_PROGRAM_EXPORT_SNAPSHOT_INVALID', 409);
        }
        $program = $this->program((string) ($manifest['program_id'] ?? ''));
        $payload = $this->catalog->detail($program);
        $currentFingerprint = $this->canonical->hash([
            'program' => $program->only(['id', 'revision', 'status', 'updated_at']),
            'documents' => collect($payload['documents'])->pluck('sha256')->all(),
            'units' => collect($payload['units'])->map(fn (array $unit): array => ['id' => $unit['id'], 'hours' => $unit['hours'], 'objectives' => collect($unit['objectives'])->pluck('id')->all()])->all(),
        ]);
        if (! hash_equals((string) ($manifest['fingerprint'] ?? ''), $currentFingerprint)) {
            throw new LibroDigitalException('El programa cambió después de solicitar el PDF; genera una nueva exportación.', 'LCD_CURRICULUM_PROGRAM_EXPORT_SNAPSHOT_STALE', 409);
        }

        $unitFilter = (string) data_get($export->filters_snapshot, 'unit_id', '');
        $units = collect($payload['units'])->when($unitFilter !== '', fn ($items) => $items->where('id', $unitFilter))->values();
        if ($unitFilter !== '' && $units->isEmpty()) {
            throw new LibroDigitalException('La unidad seleccionada no pertenece al programa.', 'LCD_CURRICULUM_PROGRAM_EXPORT_UNIT_INVALID', 422);
        }
        $allUnits = collect($payload['units']);
        $objectives = collect($payload['objectives']);
        $oaCount = $objectives->where('type', 'OA')->count();
        $skillCount = $objectives->where('type', 'OAH')->count();
        $sections = [
            [
                'title' => 'Síntesis curricular',
                'layout' => 'curriculum_program_summary',
                'subtitle' => $this->plain(data_get($payload, 'education_level.name', $payload['grade_code'])),
                'status' => $program->status === 'published' ? 'Publicado' : 'Borrador',
                'description' => $this->plain((string) ($payload['description'] ?? 'Programa curricular institucional respaldado por una fuente ministerial verificable.')),
                'metrics' => [
                    ['label' => 'Semanas', 'value' => $payload['weeks'] ?? '-', 'detail' => 'planificación anual'],
                    ['label' => 'Horas pedagógicas', 'value' => $payload['hours'] ?? '-', 'detail' => 'carga total'],
                    ['label' => 'Unidades', 'value' => count($payload['units']), 'detail' => 'organización anual'],
                    ['label' => 'OA', 'value' => $oaCount, 'detail' => 'objetivos disciplinares'],
                    ['label' => 'OAH', 'value' => $skillCount, 'detail' => 'habilidades científicas'],
                    ['label' => 'Ejes', 'value' => count($payload['axes']), 'detail' => 'ámbitos curriculares'],
                ],
                'source' => $this->plain(implode(' - ', array_filter([
                    data_get($payload, 'version.issuing_authority', data_get($payload, 'documents.0.issuing_authority', 'Ministerio de Educación de Chile')),
                    data_get($payload, 'version.decree'),
                    data_get($payload, 'documents.0.edition'),
                    filled(data_get($payload, 'documents.0.page_count')) ? data_get($payload, 'documents.0.page_count').' páginas' : null,
                ]))),
            ],
            [
                'title' => 'Distribución curricular',
                'layout' => 'curriculum_program_dashboard',
                'subtitle' => $program->status === 'published'
                    ? 'Programa publicado con respaldo documental verificable'
                    : 'Borrador institucional pendiente de publicación',
                'hours' => $payload['charts']['hours_by_unit'],
                'axes' => [
                    'labels' => collect($payload['charts']['objectives_by_axis']['labels'] ?? [])->map(fn ($label): string => $this->axisLabel((string) $label))->all(),
                    'series' => $payload['charts']['objectives_by_axis']['series'] ?? [],
                ],
            ],
            [
                'title' => 'Mapa anual de unidades',
                'headers' => ['Unidad', 'Semestre', 'Horas', 'OA', 'Páginas fuente'],
                'rows' => $units->map(fn (array $unit): array => [
                    $unit['title'], $unit['semester'] ?: '-', $unit['hours'] ?? 'Sin horas', count($unit['objectives']),
                    $this->pageRange($unit['page_start'] ?? null, $unit['page_end'] ?? null),
                ])->all(),
            ],
            [
                'title' => 'Ejes curriculares',
                'headers' => ['Eje', 'OA relacionados'],
                'rows' => collect($payload['axes'])->map(fn (array $axis): array => [$this->axisLabel((string) $axis['name']), $axis['objective_count']])->all(),
            ],
            [
                'title' => 'Objetivos de Aprendizaje',
                'layout' => 'curriculum_objectives',
                'rows' => $objectives->map(function (array $objective) use ($allUnits, $payload): array {
                    $locator = $this->objectiveLocator($objective, $allUnits);

                    return [
                        'group' => $this->axisLabel((string) ($objective['axis_code'] ?: 'Sin eje informado')),
                        'code' => $objective['code'],
                        'type' => $objective['type'],
                        'axis' => $this->axisLabel((string) $objective['axis_code']),
                        'source_page' => $locator,
                        'description' => $this->plain((string) $objective['text']),
                        'sources' => [
                            $this->plain(data_get($payload, 'documents.0.title', 'Documento ministerial')).' - '.$locator,
                        ],
                    ];
                })->all(),
            ],
        ];
        foreach ($units as $unit) {
            $sections[] = [
                'title' => $this->plain((string) $unit['title']),
                'layout' => 'curriculum_unit_detail',
                'unit_code' => $unit['code'],
                'semester' => $unit['semester'],
                'hours' => $unit['hours'],
                'page_range' => $this->pageRange($unit['page_start'] ?? null, $unit['page_end'] ?? null),
                'purpose' => $this->plain((string) ($unit['purpose'] ?: 'No informado')),
                'objectives' => collect($unit['objectives'])->map(fn (array $objective): string => $objective['code'].' - '.$this->plain((string) $objective['text']))->all(),
                'prior_knowledge' => array_values(array_filter((array) ($unit['prior_knowledge'] ?? []))),
                'knowledge' => collect($unit['elements'])->where('type', 'knowledge')->pluck('content')->map(fn ($value): string => $this->plain((string) $value))->values()->all(),
                'skills' => collect($unit['skills'])->pluck('name')->map(fn ($value): string => $this->plain((string) $value))->all(),
                'attitudes' => collect($unit['attitudes'])->pluck('text')->map(fn ($value): string => $this->plain((string) $value))->all(),
                'keywords' => collect($unit['keywords'])->pluck('text')->map(fn ($value): string => $this->plain((string) $value))->all(),
            ];
        }
        $sections[] = [
            'title' => 'Fuentes ministeriales',
            'layout' => 'curriculum_sources',
            'rows' => collect($payload['documents'])->map(fn (array $document): array => [
                'title' => $this->plain((string) $document['title']),
                'type' => $document['type'],
                'edition' => $document['edition'] ?: '-',
                'decree' => $this->plain((string) ($document['decree'] ?: '-')),
                'page_count' => $document['page_count'],
                'sha256' => $document['sha256'],
                'official_url' => $document['official_url'] ?? null,
            ])->all(),
        ];
        $dashboard = [
            'branding' => [
                'organization_name' => $export->school->legal_name ?: $export->school->name,
                'report_label' => 'Programa curricular',
                'report_trace' => 'RBD '.$export->school->rbd.' · Exportación '.$export->public_id,
                'source_label' => 'PDF ministerial respaldado · huella '.substr((string) data_get($payload, 'documents.0.sha256', $export->source_snapshot_hash), 0, 16),
                'watermark' => $program->status === 'published' ? '' : 'BORRADOR - PROGRAMA CURRICULAR',
            ],
        ];

        $title = 'Programa curricular - '.data_get($payload, 'subject.name', $payload['name']).' - '.data_get($payload, 'education_level.name', $payload['grade_code']);

        return [$this->pdf->build($this->plain($title), $snapshot['metadata'], $sections, $dashboard), 'pdf', 'application/pdf'];
    }

    private function program(string $identifier): CurriculumProgram
    {
        $program = CurriculumProgram::query()->where(function ($query) use ($identifier): void {
            if (ctype_digit($identifier)) {
                $query->whereKey((int) $identifier)->orWhere('public_id', $identifier);
            } else {
                $query->where('public_id', $identifier);
            }
        })->first();
        if (! $program) {
            throw new LibroDigitalException('El programa curricular no existe.', 'LCD_CURRICULUM_PROGRAM_NOT_FOUND', 404);
        }

        return $program;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function metadata(ReportExport $export, array $payload): array
    {
        return [
            'periodo' => data_get($payload, 'documents.0.edition', data_get($payload, 'version.publication_year', 'Versión curricular')),
            'año académico' => $export->academicYear?->name ?? $export->academicYear?->year ?? $export->academic_year_id,
            'tipo de reporte' => 'Programa curricular institucional',
            'generado por' => $export->requester?->name ?? 'Sistema',
            'fecha' => now((string) ($export->school->timezone ?: config('libro_digital.timezone')))->format('d-m-Y H:i'),
            'filtros' => $this->plain(data_get($payload, 'subject.name', '-').' · '.data_get($payload, 'education_level.name', $payload['grade_code'])),
        ];
    }

    private function objectiveLocator(array $objective, $units): string
    {
        if (is_numeric($objective['source_page'] ?? null)) {
            return 'Página física '.(int) $objective['source_page'];
        }

        $locations = $units->filter(fn (array $unit): bool => collect($unit['objectives'])->contains('id', $objective['id']))
            ->map(fn (array $unit): string => $unit['code'].' (páginas '.$this->pageRange($unit['page_start'] ?? null, $unit['page_end'] ?? null).')')
            ->values();

        return $locations->isNotEmpty() ? $locations->implode('; ') : 'Documento ministerial completo';
    }

    private function pageRange(mixed $start, mixed $end): string
    {
        if (filled($start) && filled($end)) {
            return $start.'-'.$end;
        }

        return filled($start) ? (string) $start : (filled($end) ? (string) $end : 'Sin localizar');
    }

    private function axisLabel(string $value): string
    {
        $known = [
            'CIENCIAS_DE_LA_VIDA' => 'Ciencias de la Vida',
            'CIENCIAS_FISICAS_Y_QUIMICAS' => 'Ciencias Físicas y Químicas',
            'CIENCIAS_DE_LA_TIERRA_Y_EL_UNIVERSO' => 'Ciencias de la Tierra y el Universo',
            'OBSERVAR_Y_PREGUNTAR' => 'Observar y preguntar',
            'EXPERIMENTAR' => 'Experimentar',
            'ANALIZAR_LA_EVIDENCIA_Y_COMUNICAR' => 'Analizar la evidencia y comunicar',
        ];
        $key = mb_strtoupper(str_replace(' ', '_', trim($value)));
        if (isset($known[$key])) {
            return $known[$key];
        }

        $words = preg_split('/\s+/u', mb_strtolower(str_replace('_', ' ', $value))) ?: [$value];

        return collect($words)->map(fn (string $word, int $index): string => $index > 0 && in_array($word, ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'en'], true)
            ? $word
            : mb_convert_case($word, MB_CASE_TITLE, 'UTF-8'))->implode(' ');
    }

    private function plain(string $value): string
    {
        return str_replace(["\u{2014}", "\u{2013}", "\u{2011}"], '-', $value);
    }
}
