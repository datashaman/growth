<?php

namespace App\Support;

use App\Models\DesignElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ArchitectureDiagram
{
    /**
     * @param  Collection<int, DesignElement>  $elements
     * @return array{
     *     width:int,
     *     height:int,
     *     node_width:int,
     *     node_height:int,
     *     nodes:list<array{key:string,x:int,y:int,center_x:float,center_y:float,element:DesignElement}>,
     *     relationships:list<array{element:DesignElement,from:mixed,to:mixed,from_position:array{x:int,y:int,center_x:float,center_y:float},to_position:array{x:int,y:int,center_x:float,center_y:float}}>,
     *     unmatched_relationships:list<array{element:DesignElement,from:mixed,to:mixed}>
     * }
     */
    public static function fromElements(Collection $elements): array
    {
        $nodeWidth = 200;
        $nodeHeight = 104;
        $nodeGapX = 120;
        $nodeGapY = 72;
        $nodeInset = 40;

        $entities = $elements->where('kind', 'entity')->values();
        $relationships = $elements->where('kind', 'relationship')->values();
        $nodes = [];

        foreach ($entities as $index => $entity) {
            $key = self::key($entity->name);

            if ($key === '') {
                $key = "node-{$index}";
            }

            $nodes[$key] = [
                'key' => $key,
                'index' => $index,
                'layer' => 0,
                'element' => $entity,
            ];
        }

        $edges = [];
        $unmatchedRelationships = [];
        $nodeReferences = self::nodeReferences($nodes);
        $incoming = array_fill_keys(array_keys($nodes), 0);
        $adjacent = array_fill_keys(array_keys($nodes), []);

        foreach ($relationships as $relationship) {
            $from = data_get($relationship->properties, 'from')
                ?? data_get($relationship->properties, 'source')
                ?? data_get($relationship->properties, 'source_id');
            $to = data_get($relationship->properties, 'to')
                ?? data_get($relationship->properties, 'target')
                ?? data_get($relationship->properties, 'target_id');
            $fromKey = $nodeReferences[self::key($from)] ?? '';
            $toKey = $nodeReferences[self::key($to)] ?? '';

            if ($fromKey !== '' && $toKey !== '' && isset($nodes[$fromKey], $nodes[$toKey])) {
                $edges[] = [
                    'element' => $relationship,
                    'from' => $from,
                    'to' => $to,
                    'from_key' => $fromKey,
                    'to_key' => $toKey,
                ];

                $incoming[$toKey]++;
                $adjacent[$fromKey][] = $toKey;
            } else {
                $unmatchedRelationships[] = [
                    'element' => $relationship,
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        $remainingIncoming = $incoming;
        $queue = collect(array_keys($nodes))
            ->filter(fn (string $key): bool => $incoming[$key] === 0)
            ->sortBy(fn (string $key): int => $nodes[$key]['index'])
            ->values()
            ->all();
        $visited = [];

        while ($queue !== []) {
            $key = array_shift($queue);
            $visited[$key] = true;

            foreach ($adjacent[$key] as $targetKey) {
                $nodes[$targetKey]['layer'] = max($nodes[$targetKey]['layer'], $nodes[$key]['layer'] + 1);
                $remainingIncoming[$targetKey]--;

                if ($remainingIncoming[$targetKey] === 0) {
                    $queue[] = $targetKey;
                    usort($queue, fn (string $left, string $right): int => $nodes[$left]['index'] <=> $nodes[$right]['index']);
                }
            }
        }

        foreach ($nodes as $key => $node) {
            if (! isset($visited[$key]) && $incoming[$key] === 0) {
                $nodes[$key]['layer'] = 0;
            }
        }

        $layers = collect($nodes)
            ->groupBy('layer')
            ->sortKeys()
            ->map(fn (Collection $layer): Collection => $layer->sortBy('index')->values());
        $layerIndexes = $layers->keys()->values();
        $layerColumn = $layerIndexes->flip();
        $maxRows = max(1, $layers->map->count()->max() ?? 1);
        $columnCount = max(1, $layers->count());
        $width = max(720, ($nodeInset * 2) + ($columnCount * $nodeWidth) + (($columnCount - 1) * $nodeGapX));
        $height = max(260, ($nodeInset * 2) + ($maxRows * $nodeHeight) + (($maxRows - 1) * $nodeGapY));
        $positionedNodes = [];
        $positionsByKey = [];

        foreach ($layers as $layer => $layerNodes) {
            $column = (int) $layerColumn[$layer];

            foreach ($layerNodes as $row => $node) {
                $x = $nodeInset + ($column * ($nodeWidth + $nodeGapX));
                $y = $nodeInset + ($row * ($nodeHeight + $nodeGapY));
                $position = [
                    'key' => $node['key'],
                    'x' => $x,
                    'y' => $y,
                    'center_x' => $x + ($nodeWidth / 2),
                    'center_y' => $y + ($nodeHeight / 2),
                    'element' => $node['element'],
                ];

                $positionsByKey[$node['key']] = $position;
                $positionedNodes[] = $position;
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'node_width' => $nodeWidth,
            'node_height' => $nodeHeight,
            'nodes' => $positionedNodes,
            'relationships' => array_map(fn (array $edge): array => [
                'element' => $edge['element'],
                'from' => $edge['from'],
                'to' => $edge['to'],
                'from_position' => $positionsByKey[$edge['from_key']],
                'to_position' => $positionsByKey[$edge['to_key']],
            ], $edges),
            'unmatched_relationships' => $unmatchedRelationships,
        ];
    }

    private static function key(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    /**
     * @param  array<string, array{element:DesignElement}>  $nodes
     * @return array<string, string>
     */
    private static function nodeReferences(array $nodes): array
    {
        $references = [];

        foreach ($nodes as $key => $node) {
            $references[$key] = $key;
            $references[self::key($node['element']->getKey())] = $key;
            $references[self::key($node['element']->name)] = $key;
        }

        return array_filter($references, fn (string $reference): bool => $reference !== '');
    }
}
