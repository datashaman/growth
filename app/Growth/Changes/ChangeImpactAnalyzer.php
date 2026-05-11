<?php

namespace App\Growth\Changes;

use App\Growth\Trace\TraceResolver;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use Illuminate\Database\Eloquent\Model;

class ChangeImpactAnalyzer
{
    public function __construct(private readonly TraceResolver $traceResolver) {}

    /**
     * @return array<string,mixed>
     */
    public function analyze(ChangeRequest $change, int $depth = 2): array
    {
        $impacts = $change->impacts()->get();
        $analyses = $impacts->map(fn (ChangeImpact $impact): array => $this->analyzeImpact($impact, $depth))->all();

        return [
            'change_request_id' => $change->id,
            'title' => $change->title,
            'status' => $change->status,
            'decision' => $change->decision,
            'summary' => [
                'impacts' => count($analyses),
                'needs_analysis' => count(array_filter($analyses, fn (array $row): bool => $row['impact_kind'] === 'needs_analysis')),
                'related_nodes' => array_sum(array_column($analyses, 'related_nodes')),
                'related_edges' => array_sum(array_column($analyses, 'related_edges')),
            ],
            'impacts' => $analyses,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function analyzeImpact(ChangeImpact $impact, int $depth): array
    {
        $artifact = $impact->impactable;
        $graph = $artifact instanceof Model
            ? $this->traceResolver->walk($artifact, $impact->impactable_type, $depth)
            : ['nodes' => [], 'edges' => []];

        return [
            'id' => $impact->id,
            'artifact_type' => $impact->impactable_type,
            'artifact_id' => $impact->impactable_id,
            'impact_kind' => $impact->impact_kind,
            'description' => $impact->description,
            'related_nodes' => count($graph['nodes']),
            'related_edges' => count($graph['edges']),
            'trace_nodes' => $graph['nodes'],
            'trace_edges' => $graph['edges'],
        ];
    }
}
