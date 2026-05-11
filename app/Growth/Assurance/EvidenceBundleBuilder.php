<?php

namespace App\Growth\Assurance;

use App\Models\Project;

class EvidenceBundleBuilder
{
    public function __construct(private readonly ReadinessGateEvaluator $readinessGateEvaluator) {}

    /**
     * @return array<string,mixed>
     */
    public function build(Project $project): array
    {
        $readiness = $this->readinessGateEvaluator->evaluate($project);

        return [
            'project_id' => $project->id,
            'project' => $project->name,
            'integrity_level' => $project->integrity_level,
            'readiness_status' => $readiness['status'],
            'resources' => [
                'index' => "growth://projects/{$project->id}",
                'capabilities' => "growth://projects/{$project->id}/capabilities",
                'architecture' => "growth://projects/{$project->id}/architecture",
                'verification' => "growth://projects/{$project->id}/verification",
                'plan' => "growth://projects/{$project->id}/plan",
                'reviews' => "growth://projects/{$project->id}/reviews",
                'changes' => "growth://projects/{$project->id}/changes",
                'sources' => "growth://projects/{$project->id}/sources",
            ],
            'counts' => [
                'requirements' => $project->requirements()->count(),
                'design_views' => $project->designViews()->count(),
                'test_plans' => $project->testPlans()->count(),
                'work_items' => $project->workItems()->count(),
                'risks' => $project->risks()->count(),
                'reviews' => $project->reviews()->count(),
                'change_requests' => $project->changeRequests()->count(),
                'releases' => $project->releases()->count(),
                'deployments' => $project->deployments()->count(),
                'sources' => $project->sources()->count(),
            ],
            'gates' => collect($readiness['gates'])
                ->map(fn (array $gate): array => collect($gate)->except('findings')->all())
                ->values()
                ->all(),
        ];
    }
}
