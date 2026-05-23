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
            $this->seedHealthyProjectMockups($project);

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
        ]);
        $ops = Role::create([
            'project_id' => $project->id,
            'name' => 'Ground Ops',
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
            ]);
        }

        $failoverDrill = WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $ops->id,
            'kind' => 'task',
            'name' => 'Ground station failover drill',
            'status' => 'in_progress',
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Telemetry pipeline GA',
            'status' => 'pending',
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Beta cutover',
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

        $this->seedHealthyProjectMockups($project);

        return $project;
    }

    private function seedHealthyProjectMockups(Project $project): void
    {
        $stage4 = $project->workItems()
            ->where('name', 'Implement ingest stage 4')
            ->first();

        if ($stage4) {
            $stage4->forceFill(['needs_mockups' => true])->save();

            $this->ensureMockup(
                $stage4,
                'Telemetry ingest dashboard',
                $this->telemetryIngestDashboardMockup(),
            );
            $this->ensureMockup(
                $stage4,
                'Packet latency drilldown',
                $this->packetLatencyDrilldownMockup(),
            );
        }

        $stage5 = $project->workItems()
            ->where('name', 'Implement ingest stage 5')
            ->first();

        if ($stage5) {
            $stage5->forceFill(['needs_mockups' => true])->save();
        }

        $failoverDrill = $project->workItems()
            ->where('name', 'Ground station failover drill')
            ->first();

        if ($failoverDrill) {
            $failoverDrill->forceFill(['needs_mockups' => true])->save();

            $this->ensureMockup(
                $failoverDrill,
                'Failover drill console',
                $this->failoverDrillConsoleMockup(),
            );
        }
    }

    private function ensureMockup(WorkItem $workItem, string $name, string $html): void
    {
        $mockup = $workItem->mockups()->firstOrCreate(['name' => $name]);

        if (! $mockup->revisions()->exists()) {
            $mockup->appendRevision($html);
        }
    }

    private function telemetryIngestDashboardMockup(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telemetry ingest dashboard</title>
    <style>
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #1f2937; background: #f8fafc; }
        main { padding: 28px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 28px; }
        .status { border: 1px solid #16a34a; color: #166534; border-radius: 999px; padding: 6px 12px; font-size: 13px; background: #dcfce7; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .panel { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); }
        .label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        .value { margin-top: 8px; font-size: 34px; font-weight: 700; }
        .timeline { margin-top: 18px; display: grid; gap: 10px; }
        .row { display: grid; grid-template-columns: 120px 1fr 72px; gap: 12px; align-items: center; }
        .bar { height: 10px; border-radius: 999px; background: linear-gradient(90deg, #2563eb, #14b8a6); }
        .warn { color: #92400e; background: #fef3c7; border-color: #fcd34d; }
    </style>
</head>
<body>
    <main>
        <header>
            <h1>Telemetry ingest</h1>
            <div class="status">Nominal</div>
        </header>
        <section class="grid">
            <div class="panel"><div class="label">Packet rate</div><div class="value">12.4k/s</div></div>
            <div class="panel"><div class="label">Latency p95</div><div class="value">183ms</div></div>
            <div class="panel"><div class="label">Dropped packets</div><div class="value">0.08%</div></div>
        </section>
        <section class="panel" style="margin-top: 16px;">
            <div class="label">Ground station streams</div>
            <div class="timeline">
                <div class="row"><strong>Canberra</strong><div class="bar" style="width: 92%;"></div><span>stable</span></div>
                <div class="row"><strong>Madrid</strong><div class="bar" style="width: 76%;"></div><span>stable</span></div>
                <div class="row"><strong>Goldstone</strong><div class="bar warn" style="width: 58%;"></div><span>lagging</span></div>
            </div>
        </section>
    </main>
</body>
</html>
HTML;
    }

    private function packetLatencyDrilldownMockup(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packet latency drilldown</title>
    <style>
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #111827; background: #ffffff; }
        main { padding: 26px; }
        h1 { margin: 0 0 18px; font-size: 26px; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 18px; }
        button { border: 1px solid #d1d5db; background: white; border-radius: 6px; padding: 8px 12px; }
        button.active { background: #111827; color: white; border-color: #111827; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        .spark { height: 32px; border-radius: 6px; background: linear-gradient(90deg, #60a5fa 18%, #e5e7eb 18% 30%, #22c55e 30% 72%, #f59e0b 72%); }
    </style>
</head>
<body>
    <main>
        <h1>Packet latency drilldown</h1>
        <div class="toolbar">
            <button class="active">Last hour</button>
            <button>Burn window</button>
            <button>Anomalies</button>
        </div>
        <table>
            <thead><tr><th>Station</th><th>p50</th><th>p95</th><th>Trend</th><th>Action</th></tr></thead>
            <tbody>
                <tr><td>Canberra</td><td>96ms</td><td>172ms</td><td><div class="spark"></div></td><td>Watch</td></tr>
                <tr><td>Madrid</td><td>101ms</td><td>188ms</td><td><div class="spark"></div></td><td>None</td></tr>
                <tr><td>Goldstone</td><td>144ms</td><td>246ms</td><td><div class="spark"></div></td><td>Reroute burst</td></tr>
            </tbody>
        </table>
    </main>
</body>
</html>
HTML;
    }

    private function failoverDrillConsoleMockup(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Failover drill console</title>
    <style>
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #f8fafc; background: #111827; }
        main { padding: 28px; }
        h1 { margin: 0 0 20px; font-size: 28px; }
        .layout { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; }
        .panel { background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 16px; }
        .step { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid #374151; }
        .step:last-child { border-bottom: 0; }
        .done { color: #86efac; }
        .active { color: #facc15; }
        .metric { display: grid; grid-template-columns: 1fr auto; padding: 11px 0; border-bottom: 1px solid #374151; }
        .metric:last-child { border-bottom: 0; }
        .value { font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <h1>Ground station failover</h1>
        <section class="layout">
            <div class="panel">
                <div class="step"><strong>Detect primary link degradation</strong><span class="done">Complete</span></div>
                <div class="step"><strong>Route telemetry through Madrid</strong><span class="active">Running</span></div>
                <div class="step"><strong>Verify command acknowledgement</strong><span>Queued</span></div>
                <div class="step"><strong>Restore preferred route</strong><span>Queued</span></div>
            </div>
            <div class="panel">
                <div class="metric"><span>Current route</span><span class="value">Goldstone to Madrid</span></div>
                <div class="metric"><span>Packet loss</span><span class="value">0.14%</span></div>
                <div class="metric"><span>Command lag</span><span class="value">211ms</span></div>
                <div class="metric"><span>Drill window</span><span class="value">14m left</span></div>
            </div>
        </section>
    </main>
</body>
</html>
HTML;
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
        ]);
        $compliance = Role::create([
            'project_id' => $project->id,
            'name' => 'Compliance Officer',
        ]);

        Concern::create(['project_id' => $project->id, 'text' => 'Cutover must preserve audit history.']);
        Concern::create(['project_id' => $project->id, 'text' => 'Rollback path within 30 minutes.']);
        Concern::create(['project_id' => $project->id, 'text' => 'Read latency under peak load.']);

        foreach (range(1, 4) as $i) {
            Requirement::create([
                'project_id' => $project->id,
                'doc' => 'srs',
                'type' => 'functional',
                'text' => "Legacy requirement LC-{$i} must be preserved post-migration.",
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
        ]);

        $spike = WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $migrationLead->id,
            'kind' => 'task',
            'name' => 'Schema mapping spike',
            'status' => 'done',
        ]);

        WorkItem::create([
            'project_id' => $project->id,
            'responsible_role_id' => $compliance->id,
            'kind' => 'task',
            'name' => 'Rollback runbook',
            'status' => 'todo',
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
            'status' => 'pending',
        ]);

        Milestone::create([
            'project_id' => $project->id,
            'name' => 'Cutover window',
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
