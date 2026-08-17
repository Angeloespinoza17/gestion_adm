<?php

namespace App\Services\LibroDigital;

use SplQueue;

final class CurriculumGraphBuilder
{
    /** @return array{nodes:list<array<string,mixed>>,links:list<array<string,mixed>>,node_limit:int,total_nodes:int,truncated:bool} */
    public function build(array $root, int $nodeLimit): array
    {
        $nodeLimit = max(1, $nodeLimit);
        $totalNodes = $this->countNodes($root);
        $nodes = [];
        $links = [];
        $included = [];
        $queue = new SplQueue;
        $queue->enqueue($root);

        while (! $queue->isEmpty() && count($nodes) < $nodeLimit) {
            /** @var array<string, mixed> $node */
            $node = $queue->dequeue();
            $id = (string) $node['id'];
            $included[$id] = true;
            $nodes[] = [
                'id' => $id,
                'entity_id' => $node['entity_id'] ?? null,
                'name' => (string) ($node['name'] ?? ''),
                'code' => $node['code'] ?? null,
                'type' => (string) ($node['type'] ?? 'curricular_group'),
                'value' => (int) ($node['objective_count'] ?? 0),
                'depth' => (int) ($node['depth'] ?? 0),
                'category' => (string) ($node['type'] ?? 'curricular_group'),
                'has_children' => (bool) ($node['has_children'] ?? false),
                'metadata' => [
                    ...(array) ($node['metadata'] ?? []),
                    'available_count' => (int) ($node['available_count'] ?? 0),
                    'unavailable_count' => (int) ($node['unavailable_count'] ?? 0),
                    'path' => $node['path'] ?? [],
                ],
            ];

            foreach ($node['children'] ?? [] as $child) {
                if (count($nodes) + $queue->count() >= $nodeLimit) {
                    break;
                }
                $queue->enqueue($child);
            }
        }

        $this->collectLinks($root, $included, $links);

        return [
            'nodes' => $nodes,
            'links' => $links,
            'node_limit' => $nodeLimit,
            'total_nodes' => $totalNodes,
            'truncated' => $totalNodes > count($nodes),
        ];
    }

    private function countNodes(array $node): int
    {
        $count = 1;
        foreach ($node['children'] ?? [] as $child) {
            $count += $this->countNodes($child);
        }

        return $count;
    }

    /** @param array<string, bool> $included @param list<array<string, mixed>> $links */
    private function collectLinks(array $node, array $included, array &$links): void
    {
        $source = (string) ($node['id'] ?? '');
        if (! isset($included[$source])) {
            return;
        }

        foreach ($node['children'] ?? [] as $child) {
            $target = (string) ($child['id'] ?? '');
            if (! isset($included[$target])) {
                continue;
            }
            $links[] = [
                'source' => $source,
                'target' => $target,
                'value' => (int) ($child['objective_count'] ?? 0),
                'relation_type' => 'hierarchy',
                'is_official' => true,
            ];
            $this->collectLinks($child, $included, $links);
        }
    }
}
