<?php

namespace Database\Seeders;

use App\Models\Anomaly;
use App\Models\ChangeRequest;
use App\Models\CheckRunEvidence;
use App\Models\Concern;
use App\Models\Deployment;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Release;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\TestPlan;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(fn (User $user) => $this->seedForUser($user));
    }

    private function seedForUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->seedHealthyProject($user);
            $this->seedStrugglingProject($user);
        });
    }

    private function seedHealthyProject(User $user): Project
    {
        $project = Project::withoutGlobalScopes()
            ->firstOrCreate(
                ['workspace_id' => $user->active_workspace_id, 'name' => 'Demo: Orbit Telemetry'],
                ['description' => 'Reference project showing a healthy delivery cadence.', 'rigor_level' => 2, 'created_by_user_id' => $user->id],
            );

        if (! $project->wasRecentlyCreated && $project->workItems()->exists()) {
            return $project;
        }

        $stakeholders = [
            ['name' => 'Mission Director', 'role' => 'sponsor', 'kind' => 'individual'],
            ['name' => 'Ground Ops Lead', 'role' => 'operator', 'kind' => 'individual'],
            ['name' => 'Safety Officer', 'role' => 'regulator', 'kind' => 'individual'],
        ];
        foreach ($stakeholders as $s) {
            Stakeholder::create($s + ['project_id' => $project->id, 'description' => null]);
        }

        $engineering = Role::create([
            'project_id' => $project->id,
            'name' => 'Engineering Lead',
            'weekly_capacity_hours' => 32,
            'hourly_rate_amount' => 120,
            'rate_currency' => 'USD',
        ]);
        $ops = Role::create([
            'project_id' => $project->id,
            'name' => 'Ground Ops',
            'weekly_capacity_hours' => 24,
            'hourly_rate_amount' => 95,
            'rate_currency' => 'USD',
        ]);

        Concern::create(['project_id' => $project->id, 'text' => 'Continuous telemetry availability during burns.']);
        Concern::create(['project_id' => $project->id, 'text' => 'Bounded latency for command acknowledgements.']);

        foreach (range(1, 6) as $i) {
            Requirement::create([
                'project_id' => $project->id,
                'doc' => 'srs',
                'type' => 'functional',
                'text' => "Telemetry packet R-{$i} must be ingested within 250ms.",
                'priority' => 'high',
            ]);
        }

        DesignView::create(['project_id' => $project->id, 'viewpoint' => 'logical', 'name' => 'Telemetry pipeline (logical)']);
        DesignView::create(['project_id' => $project->id, 'viewpoint' => 'deployment', 'name' => 'Ground stations (deployment)']);

        TestPlan::create(['project_id' => $project->id, 'level' => 'system', 'name' => 'Telemetry ingest acceptance']);
        TestPlan::create(['project_id' => $project->id, 'level' => 'integration', 'name' => 'Ground link integration']);

        $statuses = ['done', 'done', 'done', 'in_progress', 'todo'];
        $stageItems = [];
        foreach ($statuses as $i => $status) {
            $stageItems[$i] = WorkItem::create([
                'project_id' => $project->id,
                'responsible_role_id' => $engineering->id,
                'kind' => 'task',
                'name' => 'Implement ingest stage '.($i + 1),
                'status' => $status,
                'planned_start_date' => now()->subDays(30 - $i * 5),
                'due_date' => now()->addDays(7 + $i * 3),
                'effort_estimate_hours' => 16,
                'effort_actual_hours' => $status === 'done' ? 14 : null,
            ]);
        }

        $failoverDrill = WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $ops->id,
            'kind' => 'task',
            'name' => 'Ground station failover drill',
            'status' => 'in_progress',
            'planned_start_date' => now()->subDays(20),
            'due_date' => now()->subDays(3),
            'effort_estimate_hours' => 12,
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Telemetry pipeline GA',
            'target_date' => now()->subDays(2),
            'status' => 'pending',
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Beta cutover',
            'target_date' => now()->addDays(21),
            'status' => 'pending',
        ]);

        Review::create([
            'project_id' => $project->id,
            'type' => 'technical_review',
            'title' => 'Pipeline architecture review',
            'status' => 'held',
            'held_at' => now()->subDays(5),
            'decision' => 'accepted',
        ]);

        ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Increase telemetry sample rate',
            'category' => 'requirements',
            'status' => 'approved',
            'priority' => 'medium',
            'decision' => 'approved',
            'decided_at' => now()->subDays(2),
        ]);

        $release = Release::create([
            'project_id' => $project->id,
            'version' => '0.1.0',
            'name' => 'Early integration',
            'status' => 'released',
            'released_at' => now()->subDays(7),
        ]);

        $deployment = Deployment::create([
            'project_id' => $project->id,
            'release_id' => $release->id,
            'environment' => 'staging',
            'status' => 'succeeded',
            'deployed_at' => now()->subDays(7),
        ]);

        // Done stages 0–2: PR + successful checks + attached to staging deployment → "deployed"
        foreach ([0, 1, 2] as $i) {
            $link = WorkItemDeliveryLink::create([
                'work_item_id' => $stageItems[$i]->id,
                'type' => 'pull_request',
                'ref' => 'PR-'.(120 + $i),
                'description' => 'Ingest stage '.($i + 1).' implementation.',
            ]);
            CheckRunEvidence::create([
                'work_item_delivery_link_id' => $link->id,
                'provider' => 'github',
                'name' => 'tests',
                'status' => 'completed',
                'conclusion' => 'success',
                'completed_at' => now()->subDays(8 - $i),
            ]);
            CheckRunEvidence::create([
                'work_item_delivery_link_id' => $link->id,
                'provider' => 'github',
                'name' => 'lint',
                'status' => 'completed',
                'conclusion' => 'success',
                'completed_at' => now()->subDays(8 - $i),
            ]);
            $deployment->deliveryLinks()->attach($link->id);
        }

        // In-progress stage 4 (idx 3): PR + passing checks, no deployment → "validated"
        $stage4Link = WorkItemDeliveryLink::create([
            'work_item_id' => $stageItems[3]->id,
            'type' => 'pull_request',
            'ref' => 'PR-123',
            'description' => 'Ingest stage 4 work-in-progress.',
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $stage4Link->id,
            'provider' => 'github',
            'name' => 'tests',
            'status' => 'completed',
            'conclusion' => 'success',
            'completed_at' => now()->subHours(6),
        ]);

        // Overdue failover drill: PR + one failing check → "blocked_by_checks"
        $drillLink = WorkItemDeliveryLink::create([
            'work_item_id' => $failoverDrill->id,
            'type' => 'pull_request',
            'ref' => 'PR-130',
            'description' => 'Failover drill harness.',
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $drillLink->id,
            'provider' => 'github',
            'name' => 'integration',
            'status' => 'completed',
            'conclusion' => 'failure',
            'completed_at' => now()->subDays(2),
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $drillLink->id,
            'provider' => 'github',
            'name' => 'tests',
            'status' => 'completed',
            'conclusion' => 'success',
            'completed_at' => now()->subDays(2),
        ]);

        Risk::create([
            'project_id' => $project->id,
            'owner_role_id' => $ops->id,
            'title' => 'Ground station bandwidth saturation',
            'description' => 'Peak telemetry bursts may exceed available downlink capacity.',
            'category' => 'technical',
            'probability' => 'medium',
            'impact' => 'high',
            'status' => 'mitigating',
            'mitigation_plan' => 'Add a second contact window and apply burst smoothing.',
        ]);

        Risk::create([
            'project_id' => $project->id,
            'owner_role_id' => $engineering->id,
            'title' => 'Late delivery of orbit refinement model',
            'description' => 'Dependency on external vendor.',
            'category' => 'schedule',
            'probability' => 'low',
            'impact' => 'medium',
            'status' => 'assessed',
        ]);

        Anomaly::create([
            'project_id' => $project->id,
            'severity' => 'medium',
            'status' => 'investigating',
            'summary' => 'Intermittent packet drops on staging telemetry feed',
            'description' => 'Roughly 2% packet loss observed during 30-minute burst.',
            'environment' => 'staging',
        ]);

        return $project;
    }

    private function seedStrugglingProject(User $user): Project
    {
        $project = Project::withoutGlobalScopes()
            ->firstOrCreate(
                ['workspace_id' => $user->active_workspace_id, 'name' => 'Demo: Legacy Migration'],
                ['description' => 'Reference project showing schedule and readiness pressure.', 'rigor_level' => 3, 'created_by_user_id' => $user->id],
            );

        if (! $project->wasRecentlyCreated && $project->workItems()->exists()) {
            return $project;
        }

        Stakeholder::create(['project_id' => $project->id, 'name' => 'Migration Sponsor', 'role' => 'sponsor', 'kind' => 'individual']);
        Stakeholder::create(['project_id' => $project->id, 'name' => 'Compliance Officer', 'role' => 'regulator', 'kind' => 'individual']);

        $migrationLead = Role::create([
            'project_id' => $project->id,
            'name' => 'Migration Lead',
            'weekly_capacity_hours' => 32,
            'hourly_rate_amount' => 140,
            'rate_currency' => 'USD',
        ]);
        $compliance = Role::create([
            'project_id' => $project->id,
            'name' => 'Compliance Officer',
            'weekly_capacity_hours' => 16,
            'hourly_rate_amount' => 160,
            'rate_currency' => 'USD',
        ]);

        Concern::create(['project_id' => $project->id, 'text' => 'Cutover must preserve audit history.']);
        Concern::create(['project_id' => $project->id, 'text' => 'Rollback path within 30 minutes.']);
        Concern::create(['project_id' => $project->id, 'text' => 'Read latency under peak load.']);

        foreach (range(1, 4) as $i) {
            Requirement::create([
                'project_id' => $project->id,
                'doc' => 'srs',
                'type' => 'functional',
                'text' => "Legacy capability LC-{$i} must be preserved post-migration.",
                'priority' => 'high',
            ]);
        }

        DesignView::create(['project_id' => $project->id, 'viewpoint' => 'logical', 'name' => 'Migration topology']);

        TestPlan::create(['project_id' => $project->id, 'level' => 'system', 'name' => 'Cutover dress rehearsal']);

        $extract = WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $migrationLead->id,
            'kind' => 'work_package',
            'name' => 'Data extract & validate',
            'status' => 'in_progress',
            'planned_start_date' => now()->subDays(40),
            'due_date' => now()->subDays(5),
            'effort_estimate_hours' => 80,
            'effort_actual_hours' => 60,
        ]);

        $spike = WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $migrationLead->id,
            'kind' => 'task',
            'name' => 'Schema mapping spike',
            'status' => 'done',
            'planned_start_date' => now()->subDays(45),
            'due_date' => now()->subDays(15),
            'effort_estimate_hours' => 24,
            'effort_actual_hours' => 28,
        ]);

        WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $compliance->id,
            'kind' => 'task',
            'name' => 'Rollback runbook',
            'status' => 'todo',
            'planned_start_date' => now()->addDays(3),
            'due_date' => now()->addDays(14),
            'effort_estimate_hours' => 12,
        ]);

        // Done spike with PR + successful checks but no deployment → "validated" (done with evidence but un-deployed)
        $spikeLink = WorkItemDeliveryLink::create([
            'work_item_id' => $spike->id,
            'type' => 'pull_request',
            'ref' => 'PR-77',
            'description' => 'Schema mapper PoC.',
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $spikeLink->id,
            'provider' => 'github',
            'name' => 'tests',
            'status' => 'completed',
            'conclusion' => 'success',
            'completed_at' => now()->subDays(14),
        ]);

        // In-progress data extract: PR with one passing and one timed_out check → "blocked_by_checks"
        $extractLink = WorkItemDeliveryLink::create([
            'work_item_id' => $extract->id,
            'type' => 'pull_request',
            'ref' => 'PR-92',
            'description' => 'Extract pipeline draft.',
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $extractLink->id,
            'provider' => 'github',
            'name' => 'tests',
            'status' => 'completed',
            'conclusion' => 'success',
            'completed_at' => now()->subDays(4),
        ]);
        CheckRunEvidence::create([
            'work_item_delivery_link_id' => $extractLink->id,
            'provider' => 'github',
            'name' => 'load-test',
            'status' => 'completed',
            'conclusion' => 'timed_out',
            'completed_at' => now()->subDays(1),
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Dress rehearsal complete',
            'target_date' => now()->subDays(7),
            'status' => 'pending',
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Cutover window',
            'target_date' => now()->addDays(30),
            'status' => 'pending',
        ]);

        Review::create([
            'project_id' => $project->id,
            'type' => 'management_review',
            'title' => 'Go/no-go for cutover rehearsal',
            'status' => 'planned',
            'planned_at' => now()->addDays(3),
        ]);

        ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Extend dual-write window',
            'category' => 'plan',
            'status' => 'under_review',
            'priority' => 'high',
        ]);

        Risk::create([
            'project_id' => $project->id,
            'owner_role_id' => $compliance->id,
            'title' => 'Audit log integrity during cutover',
            'description' => 'Risk of gaps in audit history if dual-write drops a transaction.',
            'category' => 'compliance',
            'probability' => 'high',
            'impact' => 'high',
            'status' => 'identified',
        ]);

        Risk::create([
            'project_id' => $project->id,
            'owner_role_id' => $migrationLead->id,
            'title' => 'Rollback exceeds 30-minute SLA',
            'description' => 'Untested at full data volume.',
            'category' => 'operational',
            'probability' => 'high',
            'impact' => 'medium',
            'status' => 'mitigating',
            'mitigation_plan' => 'Schedule full-scale rollback dress rehearsal.',
        ]);

        Risk::create([
            'project_id' => $project->id,
            'owner_role_id' => $migrationLead->id,
            'title' => 'Read latency under peak load',
            'category' => 'technical',
            'probability' => 'medium',
            'impact' => 'medium',
            'status' => 'accepted',
        ]);

        Anomaly::create([
            'project_id' => $project->id,
            'severity' => 'high',
            'status' => 'open',
            'summary' => 'Schema mapper drops nullable enum columns',
            'description' => 'Found during dress rehearsal; affects three legacy tables.',
            'environment' => 'rehearsal',
        ]);

        Anomaly::create([
            'project_id' => $project->id,
            'severity' => 'low',
            'status' => 'resolved',
            'summary' => 'Inconsistent timezone in cutover runbook',
            'description' => 'Runbook step 4 used local time while step 7 used UTC; reconciled to UTC.',
            'environment' => 'docs',
        ]);

        return $project;
    }
}
