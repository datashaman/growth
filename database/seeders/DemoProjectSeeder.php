<?php

namespace Database\Seeders;

use App\Models\ChangeRequest;
use App\Models\Concern;
use App\Models\Deployment;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Release;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\Stakeholder;
use App\Models\TestPlan;
use App\Models\User;
use App\Models\WorkItem;
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
                ['user_id' => $user->id, 'name' => 'Demo: Orbit Telemetry'],
                ['description' => 'Reference project showing a healthy delivery cadence.', 'integrity_level' => 2],
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
        foreach ($statuses as $i => $status) {
            WorkItem::create([
                'project_id' => $project->id,
                'kind' => 'task',
                'name' => 'Implement ingest stage '.($i + 1),
                'status' => $status,
                'planned_start_date' => now()->subDays(30 - $i * 5),
                'due_date' => now()->addDays(7 + $i * 3),
                'effort_estimate_hours' => 16,
                'effort_actual_hours' => $status === 'done' ? 14 : null,
            ]);
        }

        WorkItem::create([
            'project_id' => $project->id,
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

        Deployment::create([
            'project_id' => $project->id,
            'release_id' => $release->id,
            'environment' => 'staging',
            'status' => 'succeeded',
            'deployed_at' => now()->subDays(7),
        ]);

        return $project;
    }

    private function seedStrugglingProject(User $user): Project
    {
        $project = Project::withoutGlobalScopes()
            ->firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Demo: Legacy Migration'],
                ['description' => 'Reference project showing schedule and readiness pressure.', 'integrity_level' => 3],
            );

        if (! $project->wasRecentlyCreated && $project->workItems()->exists()) {
            return $project;
        }

        Stakeholder::create(['project_id' => $project->id, 'name' => 'Migration Sponsor', 'role' => 'sponsor', 'kind' => 'individual']);
        Stakeholder::create(['project_id' => $project->id, 'name' => 'Compliance Officer', 'role' => 'regulator', 'kind' => 'individual']);

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

        WorkItem::create([
            'project_id' => $project->id,
            'kind' => 'work_package',
            'name' => 'Data extract & validate',
            'status' => 'in_progress',
            'planned_start_date' => now()->subDays(40),
            'due_date' => now()->subDays(5),
            'effort_estimate_hours' => 80,
            'effort_actual_hours' => 60,
        ]);

        WorkItem::create([
            'project_id' => $project->id,
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
            'kind' => 'task',
            'name' => 'Rollback runbook',
            'status' => 'todo',
            'planned_start_date' => now()->addDays(3),
            'due_date' => now()->addDays(14),
            'effort_estimate_hours' => 12,
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

        return $project;
    }
}
