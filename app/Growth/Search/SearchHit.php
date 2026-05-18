<?php

namespace App\Growth\Search;

/**
 * A single workspace-search result — one artifact matched by the query.
 */
final class SearchHit
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $projectId,
        public readonly string $matchedField,
        public readonly ?string $route,
        public readonly int $score,
    ) {}

    /**
     * @return array{type: string, id: string, label: string, project_id: string|null, matched_field: string, route: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'project_id' => $this->projectId,
            'matched_field' => $this->matchedField,
            'route' => $this->route,
        ];
    }
}
