<?php

namespace Database\Seeders;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\Convivencia\ConvivenciaProtocolPartLink;
use App\Models\Convivencia\ConvivenciaProtocolStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ConvivenciaRice2026Seeder extends Seeder
{
    private const EXPECTED_PROTOCOLS = 17;

    private const EXPECTED_PARTS = 28;

    private const EXPECTED_STEPS = 101;

    private const EXPECTED_LINKS = 209;

    /** @var array<string, int> */
    private array $stats = [
        'parts_created' => 0,
        'parts_updated' => 0,
        'parts_unchanged' => 0,
        'protocols_created' => 0,
        'protocols_updated' => 0,
        'protocols_unchanged' => 0,
        'steps_created' => 0,
        'steps_updated' => 0,
        'steps_unchanged' => 0,
        'links_created' => 0,
        'links_updated' => 0,
        'links_unchanged' => 0,
    ];

    public function run(): void
    {
        $this->guardExecutionContext();
        $this->assertRequiredSchema();

        $rice = config('convivencia_rice_2026');
        $this->assertConfiguration($rice);
        $actor = $this->resolveActor();
        $catalogs = $this->catalogs();

        DB::transaction(function () use ($rice, $actor, $catalogs): void {
            [$parts, $changedPartIds] = $this->syncParts($rice['parts'], $actor);

            foreach (array_values($rice['protocols']) as $definition) {
                $this->syncProtocol(
                    $definition,
                    $parts,
                    $changedPartIds,
                    $rice,
                    $catalogs,
                    $actor,
                );
            }

            $this->assertPersistedIntegrity($rice);
        }, 3);

        $this->command?->info(sprintf(
            'RICE 2026 cargado: %d protocolos, %d partes, %d pasos y %d enlaces. Cambios: %d protocolos, %d partes, %d pasos, %d enlaces.',
            self::EXPECTED_PROTOCOLS,
            self::EXPECTED_PARTS,
            self::EXPECTED_STEPS,
            self::EXPECTED_LINKS,
            $this->stats['protocols_created'] + $this->stats['protocols_updated'],
            $this->stats['parts_created'] + $this->stats['parts_updated'],
            $this->stats['steps_created'] + $this->stats['steps_updated'],
            $this->stats['links_created'] + $this->stats['links_updated'],
        ));
    }

    private function guardExecutionContext(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(static::class.' solo puede ejecutarse con APP_ENV=local o durante pruebas automatizadas.');
        }

        if (app()->environment('local') && DB::connection()->getDatabaseName() !== 'gestion_adm') {
            throw new RuntimeException(sprintf(
                '%s solo puede ejecutarse localmente sobre la base gestion_adm; conexion actual: %s.',
                static::class,
                DB::connection()->getDatabaseName() ?: '<sin nombre>',
            ));
        }
    }

    private function assertRequiredSchema(): void
    {
        $requiredTables = [
            'users',
            'convivencia_catalog_items',
            'convivencia_protocols',
            'convivencia_protocol_steps',
            'convivencia_protocol_parts',
            'convivencia_protocol_part_links',
            'convivencia_status_logs',
        ];

        $missing = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missing !== []) {
            throw new RuntimeException('Faltan migraciones de Convivencia: '.implode(', ', $missing).'.');
        }

        $requiredColumns = [
            'convivencia_protocols' => ['code', 'revision', 'metadata'],
            'convivencia_protocol_steps' => ['code', 'deadline_value', 'completion_rule', 'metadata'],
            'convivencia_protocol_parts' => ['code', 'category', 'metadata', 'deleted_at'],
            'convivencia_protocol_part_links' => ['protocol_step_id', 'condition', 'configuration'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException("La tabla {$table} no tiene el esquema RICE requerido.");
            }
        }
    }

    /** @param mixed $rice */
    private function assertConfiguration($rice): void
    {
        if (! is_array($rice) || ! is_array($rice['parts'] ?? null) || ! is_array($rice['protocols'] ?? null)) {
            throw new RuntimeException('La configuracion convivencia_rice_2026 no contiene partes y protocolos validos.');
        }

        if (count($rice['parts']) !== self::EXPECTED_PARTS || count($rice['protocols']) !== self::EXPECTED_PROTOCOLS) {
            throw new RuntimeException('La configuracion RICE 2026 no coincide con la version aprobada (17 protocolos y 28 partes).');
        }

        $partCodes = [];
        foreach ($rice['parts'] as $key => $part) {
            $code = $part['code'] ?? $key;
            if (! is_string($code) || $code === '' || isset($partCodes[$code])) {
                throw new RuntimeException('La configuracion RICE contiene codigos de partes vacios o duplicados.');
            }
            $partCodes[$code] = true;
        }

        $protocolCodes = [];
        $stepCount = 0;
        $linkIdentities = [];
        foreach ($rice['protocols'] as $key => $protocol) {
            $protocolCode = $protocol['code'] ?? $key;
            if (! is_string($protocolCode) || $protocolCode === '' || isset($protocolCodes[$protocolCode])) {
                throw new RuntimeException('La configuracion RICE contiene codigos de protocolos vacios o duplicados.');
            }
            $protocolCodes[$protocolCode] = true;
            $assignedPartCodes = [];
            $stepCodes = [];

            foreach (array_values($protocol['steps'] ?? []) as $step) {
                $stepCode = $step['code'] ?? null;
                if (! is_string($stepCode) || $stepCode === '' || isset($stepCodes[$stepCode])) {
                    throw new RuntimeException("El protocolo {$protocolCode} contiene codigos de paso vacios o duplicados.");
                }
                $stepCodes[$stepCode] = true;
                $stepCount++;

                foreach (array_values($step['parts'] ?? []) as $link) {
                    $partCode = $this->ricePartCode($link);
                    $this->assertPartReference($protocolCode, $partCode, $partCodes);
                    $assignedPartCodes[$partCode] = true;
                    $identity = "{$protocolCode}|{$stepCode}|{$partCode}";
                    if (isset($linkIdentities[$identity])) {
                        throw new RuntimeException("La configuracion RICE repite el enlace {$identity}.");
                    }
                    $linkIdentities[$identity] = true;
                }
            }

            foreach (array_values($protocol['parts'] ?? []) as $link) {
                $partCode = $this->ricePartCode($link);
                $this->assertPartReference($protocolCode, $partCode, $partCodes);
                if (isset($assignedPartCodes[$partCode])) {
                    continue;
                }
                $identity = "{$protocolCode}|global|{$partCode}";
                if (isset($linkIdentities[$identity])) {
                    throw new RuntimeException("La configuracion RICE repite el enlace {$identity}.");
                }
                $linkIdentities[$identity] = true;
            }
        }

        if ($stepCount !== self::EXPECTED_STEPS || count($linkIdentities) !== self::EXPECTED_LINKS) {
            throw new RuntimeException(sprintf(
                'La configuracion RICE contiene %d pasos y %d enlaces; se esperaban %d y %d.',
                $stepCount,
                count($linkIdentities),
                self::EXPECTED_STEPS,
                self::EXPECTED_LINKS,
            ));
        }
    }

    /** @param array<string, bool> $partCodes */
    private function assertPartReference(string $protocolCode, ?string $partCode, array $partCodes): void
    {
        if (! $partCode || ! isset($partCodes[$partCode])) {
            throw new RuntimeException("El protocolo {$protocolCode} referencia una parte inexistente: ".($partCode ?: '<sin codigo>').'.');
        }
    }

    private function resolveActor(): User
    {
        $actor = User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first();

        $actor ??= User::query()->where('active', true)->orderBy('id')->first();

        if (! $actor) {
            throw new RuntimeException('No existe un usuario activo para registrar la auditoria de la carga RICE 2026.');
        }

        return $actor;
    }

    /** @return array<string, Collection<string, ConvivenciaCatalogItem>> */
    private function catalogs(): array
    {
        return [
            'protocol_type' => ConvivenciaCatalogItem::query()
                ->where('group', 'protocol_type')
                ->where('active', true)
                ->get()
                ->keyBy('code'),
            'criticality' => ConvivenciaCatalogItem::query()
                ->where('group', 'criticality')
                ->where('active', true)
                ->get()
                ->keyBy('code'),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array{0: Collection<string, ConvivenciaProtocolPart>, 1: array<int, true>}
     */
    private function syncParts(array $definitions, User $actor): array
    {
        $parts = collect();
        $changedPartIds = [];

        foreach ($definitions as $key => $definition) {
            $code = $definition['code'] ?? $key;
            $part = ConvivenciaProtocolPart::withTrashed()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();
            $created = ! $part;
            if ($part?->trashed()) {
                throw new RuntimeException(
                    "La parte RICE {$code} esta archivada. La carga se cancela para no revertir una decision administrativa."
                );
            }
            $part ??= new ConvivenciaProtocolPart;

            $part->fill($this->partAttributes($definition, $code));
            $changed = $created || $part->isDirty();
            if ($changed) {
                $part->updated_by = $actor->id;
                if ($created) {
                    $part->created_by = $actor->id;
                }
                $part->save();
                $changedPartIds[(int) $part->id] = true;
                $this->stats[$created ? 'parts_created' : 'parts_updated']++;
            } else {
                $this->stats['parts_unchanged']++;
            }

            $parts->put($code, $part);
        }

        return [$parts, $changedPartIds];
    }

    /** @param array<string, mixed> $definition */
    private function partAttributes(array $definition, string $code): array
    {
        return [
            'category' => $definition['category'],
            'code' => $code,
            'title' => $definition['title'],
            'description' => $definition['description'] ?? null,
            'instructions' => $definition['instructions'] ?? null,
            'responsible_label' => $definition['responsible_label'] ?? null,
            'population_scope' => $definition['population_scope'] ?? null,
            'legal_reference' => $definition['legal_reference'] ?? null,
            'deadline_value' => $definition['deadline_value'] ?? null,
            'deadline_unit' => $definition['deadline_unit'] ?? null,
            'deadline_anchor' => $definition['deadline_anchor'] ?? null,
            'requires_evidence' => (bool) ($definition['requires_evidence'] ?? false),
            'active' => (bool) ($definition['active'] ?? true),
            'is_sensitive' => (bool) ($definition['is_sensitive'] ?? true),
            'metadata' => array_merge([
                'source_document' => 'RICE 2026 (con ajuste)',
                'managed_definition' => true,
                'managed_by' => static::class,
            ], $definition['metadata'] ?? []),
        ];
    }

    /**
     * @param  Collection<string, ConvivenciaProtocolPart>  $parts
     * @param  array<int, true>  $changedPartIds
     * @param  array<string, mixed>  $rice
     * @param  array<string, Collection<string, ConvivenciaCatalogItem>>  $catalogs
     */
    private function syncProtocol(
        array $definition,
        Collection $parts,
        array $changedPartIds,
        array $rice,
        array $catalogs,
        User $actor,
    ): void {
        $payload = $this->riceProtocolPayload($definition, $parts, $rice, $catalogs);
        $protocol = ConvivenciaProtocol::withTrashed()
            ->where('code', $payload['code'])
            ->lockForUpdate()
            ->first();
        $created = ! $protocol;
        if ($protocol?->trashed()) {
            throw new RuntimeException(
                "El protocolo RICE {$payload['code']} esta archivado. La carga se cancela para no revertir una decision administrativa."
            );
        }
        $protocol ??= new ConvivenciaProtocol;
        $previousStatus = $protocol->status;

        $protocol->fill($this->protocolAttributes($payload));
        $definitionChanged = $created || $protocol->isDirty();

        if ($created) {
            $protocol->revision = 1;
            $protocol->created_by = $actor->id;
            $protocol->updated_by = $actor->id;
            $protocol->save();
        }

        [$steps, $stepsChanged] = $this->syncSteps($protocol, $payload['steps']);
        $linksChanged = $this->syncLinks($protocol, $payload, $steps);
        $usesChangedPart = collect($this->flattenLinkDefinitions($payload))
            ->contains(fn (array $link): bool => isset($changedPartIds[(int) $link['protocol_part_id']]));
        $definitionChanged = $definitionChanged || $stepsChanged || $linksChanged || $usesChangedPart;

        if (! $created && $definitionChanged) {
            $protocol->revision = ((int) $protocol->revision) + 1;
            $protocol->updated_by = $actor->id;
            $protocol->save();
        }

        if ($created || $definitionChanged) {
            $protocol->statusLogs()->create([
                'changed_by' => $actor->id,
                'previous_status' => $created ? null : $previousStatus,
                'new_status' => $protocol->status,
                'event_type' => $created ? 'created' : 'definition_updated',
                'comment' => $created
                    ? 'Definicion normativa RICE 2026 creada mediante carga segura.'
                    : 'Definicion normativa RICE 2026 sincronizada. Revision '.$protocol->revision.'.',
                'changed_at' => now(),
            ]);
            $this->stats[$created ? 'protocols_created' : 'protocols_updated']++;
        } else {
            $this->stats['protocols_unchanged']++;
        }
    }

    /** @param array<string, mixed> $payload */
    private function protocolAttributes(array $payload): array
    {
        return [
            'code' => $payload['code'],
            'version_label' => $payload['version_label'],
            'regulatory_source' => $payload['regulatory_source'],
            'education_scope' => $payload['education_scope'],
            'legal_reference' => $payload['legal_reference'],
            'source_reference' => $payload['source_reference'],
            'effective_from' => $payload['effective_from'],
            'effective_to' => $payload['effective_to'],
            'published_at' => $payload['published_at'],
            'metadata' => $payload['metadata'],
            'protocol_type_item_id' => $payload['protocol_type_item_id'],
            'criticality_item_id' => $payload['criticality_item_id'],
            'name' => $payload['name'],
            'type_label' => $payload['type_label'],
            'criticality_label' => $payload['criticality_label'],
            'description' => $payload['description'],
            'required_documents' => $payload['required_documents'],
            'safeguard_measures' => $payload['safeguard_measures'],
            'minimal_actions' => $payload['minimal_actions'],
            'default_due_days' => $payload['default_due_days'],
            'status' => $payload['status'],
            'is_sensitive' => $payload['is_sensitive'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array{0: Collection<string, ConvivenciaProtocolStep>, 1: bool}
     */
    private function syncSteps(ConvivenciaProtocol $protocol, array $definitions): array
    {
        $existing = $protocol->steps()->lockForUpdate()->get();
        $existingByCode = $existing->filter(fn ($step) => filled($step->code))->groupBy('code');
        foreach ($existingByCode as $code => $matches) {
            if ($matches->count() > 1) {
                throw new RuntimeException("El protocolo {$protocol->code} contiene pasos duplicados con codigo {$code}.");
            }
        }

        $matched = [];
        $resolved = [];
        foreach (array_values($definitions) as $index => $definition) {
            $order = (int) ($definition['step_order'] ?? ($index + 1));
            $step = $existingByCode->get($definition['code'])?->first();
            $step ??= $existing->first(fn ($candidate) => blank($candidate->code)
                && (int) $candidate->step_order === $order
                && ! isset($matched[(int) $candidate->id]));
            $step ??= new ConvivenciaProtocolStep(['protocol_id' => $protocol->id]);
            if ($step->exists) {
                $matched[(int) $step->id] = true;
            }
            $resolved[] = [$step, $this->stepAttributes($definition, $order)];
        }

        $unexpected = $existing->reject(fn ($step) => isset($matched[(int) $step->id]));
        if ($unexpected->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'El protocolo %s tiene %d pasos ajenos a la configuracion RICE; la carga se cancela para no eliminarlos.',
                $protocol->code,
                $unexpected->count(),
            ));
        }

        $orderChanged = collect($resolved)->contains(
            fn (array $item): bool => $item[0]->exists && (int) $item[0]->step_order !== (int) $item[1]['step_order'],
        );
        if ($orderChanged) {
            $temporaryOrder = max(1000, ((int) $existing->max('step_order')) + count($definitions) + 100);
            foreach ($existing->values() as $index => $step) {
                $step->forceFill(['step_order' => $temporaryOrder + $index])->save();
            }
        }

        $changed = false;
        $steps = collect();
        foreach ($resolved as [$step, $attributes]) {
            $created = ! $step->exists;
            $step->fill($attributes);
            if ($created || $step->isDirty()) {
                $step->save();
                $changed = true;
                $this->stats[$created ? 'steps_created' : 'steps_updated']++;
            } else {
                $this->stats['steps_unchanged']++;
            }
            $steps->put($step->code, $step);
        }

        return [$steps, $changed];
    }

    /** @param array<string, mixed> $definition */
    private function stepAttributes(array $definition, int $order): array
    {
        $deadlineValue = $definition['deadline_value'] ?? null;
        $deadlineUnit = $definition['deadline_unit'] ?? null;

        return [
            'step_order' => $order,
            'code' => $definition['code'],
            'stage_name' => $definition['stage_name'],
            'description' => $definition['description'] ?? null,
            'step_type' => $definition['step_type'] ?? 'gestion',
            'responsible_label' => $definition['responsible_label'] ?? null,
            'due_days' => in_array($deadlineUnit, ['calendar_days', 'business_days', 'school_days'], true)
                ? $deadlineValue
                : null,
            'deadline_value' => $deadlineValue,
            'deadline_unit' => $deadlineUnit ?? 'calendar_days',
            'deadline_anchor' => $definition['deadline_anchor'] ?? 'step_started',
            'can_extend' => (bool) ($definition['can_extend'] ?? false),
            'extension_value' => $definition['extension_value'] ?? null,
            'extension_unit' => $definition['extension_unit'] ?? null,
            'completion_rule' => $definition['completion_rule'] ?? null,
            'active' => (bool) ($definition['active'] ?? true),
            'metadata' => $definition['metadata'] ?? null,
            'required_documents' => $definition['required_documents'] ?? null,
            'minimal_actions' => $definition['minimal_actions'] ?? null,
            'safeguard_measures' => $definition['safeguard_measures'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<string, ConvivenciaProtocolStep>  $steps
     */
    private function syncLinks(ConvivenciaProtocol $protocol, array $payload, Collection $steps): bool
    {
        $definitions = $this->flattenLinkDefinitions($payload);
        $existing = $protocol->partLinks()->lockForUpdate()->get();
        $existingByIdentity = $existing->groupBy(fn (ConvivenciaProtocolPartLink $link): string => $this->linkIdentity(
            $link->protocol_step_id,
            $link->protocol_part_id,
        ));
        foreach ($existingByIdentity as $identity => $matches) {
            if ($matches->count() > 1) {
                throw new RuntimeException("El protocolo {$protocol->code} contiene enlaces duplicados ({$identity}).");
            }
        }

        $desired = collect($definitions)->map(function (array $definition) use ($steps): array {
            $step = filled($definition['step_code'] ?? null) ? $steps->get($definition['step_code']) : null;
            if (filled($definition['step_code'] ?? null) && ! $step) {
                throw new RuntimeException('No se pudo resolver el paso '.$definition['step_code'].' al crear sus enlaces.');
            }

            return [
                'protocol_step_id' => $step?->id,
                'protocol_part_id' => (int) $definition['protocol_part_id'],
                'sort_order' => (int) $definition['sort_order'],
                'is_required' => (bool) $definition['is_required'],
                'condition' => $definition['condition'] ?? null,
                'configuration' => $definition['configuration'] ?? null,
            ];
        });

        $desiredIdentities = $desired->map(fn (array $link): string => $this->linkIdentity(
            $link['protocol_step_id'],
            $link['protocol_part_id'],
        ));
        if ($desiredIdentities->unique()->count() !== $desiredIdentities->count()) {
            throw new RuntimeException("La configuracion del protocolo {$protocol->code} genera enlaces duplicados.");
        }

        $unexpected = $existingByIdentity->keys()->diff($desiredIdentities);
        if ($unexpected->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'El protocolo %s tiene %d enlaces ajenos a la configuracion RICE; la carga se cancela para no eliminarlos.',
                $protocol->code,
                $unexpected->count(),
            ));
        }

        $changed = false;
        foreach ($desired as $attributes) {
            $identity = $this->linkIdentity($attributes['protocol_step_id'], $attributes['protocol_part_id']);
            $link = $existingByIdentity->get($identity)?->first();
            $created = ! $link;
            $link ??= new ConvivenciaProtocolPartLink(['protocol_id' => $protocol->id]);
            $link->fill(['protocol_id' => $protocol->id] + $attributes);
            if ($created || $link->isDirty()) {
                $link->save();
                $changed = true;
                $this->stats[$created ? 'links_created' : 'links_updated']++;
            } else {
                $this->stats['links_unchanged']++;
            }
        }

        return $changed;
    }

    private function linkIdentity(?int $stepId, int $partId): string
    {
        return ($stepId ?: 'global').'|'.$partId;
    }

    /** @param array<string, mixed> $payload */
    private function flattenLinkDefinitions(array $payload): array
    {
        $links = array_values($payload['part_links'] ?? []);
        foreach (array_values($payload['steps'] ?? []) as $step) {
            foreach (array_values($step['part_links'] ?? []) as $link) {
                $links[] = $link + ['step_code' => $step['code']];
            }
        }

        return $links;
    }

    /**
     * @param  Collection<string, ConvivenciaProtocolPart>  $parts
     * @param  array<string, mixed>  $rice
     * @param  array<string, Collection<string, ConvivenciaCatalogItem>>  $catalogs
     */
    private function riceProtocolPayload(array $definition, Collection $parts, array $rice, array $catalogs): array
    {
        $stepPartCodes = collect($definition['steps'] ?? [])
            ->flatMap(fn (array $step) => $step['parts'] ?? [])
            ->map(fn ($link) => $this->ricePartCode($link))
            ->filter()
            ->unique()
            ->values();
        $globalPartDefinitions = collect($definition['parts'] ?? [])
            ->reject(fn ($link) => $stepPartCodes->contains($this->ricePartCode($link)))
            ->values()
            ->all();
        $protocolTypeCode = $definition['protocol_type_code'] ?? null;
        $criticalityCode = $definition['criticality_code'] ?? null;
        $protocolType = $protocolTypeCode ? $catalogs['protocol_type']->get($protocolTypeCode) : null;
        $criticality = $criticalityCode ? $catalogs['criticality']->get($criticalityCode) : null;

        $steps = collect($definition['steps'] ?? [])->values()->map(function (array $step, int $index) use ($parts): array {
            $deadline = $step['deadline'] ?? [];
            $extension = $step['extension'] ?? [];
            $responsibleRoles = array_values($step['responsible_roles'] ?? []);

            return [
                'step_order' => $index + 1,
                'code' => $step['code'],
                'stage_name' => $step['stage_name'],
                'description' => $step['description'] ?? null,
                'step_type' => $step['step_type'] ?? 'gestion',
                'responsible_label' => $responsibleRoles
                    ? implode(', ', array_map(fn (string $role): string => str_replace('_', ' ', $role), $responsibleRoles))
                    : null,
                'deadline_value' => $deadline['value'] ?? null,
                'deadline_unit' => $deadline['unit'] ?? null,
                'deadline_anchor' => $deadline['anchor'] ?? 'step_started',
                'can_extend' => (bool) ($extension['allowed'] ?? false),
                'extension_value' => $extension['value'] ?? null,
                'extension_unit' => $extension['unit'] ?? null,
                'completion_rule' => $step['completion_rule'] ?? null,
                'active' => (bool) ($step['active'] ?? true),
                'metadata' => array_merge($step['metadata'] ?? [], [
                    'responsible_roles' => $responsibleRoles,
                    'extension_requires_approval' => $extension['requires_approval'] ?? false,
                    'extension_requires_reason' => $extension['requires_reason'] ?? false,
                    'structured_documents' => array_values($step['documents'] ?? []),
                    'structured_actions' => array_values($step['actions'] ?? []),
                    'structured_safeguards' => array_values($step['safeguards'] ?? []),
                ]),
                'required_documents' => $this->formatRiceList($step['documents'] ?? []),
                'minimal_actions' => $this->formatRiceList($step['actions'] ?? []),
                'safeguard_measures' => $this->formatRiceList($step['safeguards'] ?? []),
                'part_links' => $this->resolveRicePartLinks($step['parts'] ?? [], $parts),
            ];
        })->all();
        $source = $rice['source'] ?? [];

        return [
            'code' => $definition['code'],
            'version_label' => $definition['version_label'] ?? 'RICE 2026 con ajuste',
            'regulatory_source' => $definition['regulatory_source'] ?? ($source['name'] ?? null),
            'education_scope' => $definition['education_scope'] ?? null,
            'legal_reference' => $definition['legal_reference'] ?? null,
            'source_reference' => $definition['source_reference'] ?? null,
            'effective_from' => $definition['effective_from'] ?? null,
            'effective_to' => $definition['effective_to'] ?? null,
            'published_at' => $definition['published_at'] ?? null,
            'metadata' => [
                'configuration_schema_version' => $rice['schema_version'] ?? 1,
                'source' => $source,
                'source_revision' => $definition['revision'] ?? 1,
                'review_required' => (bool) ($definition['review_required'] ?? false),
                'warnings' => array_values($definition['warnings'] ?? []),
                'global_warnings' => array_values($rice['global_warnings'] ?? []),
                'scope' => $definition['scope'] ?? [],
                'references' => array_values($definition['references'] ?? []),
                'transitions' => array_values($definition['transitions'] ?? []),
                'structured_documents' => array_values($definition['documents'] ?? []),
                'structured_actions' => array_values($definition['actions'] ?? []),
                'structured_safeguards' => array_values($definition['safeguards'] ?? []),
                'protocol_parts_summary' => array_values($definition['parts'] ?? []),
                'managed_definition' => true,
                'managed_by' => static::class,
            ],
            'protocol_type_item_id' => $protocolType?->id,
            'criticality_item_id' => $criticality?->id,
            'name' => $definition['name'],
            'type_label' => $definition['type_label'] ?? $protocolType?->name,
            'criticality_label' => $definition['criticality_label'] ?? $criticality?->name,
            'description' => $definition['description'] ?? null,
            'required_documents' => $this->formatRiceList($definition['documents'] ?? []),
            'safeguard_measures' => $this->formatRiceList($definition['safeguards'] ?? []),
            'minimal_actions' => $this->formatRiceList($definition['actions'] ?? []),
            'default_due_days' => $definition['default_due_days'] ?? null,
            'status' => $definition['status'] ?? 'borrador',
            'is_sensitive' => (bool) ($definition['is_sensitive'] ?? true),
            'steps' => $steps,
            'part_links' => $this->resolveRicePartLinks($globalPartDefinitions, $parts),
        ];
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $definitions
     * @param  Collection<string, ConvivenciaProtocolPart>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function resolveRicePartLinks(array $definitions, Collection $parts): array
    {
        return collect($definitions)->values()->map(function ($definition, int $index) use ($parts): array {
            $link = is_string($definition) ? ['code' => $definition] : $definition;
            $code = $this->ricePartCode($link);
            $part = $code ? $parts->get($code) : null;
            if (! $part) {
                throw new RuntimeException("La parte RICE {$code} no existe en la biblioteca configurada.");
            }

            return [
                'protocol_part_id' => $part->id,
                'is_required' => (bool) ($link['required'] ?? $link['is_required'] ?? true),
                'sort_order' => $index + 1,
                'condition' => $link['condition'] ?? null,
                'configuration' => $link['configuration'] ?? null,
            ];
        })->all();
    }

    private function ricePartCode($definition): ?string
    {
        return is_string($definition) ? $definition : ($definition['code'] ?? null);
    }

    /** @param array<int, string> $items */
    private function formatRiceList(array $items): ?string
    {
        $items = array_values(array_filter($items, fn ($item): bool => is_string($item) && trim($item) !== ''));

        return $items ? '- '.implode("\n- ", $items) : null;
    }

    /** @param array<string, mixed> $rice */
    private function assertPersistedIntegrity(array $rice): void
    {
        $protocolCodes = array_values(array_map(fn (array $protocol): string => $protocol['code'], $rice['protocols']));
        $partCodes = array_values(array_map(fn (array $part): string => $part['code'], $rice['parts']));
        $protocols = ConvivenciaProtocol::query()->whereIn('code', $protocolCodes)->get(['id', 'code']);
        $protocolIds = $protocols->pluck('id');

        $counts = [
            'protocolos' => $protocols->count(),
            'partes' => ConvivenciaProtocolPart::query()->whereIn('code', $partCodes)->count(),
            'pasos' => ConvivenciaProtocolStep::query()->whereIn('protocol_id', $protocolIds)->count(),
            'enlaces' => ConvivenciaProtocolPartLink::query()->whereIn('protocol_id', $protocolIds)->count(),
        ];
        $expected = [
            'protocolos' => self::EXPECTED_PROTOCOLS,
            'partes' => self::EXPECTED_PARTS,
            'pasos' => self::EXPECTED_STEPS,
            'enlaces' => self::EXPECTED_LINKS,
        ];
        if ($counts !== $expected) {
            throw new RuntimeException('La carga RICE no alcanzo los conteos esperados: '.json_encode($counts, JSON_UNESCAPED_UNICODE).'.');
        }

        $links = ConvivenciaProtocolPartLink::query()
            ->whereIn('protocol_id', $protocolIds)
            ->get(['protocol_id', 'protocol_step_id', 'protocol_part_id']);
        $duplicates = $links
            ->groupBy(fn (ConvivenciaProtocolPartLink $link): string => $link->protocol_id.'|'.($link->protocol_step_id ?: 'global').'|'.$link->protocol_part_id)
            ->filter(fn (Collection $group): bool => $group->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('La carga RICE genero enlaces duplicados; la transaccion fue revertida.');
        }
    }
}
