<?php

namespace App\Growth\Assurance;

use App\Models\Project;
use App\Models\Release;

class ReleaseReadinessAssessor
{
    public function __construct(private readonly ReadinessGateEvaluator $readinessGateEvaluator) {}

    /**
     * @return array<string,mixed>
     */
    public function assess(Project $project, ?Release $release = null): array
    {
        $readiness = $this->readinessGateEvaluator->evaluate($project);
        $risks = $project->risks()
            ->where('probability', 'high')
            ->where('impact', 'high')
            ->whereNotIn('status', ['closed', 'accepted'])
            ->get(['id', 'title', 'status', 'mitigation_plan']);

        $deliveryLinks = $release
            ? $release->workItems()->with('deliveryLinks.checkRuns', 'deliveryLinks.deployments')->get()->flatMap->deliveryLinks
            : $project->workItems()->with('deliveryLinks.checkRuns', 'deliveryLinks.deployments')->get()->flatMap->deliveryLinks;

        $checks = $deliveryLinks->flatMap->checkRuns;
        $deployments = $deliveryLinks->flatMap->deployments->unique('id');

        $blockers = [];
        if ($readiness['status'] === 'fail') {
            $blockers[] = 'readiness_gates_failed';
        }
        if ($this->hasFinding($readiness, 'pmp.wbs.no_implementation_work')) {
            $blockers[] = 'no_implementation_work';
        }
        if ($deliveryLinks->isEmpty()) {
            $blockers[] = 'no_delivery_evidence';
        }
        if ($risks->isNotEmpty()) {
            $blockers[] = 'high_exposure_risks_open';
        }
        if ($checks->whereIn('conclusion', ['failure', 'timed_out', 'action_required'])->isNotEmpty()) {
            $blockers[] = 'failed_checks';
        }
        if ($release && $deployments->where('status', 'succeeded')->isEmpty()) {
            $blockers[] = 'no_successful_deployment';
        }

        return [
            'project_id' => $project->id,
            'release_id' => $release?->id,
            'release_version' => $release?->version,
            'status' => $blockers === [] ? ($readiness['status'] === 'warn' ? 'caution' : 'ready') : 'not_ready',
            'blockers' => $blockers,
            'readiness_status' => $readiness['status'],
            'risk_summary' => [
                'high_exposure_open' => $risks->count(),
                'unmitigated_high_exposure' => $risks->filter(fn ($risk) => trim((string) $risk->mitigation_plan) === '')->count(),
            ],
            'delivery_summary' => [
                'delivery_links' => $deliveryLinks->count(),
                'checks' => $checks->count(),
                'failed_checks' => $checks->whereIn('conclusion', ['failure', 'timed_out', 'action_required'])->count(),
                'successful_deployments' => $deployments->where('status', 'succeeded')->count(),
            ],
            'risks' => $risks->map(fn ($risk): array => [
                'id' => $risk->id,
                'title' => $risk->title,
                'status' => $risk->status,
                'mitigated' => trim((string) $risk->mitigation_plan) !== '',
            ])->all(),
        ];
    }

    /**
     * @param  array<string,mixed>  $readiness
     */
    private function hasFinding(array $readiness, string $rule): bool
    {
        return collect($readiness['gates'] ?? [])
            ->flatMap(fn (array $gate): array => $gate['findings'] ?? [])
            ->contains(fn (array $finding): bool => ($finding['rule'] ?? null) === $rule);
    }
}
