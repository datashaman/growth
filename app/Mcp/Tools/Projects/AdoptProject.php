<?php

namespace App\Mcp\Tools\Projects;

use App\Growth\Plan\PlanBaseliner;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Adopt an existing GitHub repository as a Growth project: bind the repo, stamp the adoption time, and set an `adoption`-kind plan baseline at HEAD. A repo already bound to a project that was never adopted (e.g. via create-project) is adopted in place. Idempotent — adopting an already-adopted repo returns it unchanged. Newly created adopted projects start at rigor level 1; raise rigor as the backfill catches up.')]
class AdoptProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'name' => 'required|string|max:255',
        ]);

        $existing = Project::query()->where('github_repo', $data['github_repo'])->first();

        if ($existing !== null) {
            // Already adopted — idempotent no-op, no re-stamp, no second baseline.
            if ($existing->adopted_at !== null) {
                return $this->respond($existing, adopted: false);
            }

            // Bound but never adopted (e.g. created with create-project): adopt
            // the existing project in place rather than refusing or duplicating.
            try {
                DB::transaction(function () use ($existing): void {
                    $existing->update(['adopted_at' => now()]);
                    $this->baselineAdoption($existing);
                });
            } catch (IllegalTransitionException) {
                return new ResponseFactory(Response::error(
                    'The repository '.$data['github_repo'].' is bound to a project whose plan is not in draft and cannot be adopted in place.'
                ));
            }

            return $this->respond($existing->fresh(), adopted: true);
        }

        // github_repo is globally unique; refuse a repo bound in another
        // workspace without leaking that workspace's project.
        $boundElsewhere = Project::query()
            ->withoutGlobalScope('workspace')
            ->where('github_repo', $data['github_repo'])
            ->exists();

        if ($boundElsewhere) {
            return new ResponseFactory(Response::error(
                'The repository '.$data['github_repo'].' is already bound to a project in another workspace.'
            ));
        }

        $project = DB::transaction(function () use ($data): Project {
            $project = Project::create([
                'name' => $data['name'],
                'github_repo' => $data['github_repo'],
                'rigor_level' => 1,
                'adopted_at' => now(),
                'workspace_id' => app(WorkspaceContext::class)->requireId(),
                'created_by_user_id' => auth()->id(),
            ]);

            $this->baselineAdoption($project);

            return $project;
        });

        return $this->respond($project, adopted: true);
    }

    /**
     * Snapshot the project's plan as an `adoption` baseline at HEAD, creating a
     * draft plan first when the project has none.
     *
     * Throws {@see IllegalTransitionException} when the project already has a
     * plan that is not in `draft`.
     */
    private function baselineAdoption(Project $project): void
    {
        $plan = $project->projectPlan;

        if ($plan === null) {
            $plan = ProjectPlan::create([
                'project_id' => $project->id,
                'status' => 'draft',
            ]);
            // Keep the cached relation in step so respond() sees the new plan.
            $project->setRelation('projectPlan', $plan);
        }

        app(PlanBaseliner::class)->baseline($plan, auth()->user(), 'Adoption baseline at HEAD.', 'adoption');
    }

    /**
     * Shape the response for both the fresh-adoption and idempotent paths.
     */
    private function respond(Project $project, bool $adopted): ResponseFactory
    {
        $baseline = $project->projectPlan?->baselines()
            ->where('kind', 'adoption')
            ->orderBy('version')
            ->first();

        return Response::structured([
            'project_id' => $project->id,
            'name' => $project->name,
            'rigor_level' => $project->rigor_level,
            'status' => $project->status,
            'github_repo' => $project->github_repo,
            'adopted_at' => $project->adopted_at?->toIso8601String(),
            'adopted' => $adopted,
            'adoption_baseline_id' => $baseline?->id,
            'adoption_baseline_version' => $baseline?->version,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()
                ->description('The existing GitHub repository to adopt, in owner/repo form')
                ->required(),
            'name' => $schema->string()
                ->description('Name for the Growth project')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'rigor_level' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
            'github_repo' => $schema->string()->required(),
            'adopted_at' => $schema->string()->required(),
            'adopted' => $schema->boolean()->description('True when this call performed adoption (created the project or adopted an existing one in place); false when the project was already adopted')->required(),
            'adoption_baseline_id' => $schema->string(),
            'adoption_baseline_version' => $schema->integer(),
        ];
    }
}
