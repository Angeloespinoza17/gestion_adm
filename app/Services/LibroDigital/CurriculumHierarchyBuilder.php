<?php

namespace App\Services\LibroDigital;

final class CurriculumHierarchyBuilder
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /**
     * @param  iterable<array{path:list<array<string,mixed>>,objective_count:int,available_count:int,unavailable_count:int}>  $rows
     * @return array<string, mixed>
     */
    public function build(iterable $rows, string $scope): array
    {
        $root = $this->node(
            id: 'catalog:'.$this->canonical->hash(['root' => true, 'scope' => $scope]),
            parentId: null,
            type: 'catalog',
            entityId: null,
            name: $scope === 'catalog' ? 'Catálogo curricular activo' : 'Resultados filtrados',
            shortName: null,
            code: null,
            description: null,
            depth: 0,
            path: [],
            metadata: ['scope' => $scope, 'filters' => []],
        );

        foreach ($rows as $row) {
            $objectiveCount = (int) ($row['objective_count'] ?? 0);
            $availableCount = (int) ($row['available_count'] ?? 0);
            $unavailableCount = (int) ($row['unavailable_count'] ?? 0);
            $this->increment($root, $objectiveCount, $availableCount, $unavailableCount);

            $current = &$root;
            $identityPath = [];
            foreach ($row['path'] ?? [] as $descriptor) {
                $type = (string) ($descriptor['type'] ?? 'curricular_group');
                $identityPath[] = [
                    'type' => $type,
                    'key' => $descriptor['key'] ?? null,
                ];
                $id = $type.':'.$this->canonical->hash($identityPath);
                if (! isset($current['_children'][$id])) {
                    $filters = (array) data_get($current, 'metadata.filters', []);
                    $filters[$type] = $descriptor['filter_value'] ?? $descriptor['key'] ?? null;
                    $path = [...$current['path'], [
                        'id' => $id,
                        'type' => $type,
                        'name' => (string) ($descriptor['name'] ?? 'Sin clasificación'),
                        'code' => $descriptor['code'] ?? null,
                    ]];
                    $metadata = [
                        ...(array) ($descriptor['metadata'] ?? []),
                        'filters' => $filters,
                    ];
                    $current['_children'][$id] = $this->node(
                        id: $id,
                        parentId: $current['id'],
                        type: $type,
                        entityId: $descriptor['entity_id'] ?? null,
                        name: (string) ($descriptor['name'] ?? 'Sin clasificación'),
                        shortName: $descriptor['short_name'] ?? null,
                        code: $descriptor['code'] ?? null,
                        description: $descriptor['description'] ?? null,
                        depth: (int) $current['depth'] + 1,
                        path: $path,
                        metadata: $metadata,
                    );
                }

                $current = &$current['_children'][$id];
                $this->increment($current, $objectiveCount, $availableCount, $unavailableCount);
            }
            unset($current);
        }

        return $this->finalize($root, null);
    }

    /** @return array<string, mixed>|null */
    public function find(array $root, string $id): ?array
    {
        if (($root['id'] ?? null) === $id) {
            return $root;
        }

        foreach ($root['children'] ?? [] as $child) {
            $found = $this->find($child, $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function rebase(array $root): array
    {
        return $this->rebaseNode($root, null, 0, null);
    }

    /** @return array<string, mixed> */
    public function limitDepth(array $root, int $maxDepth): array
    {
        return $this->limitNode($root, max(0, $maxDepth));
    }

    public function containsType(array $root, string $type): bool
    {
        if (($root['type'] ?? null) === $type) {
            return true;
        }

        foreach ($root['children'] ?? [] as $child) {
            if ($this->containsType($child, $type)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function node(
        string $id,
        ?string $parentId,
        string $type,
        mixed $entityId,
        string $name,
        mixed $shortName,
        mixed $code,
        mixed $description,
        int $depth,
        array $path,
        array $metadata,
    ): array {
        return [
            'id' => $id,
            'entity_id' => $entityId,
            'parent_id' => $parentId,
            'type' => $type,
            'name' => $name,
            'short_name' => $shortName,
            'code' => $code,
            'description' => $description,
            'value' => 0,
            'objective_count' => 0,
            'available_count' => 0,
            'unavailable_count' => 0,
            'percentage_of_parent' => 0.0,
            'depth' => $depth,
            'has_children' => false,
            'children' => [],
            'path' => $path,
            'metadata' => $metadata,
            '_children' => [],
        ];
    }

    private function increment(array &$node, int $objectives, int $available, int $unavailable): void
    {
        $node['objective_count'] += $objectives;
        $node['available_count'] += $available;
        $node['unavailable_count'] += $unavailable;
        $node['value'] = $node['objective_count'];
    }

    /** @return array<string, mixed> */
    private function finalize(array $node, ?int $parentCount): array
    {
        $children = [];
        foreach (array_values($node['_children'] ?? []) as $child) {
            $children[] = $this->finalize($child, (int) $node['objective_count']);
        }
        unset($node['_children']);

        $node['children'] = $children;
        $node['has_children'] = $children !== [];
        $node['percentage_of_parent'] = $parentCount === null
            ? 100.0
            : ($parentCount > 0 ? round(((int) $node['objective_count'] / $parentCount) * 100, 2) : 0.0);
        $node['metadata']['child_count'] = count($children);

        return $node;
    }

    /** @return array<string, mixed> */
    private function rebaseNode(array $node, ?string $parentId, int $depth, ?int $parentCount): array
    {
        $node['parent_id'] = $parentId;
        $node['depth'] = $depth;
        $node['percentage_of_parent'] = $parentCount === null
            ? 100.0
            : ($parentCount > 0 ? round(((int) $node['objective_count'] / $parentCount) * 100, 2) : 0.0);
        $node['children'] = array_map(
            fn (array $child): array => $this->rebaseNode(
                $child,
                (string) $node['id'],
                $depth + 1,
                (int) $node['objective_count'],
            ),
            $node['children'] ?? [],
        );
        $node['has_children'] = $node['children'] !== [];
        $node['metadata']['child_count'] = count($node['children']);

        return $node;
    }

    /** @return array<string, mixed> */
    private function limitNode(array $node, int $maxDepth): array
    {
        $children = $node['children'] ?? [];
        if ((int) ($node['depth'] ?? 0) >= $maxDepth) {
            $hiddenChildCount = count($children);
            $node['children'] = [];
            $node['has_children'] = $hiddenChildCount > 0;
            $node['metadata']['child_count'] = $hiddenChildCount;
            $node['metadata']['visible_child_count'] = 0;
            $node['metadata']['children_truncated'] = $hiddenChildCount > 0;

            return $node;
        }

        $node['children'] = array_map(
            fn (array $child): array => $this->limitNode($child, $maxDepth),
            $children,
        );
        $node['has_children'] = $node['children'] !== [];
        $node['metadata']['child_count'] = count($node['children']);
        $node['metadata']['visible_child_count'] = count($node['children']);
        $node['metadata']['children_truncated'] = false;

        return $node;
    }
}
