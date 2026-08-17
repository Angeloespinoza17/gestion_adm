<?php

namespace App\Services\LibroDigital;

final class CurriculumCorpusHasher
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /**
     * La identidad del catálogo es portable entre establecimientos. Excluye
     * filas, IDs internos, vínculos RBD/año, rutas y estado de cobertura.
     *
     * @param  array<string, mixed>  $catalog
     * @param  list<array<string, mixed>>  $sources
     * @param  list<array<string, mixed>>  $objectives
     * @param  list<array<string, mixed>>  $relationships
     * @return array<string, mixed>
     */
    public function payload(array $catalog, array $sources, array $objectives, array $relationships): array
    {
        return [
            'catalog' => $this->only($catalog, [
                'code', 'name', 'version', 'authority', 'effective_from', 'effective_to',
            ]),
            'sources' => collect($sources)
                ->map(fn (array $source): array => $this->only($source, [
                    'source_key', 'source_scope', 'source_name', 'authority', 'document_number',
                    'source_url', 'source_sha256', 'effective_from', 'effective_to',
                    'curriculum_track', 'subject_code', 'objective_type',
                ]))
                ->sortBy('source_key')->values()->all(),
            'objectives' => collect($objectives)
                ->map(fn (array $objective): array => $this->objective($objective))
                ->sortBy('objective_key')->values()->all(),
            'objective_sources' => collect($relationships)
                ->map(fn (array $relationship): array => $this->only($relationship, [
                    'objective_key', 'source_key', 'source_role', 'source_locator',
                ]))
                ->sortBy(fn (array $relationship): string => implode('|', [
                    $relationship['objective_key'] ?? '',
                    $relationship['source_role'] ?? '',
                    $relationship['source_key'] ?? '',
                    $relationship['source_locator'] ?? '',
                ]))->values()->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function hashPayload(array $payload): string
    {
        return $this->canonical->hash($this->payload(
            (array) ($payload['catalog'] ?? []),
            array_values((array) ($payload['sources'] ?? [])),
            array_values((array) ($payload['objectives'] ?? [])),
            array_values((array) ($payload['objective_sources'] ?? [])),
        ));
    }

    /** @param array<string, mixed> $objective @return array<string, mixed> */
    public function objective(array $objective): array
    {
        return $this->only($objective, [
            'code', 'objective_key', 'objective_type', 'subject_code', 'level_code',
            'grade_code', 'curriculum_track', 'axis_code', 'unit_code', 'description',
            'indicators', 'active', 'source_page',
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $value, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $value[$key] ?? null])->all();
    }
}
