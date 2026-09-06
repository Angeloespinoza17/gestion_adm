<?php

namespace Database\Seeders;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ConvivenciaCaseCatalogSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $stats = [
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
    ];

    public function run(): void
    {
        $this->guardExecutionContext();

        if (! Schema::hasTable('convivencia_catalog_items')) {
            throw new RuntimeException('Falta la migración que crea convivencia_catalog_items.');
        }

        $definitions = config('convivencia_case_catalogs');
        if (! is_array($definitions) || empty($definitions['classification']) || empty($definitions['criticality'])) {
            throw new RuntimeException('La configuración convivencia_case_catalogs está incompleta.');
        }

        DB::transaction(function () use ($definitions): void {
            foreach (['case_type', 'classification', 'criticality'] as $group) {
                $this->syncGroup($group, $definitions[$group] ?? []);
            }

            $classificationIds = ConvivenciaCatalogItem::query()
                ->where('group', 'classification')
                ->whereIn('code', array_column($definitions['classification'], 'code'))
                ->pluck('id', 'code');

            foreach (array_values($definitions['subclassification'] ?? []) as $index => $definition) {
                $parentCode = $definition['parent'] ?? null;
                $parentId = $classificationIds->get($parentCode);
                if (! $parentId) {
                    throw new RuntimeException("No existe la clasificación padre {$parentCode}.");
                }

                $this->syncItem('subclassification', $definition, $index + 1, (int) $parentId);
            }
        }, 3);

        $this->command?->info(sprintf(
            'Catálogos de apertura de casos cargados. Creados: %d; actualizados: %d; sin cambios: %d.',
            $this->stats['created'],
            $this->stats['updated'],
            $this->stats['unchanged'],
        ));
    }

    private function guardExecutionContext(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(static::class.' solo puede ejecutarse con APP_ENV=local o durante pruebas automatizadas.');
        }

        if (app()->environment('local') && DB::connection()->getDatabaseName() !== 'gestion_adm') {
            throw new RuntimeException(sprintf(
                '%s solo puede ejecutarse localmente sobre la base gestion_adm; conexión actual: %s.',
                static::class,
                DB::connection()->getDatabaseName() ?: '<sin nombre>',
            ));
        }
    }

    /** @param array<int, array<string, mixed>> $definitions */
    private function syncGroup(string $group, array $definitions): void
    {
        foreach (array_values($definitions) as $index => $definition) {
            $this->syncItem($group, $definition, $index + 1);
        }
    }

    /** @param array<string, mixed> $definition */
    private function syncItem(string $group, array $definition, int $sortOrder, ?int $parentId = null): void
    {
        $code = trim((string) ($definition['code'] ?? ''));
        $name = trim((string) ($definition['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new RuntimeException("Ítem inválido en el catálogo {$group}.");
        }

        $metadata = $definition['metadata'] ?? [];
        if (! empty($definition['protocol_codes'])) {
            $metadata['rice_protocol_codes'] = array_values($definition['protocol_codes']);
        }
        $metadata['source'] = 'RICE 2026 (con ajuste)';

        $payload = [
            'parent_id' => $parentId,
            'name' => $name,
            'description' => $definition['description'] ?? null,
            'color' => $definition['color'] ?? null,
            'metadata' => $metadata,
            'sort_order' => $sortOrder,
            'active' => true,
        ];

        $item = ConvivenciaCatalogItem::query()->firstOrNew([
            'group' => $group,
            'code' => $code,
        ]);
        $isNew = ! $item->exists;
        $item->fill($payload);

        if (! $isNew && ! $item->isDirty()) {
            $this->stats['unchanged']++;

            return;
        }

        $item->save();
        $this->stats[$isNew ? 'created' : 'updated']++;
    }
}
