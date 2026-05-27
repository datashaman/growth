<?php

use App\Growth\Assurance\ReleaseReadinessAssessor;
use App\Growth\Manifest\ManifestApplier;
use App\Growth\Manifest\StarterTemplates;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\WorkspaceContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(WorkspaceContext::class)->set($this->user->active_workspace_id);
});

it('keeps checklist-only software projects blocked for missing implementation evidence', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'created_by_user_id' => $this->user->id,
        'name' => 'Checklist-only launch',
        'rigor_level' => 2,
    ]);
    $project->projectPlan()->create([
        'status' => 'draft',
        'scope_summary' => 'Release the vendor order workflow.',
        'approach' => 'Prepare the launch collateral and runbook.',
    ]);
    Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The platform shall let vendors manage orders.',
    ]);
    WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Write rollout runbook',
        'description' => 'Document the deployment checklist and support process.',
    ]);
    WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Prepare launch checklist',
        'description' => 'Capture go/no-go criteria for launch readiness.',
    ]);

    $assessment = app(ReleaseReadinessAssessor::class)->assess($project->fresh());

    expect($assessment['status'])->toBe('not_ready')
        ->and($assessment['blockers'])->toContain('no_implementation_work')
        ->and($assessment['blockers'])->toContain('no_delivery_evidence');
});

it('allows release readiness when implementation delivery evidence and deployment are clean', function () {
    $manifest = app(StarterTemplates::class)->template(2);
    $manifest['project']['name'] = 'Evidence-backed launch';

    $report = app(ManifestApplier::class)->apply($manifest, userId: $this->user->id);
    $project = Project::findOrFail($report['project_id']);
    $workItem = $project->workItems()->firstOrFail();
    $workItem->update(['status' => 'done']);

    $release = $project->releases()->create([
        'version' => '1.0.0',
        'status' => 'planned',
    ]);
    $release->workItems()->attach($workItem);

    $deliveryLink = $workItem->deliveryLinks()->create([
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/acme/product/pull/42',
        'description' => 'Implements the primary action.',
    ]);
    $deliveryLink->checkRuns()->create([
        'provider' => 'github',
        'name' => 'Feature tests',
        'run_ref' => 'run-42',
        'status' => 'completed',
        'conclusion' => 'success',
        'url' => 'https://github.com/acme/product/actions/runs/42',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);
    $deployment = $project->deployments()->create([
        'release_id' => $release->id,
        'environment' => 'production',
        'status' => 'succeeded',
        'deployed_at' => now(),
        'url' => 'https://product.example.com',
    ]);
    $deployment->deliveryLinks()->attach($deliveryLink);

    $assessment = app(ReleaseReadinessAssessor::class)->assess($project->fresh(), $release->fresh());

    expect($assessment['status'])->not->toBe('not_ready')
        ->and($assessment['blockers'])->toBe([])
        ->and($assessment['delivery_summary'])->toMatchArray([
            'delivery_links' => 1,
            'checks' => 1,
            'failed_checks' => 0,
            'successful_deployments' => 1,
        ]);
});
