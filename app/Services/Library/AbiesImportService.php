<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaCategoria;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaLectorTemporal;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaUbicacion;
use App\Support\Rut;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class AbiesImportService
{
    private const SOURCE = 'abies20';

    /**
     * @return array<string, mixed>
     */
    public function preview(AbiesMdbReader $reader): array
    {
        $works = $reader->table('Fondos');
        $copies = $reader->table('Ejemplares');
        $readers = $reader->table('Lectores');

        $workCodes = collect($works)->map(fn (array $row) => $this->text($row['CodigoLocal'] ?? null))->filter()->values();
        $workBarcodes = collect($works)->map(fn (array $row) => $this->text($row['CodigoExterno'] ?? null))->filter()->values();
        $copyCodes = collect($copies)->map(fn (array $row) => $this->text($row['CodigoEjemplar'] ?? null))->filter()->values();

        $workCodeCollisions = BibliotecaObra::query()
            ->where(function ($query) {
                $query->whereNull('source_system')->orWhere('source_system', '!=', self::SOURCE);
            })
            ->whereIn('internal_code', $workCodes)
            ->count();
        $workBarcodeCollisions = BibliotecaObra::query()
            ->where(function ($query) {
                $query->whereNull('source_system')->orWhere('source_system', '!=', self::SOURCE);
            })
            ->whereIn('barcode', $workBarcodes)
            ->count();
        $copyCodeCollisions = BibliotecaEjemplar::query()
            ->where(function ($query) {
                $query->whereNull('source_system')->orWhere('source_system', '!=', self::SOURCE);
            })
            ->where(function ($query) use ($copyCodes) {
                $query->whereIn('code', $copyCodes)->orWhereIn('barcode', $copyCodes);
            })
            ->count();

        $currentRuts = $this->currentRutMaps();
        $matchedReaders = collect($readers)->filter(function (array $row) use ($currentRuts) {
            $rut = Rut::normalize($this->text($row['DNI'] ?? null));

            return $rut && (isset($currentRuts['students'][$rut]) || isset($currentRuts['staff'][$rut]));
        })->count();

        return [
            'works' => count($works),
            'copies' => count($copies),
            'readers' => count($readers),
            'readers_matching_current_people' => $matchedReaders,
            'temporary_readers_to_create' => count($readers) - $matchedReaders,
            'active_legacy_loans' => $reader->exportableCount('Prestamos'),
            'work_code_collisions' => $workCodeCollisions,
            'work_barcode_collisions' => $workBarcodeCollisions,
            'copy_code_collisions' => $copyCodeCollisions,
            'existing_imported_works' => BibliotecaObra::query()->where('source_system', self::SOURCE)->count(),
            'existing_imported_copies' => BibliotecaEjemplar::query()->where('source_system', self::SOURCE)->count(),
            'existing_imported_temporary_readers' => BibliotecaLectorTemporal::query()->where('source_system', self::SOURCE)->count(),
        ];
    }

    /**
     * @param  callable(string, int, int):void|null  $progress
     * @return array<string, int>
     */
    public function import(
        AbiesMdbReader $reader,
        ?int $actorId = null,
        bool $importImages = true,
        ?callable $progress = null,
    ): array {
        $preview = $this->preview($reader);
        if ($preview['work_code_collisions'] > 0 || $preview['work_barcode_collisions'] > 0 || $preview['copy_code_collisions'] > 0) {
            throw new RuntimeException('La importación se detuvo porque existen colisiones de códigos con registros no provenientes de Abies.');
        }

        $source = $this->loadSource($reader);
        $summary = [
            'works_created' => 0,
            'works_updated' => 0,
            'copies_created' => 0,
            'copies_updated' => 0,
            'temporary_readers_created' => 0,
            'temporary_readers_updated' => 0,
            'readers_linked_to_current_people' => 0,
            'covers_imported' => 0,
            'covers_skipped' => 0,
            'covers_failed' => 0,
        ];

        DB::transaction(function () use ($source, $actorId, $progress, &$summary) {
            $categoryMap = $this->importCategories($source['work_cdus'], $actorId);
            $locationMap = $this->importLocations($source['locations'], $actorId);
            $copyCounts = collect($source['copies'])->countBy(fn (array $row) => $this->text($row['IdFondo'] ?? null));
            $loanableCounts = collect($source['copies'])
                ->filter(fn (array $row) => $this->copyIsLoanable($source['copy_types'][$this->text($row['IdTipoEjemplar'] ?? null)]['TipoEjemplar'] ?? null))
                ->countBy(fn (array $row) => $this->text($row['IdFondo'] ?? null));

            $workMap = [];
            $workTotal = count($source['works']);
            foreach ($source['works'] as $index => $row) {
                $sourceId = $this->requiredText($row, 'IdFondo', 'Fondo sin identificador.');
                $model = BibliotecaObra::query()->firstOrNew([
                    'source_system' => self::SOURCE,
                    'source_id' => $sourceId,
                ]);
                $created = ! $model->exists;
                $payload = $this->workPayload($row, $source, $categoryMap, $actorId);
                $payload['total_copies'] = (int) ($copyCounts[$sourceId] ?? 0);
                $payload['available_copies'] = (int) ($loanableCounts[$sourceId] ?? 0);
                $payload['loan_count'] = 0;
                $payload['general_status'] = 'disponible';
                if (! $created) {
                    $payload['cover_image_url'] = $model->cover_image_url;
                    $payload['created_by'] = $model->created_by;
                }
                $model->fill($payload)->save();
                $workMap[$sourceId] = $model->id;
                $summary[$created ? 'works_created' : 'works_updated']++;
                $progress?->__invoke('obras', $index + 1, $workTotal);
            }

            $copyTotal = count($source['copies']);
            foreach ($source['copies'] as $index => $row) {
                $sourceId = $this->requiredText($row, 'IdEjemplar', 'Ejemplar sin identificador.');
                $legacyWorkId = $this->requiredText($row, 'IdFondo', 'Ejemplar sin obra asociada.');
                if (! isset($workMap[$legacyWorkId])) {
                    throw new RuntimeException("El ejemplar Abies {$sourceId} apunta a una obra inexistente.");
                }

                $model = BibliotecaEjemplar::query()->firstOrNew([
                    'source_system' => self::SOURCE,
                    'source_id' => $sourceId,
                ]);
                $created = ! $model->exists;
                $model->fill($this->copyPayload($row, $source, $workMap[$legacyWorkId], $locationMap, $actorId));
                if (! $created) {
                    $model->created_by = $model->getOriginal('created_by');
                }
                $model->save();
                $summary[$created ? 'copies_created' : 'copies_updated']++;
                $progress?->__invoke('ejemplares', $index + 1, $copyTotal);
            }

            $this->importTemporaryReaders($source, $actorId, $summary, $progress);
        }, 3);

        if ($importImages) {
            $this->importCoverImages($reader, $summary, $progress);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSource(AbiesMdbReader $reader): array
    {
        $tables = [
            'works' => 'Fondos',
            'copies' => 'Ejemplares',
            'authors' => 'Autores',
            'publishers' => 'Editoriales',
            'work_authors' => 'Fondos_Autores',
            'functions' => 'Funciones',
            'fund_types' => 'TiposFondo',
            'languages' => 'Lenguas',
            'descriptors' => 'Descriptores',
            'work_descriptors' => 'Fondos_Descriptores',
            'applications' => 'Aplicaciones',
            'work_applications' => 'Fondos_Aplicaciones',
            'work_cdus' => 'Fondos_CDUs',
            'locations' => 'Ubicaciones',
            'origins' => 'Procedencias',
            'copy_types' => 'TiposEjemplar',
            'readers' => 'Lectores',
            'reader_types' => 'TiposLector',
            'courses' => 'Cursos',
        ];

        $source = [];
        foreach ($tables as $key => $table) {
            $source[$key] = $reader->table($table);
        }

        $source['authors'] = $this->index($source['authors'], 'IdAutor');
        $source['publishers'] = $this->index($source['publishers'], 'IdEditorial');
        $source['functions'] = $this->index($source['functions'], 'IdFuncion');
        $source['fund_types'] = $this->index($source['fund_types'], 'IdTipoFondo');
        $source['languages'] = $this->index($source['languages'], 'IdLengua');
        $source['descriptors'] = $this->index($source['descriptors'], 'IdDescriptor');
        $source['applications'] = $this->index($source['applications'], 'IdAplicacion');
        $source['origins'] = $this->index($source['origins'], 'IdProcedencia');
        $source['copy_types'] = $this->index($source['copy_types'], 'IdTipoEjemplar');
        $source['reader_types'] = $this->index($source['reader_types'], 'IdTipoLector');
        $source['courses'] = $this->index($source['courses'], 'IdCurso');
        $source['work_authors'] = $this->group($source['work_authors'], 'IdFondo');
        $source['work_descriptors'] = $this->group($source['work_descriptors'], 'IdFondo');
        $source['work_applications'] = $this->group($source['work_applications'], 'IdFondo');
        $source['work_cdus'] = $this->group($source['work_cdus'], 'IdFondo');

        return $source;
    }

    /**
     * @param  array<string, array<int, array<string, string|null>>>  $workCdus
     * @return array<string, int>
     */
    private function importCategories(array $workCdus, ?int $actorId): array
    {
        $definitions = $this->categoryDefinitions();
        $digits = collect($workCdus)
            ->flatten(1)
            ->map(fn (array $row) => $this->cduDigit($row['CDU'] ?? null))
            ->filter()
            ->unique()
            ->sort();
        $map = [];

        foreach ($digits as $digit) {
            $definition = $definitions[$digit];
            $category = BibliotecaCategoria::query()->updateOrCreate(
                ['code' => "CDU-{$digit}"],
                [
                    'name' => $definition['name'],
                    'slug' => Str::slug($definition['name']),
                    'color' => $definition['color'],
                    'description' => "Clasificación Decimal Universal {$digit}00.",
                    'sort_order' => (int) $digit,
                    'active' => true,
                    'updated_by' => $actorId,
                    'created_by' => $actorId,
                ]
            );
            $map[$digit] = $category->id;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, string|null>>  $locations
     * @return array<string, int>
     */
    private function importLocations(array $locations, ?int $actorId): array
    {
        $root = BibliotecaUbicacion::query()->updateOrCreate(
            ['code' => 'ABIES-ROOT'],
            [
                'parent_id' => null,
                'type' => 'sala',
                'name' => 'Biblioteca Abies',
                'audience_type' => 'mixta',
                'sort_order' => 900,
                'active' => true,
                'notes' => 'Ubicaciones importadas desde Abies 2.0.',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );
        $map = [];

        foreach ($locations as $index => $row) {
            $sourceId = $this->text($row['IdUbicacion'] ?? null);
            $name = $this->text($row['Ubicacion'] ?? null);
            if (! $sourceId || ! $name) {
                continue;
            }

            $location = BibliotecaUbicacion::query()
                ->where('parent_id', $root->id)
                ->where('type', 'estante')
                ->where('name', $name)
                ->first();

            if (! $location) {
                $location = BibliotecaUbicacion::query()->updateOrCreate(
                    ['code' => "ABIES-UBI-{$sourceId}"],
                    [
                        'parent_id' => $root->id,
                        'type' => 'estante',
                        'name' => $name,
                        'audience_type' => 'mixta',
                        'sort_order' => 1000 + $index,
                        'active' => true,
                        'notes' => "Ubicación Abies {$sourceId}.",
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );
            }
            $map[$sourceId] = $location->id;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, int>  $categoryMap
     * @return array<string, mixed>
     */
    private function workPayload(array $row, array $source, array $categoryMap, ?int $actorId): array
    {
        $sourceId = $this->requiredText($row, 'IdFondo', 'Fondo sin identificador.');
        $author = $source['authors'][$this->text($row['IdAutor'] ?? null)] ?? null;
        $publisher = $source['publishers'][$this->text($row['IdEditorial'] ?? null)] ?? null;
        $language = $source['languages'][$this->text($row['IdLenguaInfo'] ?? null)] ?? null;
        $cdus = collect($source['work_cdus'][$sourceId] ?? [])->pluck('CDU')->map(fn ($value) => $this->text($value))->filter()->values();
        $categoryDigit = $this->cduDigit($cdus->first());
        $descriptors = collect($source['work_descriptors'][$sourceId] ?? [])
            ->map(fn (array $link) => $source['descriptors'][$this->text($link['IdDescriptor'] ?? null)]['cDescriptor'] ?? null)
            ->map(fn ($value) => $this->text($value))
            ->filter()
            ->unique()
            ->values();
        $applications = collect($source['work_applications'][$sourceId] ?? [])
            ->map(fn (array $link) => $source['applications'][$this->text($link['IdAplicacion'] ?? null)]['Aplicacion'] ?? null)
            ->map(fn ($value) => $this->text($value))
            ->filter()
            ->unique()
            ->values();
        $secondaryAuthors = collect($source['work_authors'][$sourceId] ?? [])
            ->map(function (array $link) use ($source, $author) {
                $linkedAuthor = $source['authors'][$this->text($link['IdAutor'] ?? null)] ?? null;
                $name = $this->text($linkedAuthor['a'] ?? null);
                if (! $name || $name === $this->text($author['a'] ?? null)) {
                    return null;
                }
                $role = $this->text($source['functions'][$this->text($link['IdFuncion'] ?? null)]['Funcion'] ?? null);

                return $role ? Str::limit("{$name} ({$role})", 191, '') : $name;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
        $recommended = $applications->implode(' · ');
        $metadata = Arr::except($row, ['Foto']);
        $metadata['cdus'] = $cdus->all();
        $metadata['descriptors'] = $descriptors->all();
        $metadata['applications'] = $applications->all();
        $metadata['source'] = self::SOURCE;

        return [
            'material_type' => $this->materialType($row['IdTipoFondo'] ?? null, $source),
            'title' => $this->requiredText($row, 'Titulo', "La obra Abies {$sourceId} no tiene título."),
            'subtitle' => $this->text($row['Subtitulo'] ?? null),
            'main_author' => $this->text($author['a'] ?? null) ?: 'Autor no informado',
            'secondary_authors' => $secondaryAuthors,
            'publisher' => $this->text($publisher['Editorial'] ?? null),
            'publication_year' => $this->publicationYear($row['AnoEdicion'] ?? null),
            'isbn' => $this->isbn($row['ISBN2'] ?? null, $row['ISBN'] ?? null),
            'biblioteca_categoria_id' => $categoryDigit ? ($categoryMap[$categoryDigit] ?? null) : null,
            'category' => $categoryDigit ? ($this->categoryDefinitions()[$categoryDigit]['name'] ?? null) : null,
            'genre' => $descriptors->first(),
            'recommended_level' => $recommended !== '' ? Str::limit($recommended, 120, '') : null,
            'language' => $this->text($language['Lengua'] ?? null),
            'page_count' => $this->pageCount($row['Extension'] ?? null),
            'description' => $this->text($row['RestoPortada'] ?? null),
            'keywords' => $descriptors->map(fn (string $value) => Str::limit($value, 80, ''))->all(),
            'internal_code' => $this->requiredText($row, 'CodigoLocal', "La obra Abies {$sourceId} no tiene código local."),
            'barcode' => $this->text($row['CodigoExterno'] ?? null),
            'observations' => $this->workObservations($row),
            'source_metadata' => $metadata,
            'source_system' => self::SOURCE,
            'source_id' => $sourceId,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, int>  $locationMap
     * @return array<string, mixed>
     */
    private function copyPayload(array $row, array $source, int $workId, array $locationMap, ?int $actorId): array
    {
        $type = $this->text($source['copy_types'][$this->text($row['IdTipoEjemplar'] ?? null)]['TipoEjemplar'] ?? null) ?: 'Normal';
        $origin = $this->text($source['origins'][$this->text($row['IdProcedencia'] ?? null)]['Procedencia'] ?? null);
        $locationId = $this->text($row['IdUbicacion'] ?? null);
        $location = collect($source['locations'])->first(fn (array $item) => $this->text($item['IdUbicacion'] ?? null) === $locationId);
        $code = $this->requiredText($row, 'CodigoEjemplar', 'Ejemplar sin código.');

        return [
            'biblioteca_obra_id' => $workId,
            'code' => $code,
            'barcode' => $code,
            'legacy_registration_number' => $this->text($row['NumRegistro'] ?? null),
            'ingress_date' => $this->ingressDate($row['FechaAlta'] ?? null),
            'origin' => $this->origin($origin),
            'estimated_value' => $this->decimal($row['Importe'] ?? null),
            'biblioteca_ubicacion_id' => $locationId ? ($locationMap[$locationId] ?? null) : null,
            'physical_location' => $this->text($location['Ubicacion'] ?? null),
            'physical_state' => 'bueno',
            'availability_status' => 'disponible',
            'is_loanable' => $this->copyIsLoanable($type),
            'loan_restriction' => $this->loanRestriction($type),
            'source_system' => self::SOURCE,
            'source_id' => $this->requiredText($row, 'IdEjemplar', 'Ejemplar sin identificador.'),
            'source_metadata' => Arr::except($row, ['Foto']),
            'registered_by' => $actorId,
            'observations' => $this->copyObservations($row, $type, $origin),
            'photo_urls' => [],
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, int>  $summary
     * @param  callable(string, int, int):void|null  $progress
     */
    private function importTemporaryReaders(array $source, ?int $actorId, array &$summary, ?callable $progress): void
    {
        $rutMaps = $this->currentRutMaps();
        $total = count($source['readers']);

        foreach ($source['readers'] as $index => $row) {
            $sourceId = $this->requiredText($row, 'IdLector', 'Lector sin identificador.');
            $rut = Rut::normalize($this->text($row['DNI'] ?? null));
            $studentId = $rut ? ($rutMaps['students'][$rut] ?? null) : null;
            $staffId = $rut ? ($rutMaps['staff'][$rut] ?? null) : null;
            $existing = BibliotecaLectorTemporal::query()
                ->where('source_system', self::SOURCE)
                ->where('source_id', $sourceId)
                ->first();

            if ($studentId || $staffId) {
                $summary['readers_linked_to_current_people']++;
                if ($existing) {
                    $existing->update([
                        'active' => false,
                        'linked_student_profile_id' => $studentId,
                        'linked_staff_id' => $staffId,
                        'updated_by' => $actorId,
                    ]);
                }
                $progress?->__invoke('lectores', $index + 1, $total);

                continue;
            }

            $type = $this->text($source['reader_types'][$this->text($row['IdTipoLector'] ?? null)]['TipoLector'] ?? null);
            $course = $this->text($source['courses'][$this->text($row['IdCurso'] ?? null)]['Curso'] ?? null);
            $model = $existing ?: new BibliotecaLectorTemporal([
                'source_system' => self::SOURCE,
                'source_id' => $sourceId,
            ]);
            $created = ! $model->exists;
            $model->fill([
                'full_name' => trim(implode(' ', array_filter([
                    $this->text($row['Nombre'] ?? null),
                    $this->text($row['Apellidos'] ?? null),
                ]))) ?: "Lector Abies {$sourceId}",
                'rut' => $rut,
                'person_category' => $this->readerCategory($type),
                'email' => $this->text($row['Email'] ?? null),
                'phone' => $this->text($row['Telefono'] ?? null),
                'course_name' => $course,
                'notes' => $this->text($row['Notas'] ?? null),
                'active' => true,
                'linked_student_profile_id' => null,
                'linked_staff_id' => null,
                'source_system' => self::SOURCE,
                'source_id' => $sourceId,
                'source_metadata' => Arr::except($row, ['Foto']),
                'created_by' => $created ? $actorId : $model->created_by,
                'updated_by' => $actorId,
            ])->save();
            $summary[$created ? 'temporary_readers_created' : 'temporary_readers_updated']++;
            $progress?->__invoke('lectores', $index + 1, $total);
        }
    }

    /**
     * @param  array<string, int>  $summary
     * @param  callable(string, int, int):void|null  $progress
     */
    private function importCoverImages(AbiesMdbReader $reader, array &$summary, ?callable $progress): void
    {
        $directory = storage_path('app/public/biblioteca/abies/covers');
        File::ensureDirectoryExists($directory);
        $total = BibliotecaObra::query()->where('source_system', self::SOURCE)->count();
        $index = 0;

        foreach ($reader->rows('Fondos', 'raw') as $row) {
            $index++;
            $sourceId = $this->text($row['IdFondo'] ?? null);
            $binary = $row['Foto'] ?? null;
            if (! $sourceId || ! is_string($binary) || $binary === '') {
                $summary['covers_skipped']++;
                $progress?->__invoke('portadas', $index, $total);

                continue;
            }

            $relativePath = "biblioteca/abies/covers/{$sourceId}.webp";
            $absolutePath = storage_path('app/public/'.$relativePath);
            if (is_file($absolutePath)) {
                $summary['covers_skipped']++;
            } else {
                $image = @imagecreatefromstring($binary);
                if ($image === false || ! imagewebp($image, $absolutePath, 82)) {
                    if ($image !== false) {
                        imagedestroy($image);
                    }
                    $summary['covers_failed']++;
                    $progress?->__invoke('portadas', $index, $total);

                    continue;
                }
                imagedestroy($image);
                $summary['covers_imported']++;
            }

            BibliotecaObra::query()
                ->where('source_system', self::SOURCE)
                ->where('source_id', $sourceId)
                ->update(['cover_image_url' => '/storage/'.$relativePath]);
            $progress?->__invoke('portadas', $index, $total);
        }
    }

    /** @return array<string, array<string, int>> */
    private function currentRutMaps(): array
    {
        $students = [];
        foreach (DB::table('student_profiles')->whereNotNull('rut')->pluck('id', 'rut') as $rut => $id) {
            if ($normalized = Rut::normalize((string) $rut)) {
                $students[$normalized] = (int) $id;
            }
        }
        $staff = [];
        foreach (DB::table('staff')->whereNotNull('rut')->pluck('id', 'rut') as $rut => $id) {
            if ($normalized = Rut::normalize((string) $rut)) {
                $staff[$normalized] = (int) $id;
            }
        }

        return compact('students', 'staff');
    }

    /** @param array<int, array<string, string|null>> $rows */
    private function index(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if ($value = $this->text($row[$key] ?? null)) {
                $indexed[$value] = $row;
            }
        }

        return $indexed;
    }

    /** @param array<int, array<string, string|null>> $rows */
    private function group(array $rows, string $key): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            if ($value = $this->text($row[$key] ?? null)) {
                $grouped[$value][] = $row;
            }
        }

        return $grouped;
    }

    private function materialType(?string $typeId, array $source): string
    {
        $value = Str::lower($this->text($source['fund_types'][$this->text($typeId)]['TipoFondo'] ?? null) ?? '');

        if (str_contains($value, 'diccionario')) {
            return 'diccionario';
        }
        if (Str::contains($value, ['audio', 'video', 'dvd', 'grabación', 'grabacion'])) {
            return 'audiovisual';
        }
        if (Str::contains($value, ['concreto', 'mapa', 'lámina', 'lamina', 'compás', 'compas', 'escuadra', 'lupa', 'laboratorio'])) {
            return 'material_didactico';
        }
        if (Str::contains($value, ['libro', 'impreso', 'revista', 'fotocopia'])) {
            return 'libro';
        }

        return 'otro';
    }

    private function copyIsLoanable(?string $type): bool
    {
        return ! str_contains(Str::lower((string) $type), 'no prestable');
    }

    private function loanRestriction(?string $type): string
    {
        $normalized = Str::lower((string) $type);

        return match (true) {
            str_contains($normalized, 'no prestable') => 'no_prestable',
            str_contains($normalized, 'restring') => 'restringido',
            default => 'normal',
        };
    }

    private function origin(?string $value): string
    {
        $normalized = Str::lower((string) $value);

        return match (true) {
            str_contains($normalized, 'donad') => 'donacion',
            Str::contains($normalized, ['pérdida', 'perdida', 'reposición', 'reposicion', 'devolución', 'devolucion']) => 'reposicion',
            default => 'inventario_inicial',
        };
    }

    private function readerCategory(?string $type): string
    {
        $normalized = Str::lower(Str::ascii((string) $type));

        return match (true) {
            str_contains($normalized, 'alumn') => 'exalumno',
            str_contains($normalized, 'apoder') => 'apoderado',
            str_contains($normalized, 'practic') => 'practicante',
            str_contains($normalized, 'visit') => 'visitante',
            Str::contains($normalized, ['prof', 'docent', 'inspect', 'admin', 'asistente', 'secret', 'utp', 'sicolog', 'psicolog', 'fonoaudi', 'religios']) => 'exfuncionario',
            default => 'otro',
        };
    }

    private function publicationYear(?string $value): ?int
    {
        $value = $this->text($value);
        if (! $value || ! ctype_digit($value)) {
            return null;
        }
        $year = (int) $value;

        return $year >= 1000 && $year <= now()->year + 1 ? $year : null;
    }

    private function ingressDate(?string $value): ?string
    {
        $value = $this->text($value);
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        if ($date->year < 1900 || $date->year > now()->year + 1) {
            return null;
        }

        return $date->toDateString();
    }

    private function isbn(?string $preferred, ?string $fallback): ?string
    {
        foreach ([$preferred, $fallback] as $value) {
            $normalized = mb_strtoupper((string) preg_replace('/[^0-9Xx]/', '', (string) $value));
            if (in_array(strlen($normalized), [10, 13], true)) {
                return $normalized;
            }
        }

        return null;
    }

    private function pageCount(?string $value): ?int
    {
        if (! preg_match('/(\d{1,4})\s*(?:p\.?|pág)/ui', (string) $value, $matches)) {
            return null;
        }
        $pages = (int) $matches[1];

        return $pages >= 1 && $pages <= 5000 ? $pages : null;
    }

    private function decimal(?string $value): ?float
    {
        $value = $this->text($value);
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return max(0, (float) $value);
    }

    private function workObservations(array $row): ?string
    {
        $parts = [];
        foreach ([
            'Edicion' => 'Edición',
            'LugarEdicion' => 'Lugar de edición',
            'Extension' => 'Extensión',
            'CaracteristicasFisicas' => 'Características físicas',
            'Dimensiones' => 'Dimensiones',
            'Serie' => 'Serie',
            'NumeroSerie' => 'Número de serie',
            'DepositoLegal' => 'Depósito legal',
            'Notas' => 'Notas Abies',
        ] as $key => $label) {
            if ($value = $this->text($row[$key] ?? null)) {
                $parts[] = "{$label}: {$value}";
            }
        }

        return $parts ? implode(PHP_EOL, $parts) : null;
    }

    private function copyObservations(array $row, string $type, ?string $origin): ?string
    {
        $parts = ["Tipo de ejemplar Abies: {$type}"];
        if ($origin) {
            $parts[] = "Procedencia Abies: {$origin}";
        }
        $signature = collect(['Sig1', 'Sig2', 'Sig3', 'Sig4'])
            ->map(fn (string $key) => $this->text($row[$key] ?? null))
            ->filter()
            ->implode(' ');
        if ($signature !== '') {
            $parts[] = "Signatura: {$signature}";
        }
        if ($notes = $this->text($row['Notas'] ?? null)) {
            $parts[] = $notes;
        }

        return implode(PHP_EOL, $parts);
    }

    private function cduDigit(?string $value): ?string
    {
        return preg_match('/^\s*([0-9])/', (string) $value, $matches) ? $matches[1] : null;
    }

    /** @return array<string, array{name:string,color:string}> */
    private function categoryDefinitions(): array
    {
        return [
            '0' => ['name' => 'Generalidades, información y documentación', 'color' => '#6c757d'],
            '1' => ['name' => 'Filosofía y psicología', 'color' => '#7952b3'],
            '2' => ['name' => 'Religión y teología', 'color' => '#d63384'],
            '3' => ['name' => 'Ciencias sociales', 'color' => '#0d6efd'],
            '4' => ['name' => 'Clasificación CDU 4', 'color' => '#adb5bd'],
            '5' => ['name' => 'Matemáticas y ciencias naturales', 'color' => '#198754'],
            '6' => ['name' => 'Ciencias aplicadas, medicina y tecnología', 'color' => '#20c997'],
            '7' => ['name' => 'Artes, entretenimiento y deportes', 'color' => '#fd7e14'],
            '8' => ['name' => 'Lengua y literatura', 'color' => '#dc3545'],
            '9' => ['name' => 'Geografía, biografía e historia', 'color' => '#ffc107'],
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function requiredText(array $row, string $key, string $message): string
    {
        return $this->text($row[$key] ?? null) ?? throw new RuntimeException($message);
    }
}
