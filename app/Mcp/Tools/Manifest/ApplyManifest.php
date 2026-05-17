<?php

namespace App\Mcp\Tools\Manifest;

use App\Growth\Manifest\ManifestApplier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Description('Apply a Growth project manifest (project + stakeholders + concerns + requirements + architecture + plan + verification) in a single transaction. Three modes: `fail` aborts on any difference, `merge` updates by natural keys (project id; stakeholder/role/milestone/view/work-item/verification-plan name; concern text; requirement slug; element name within view; verification case name within plan; ProjectPlan is singleton per project), `replace` wipes the project\'s child entities first and requires `confirm` to match the project name. Pass `dry_run: true` to roll back and preview the report.')]
class ApplyManifest extends Tool
{
    public function __construct(private readonly ManifestApplier $applier) {}

    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'manifest' => 'required|array',
            'manifest.project' => 'required|array',
            'manifest.project.id' => 'nullable|string|owned_project',
            'manifest.project.name' => 'required_without:manifest.project.id|string|max:255',
            'manifest.stakeholders' => 'nullable|array',
            'manifest.stakeholders.*.name' => 'required|string|max:255',
            'manifest.concerns' => 'nullable|array',
            'manifest.concerns.*.text' => 'required|string|min:3',
            'manifest.requirements' => 'nullable|array',
            'manifest.requirements.*.slug' => 'required|string|max:120',
            'manifest.requirements.*.text' => 'required|string|min:3',
            'manifest.architecture' => 'nullable|array',
            'manifest.architecture.viewpoints' => 'nullable|array',
            'manifest.architecture.viewpoints.*.name' => 'required|string|max:80',
            'manifest.architecture.viewpoints.*.concerns' => 'required|array|min:1',
            'manifest.architecture.viewpoints.*.element_types' => 'required|array|min:1',
            'manifest.architecture.viewpoints.*.languages' => 'required|array|min:1',
            'manifest.architecture.views' => 'nullable|array',
            'manifest.architecture.views.*.name' => 'required|string|max:255',
            'manifest.architecture.views.*.viewpoint' => 'required|string',
            'manifest.architecture.views.*.elements' => 'nullable|array',
            'manifest.architecture.views.*.elements.*.name' => 'required|string|max:255',
            'manifest.architecture.views.*.elements.*.kind' => 'required|in:entity,relationship,attribute,constraint',
            'manifest.plan' => 'nullable|array',
            'manifest.plan.status' => 'nullable|in:draft,baselined,active,closed',
            'manifest.plan.roles' => 'nullable|array',
            'manifest.plan.roles.*.name' => 'required|string|max:255',
            'manifest.plan.milestones' => 'nullable|array',
            'manifest.plan.milestones.*.name' => 'required|string|max:255',
            'manifest.plan.milestones.*.status' => 'nullable|in:pending,hit,missed,deferred',
            'manifest.plan.work_items' => 'nullable|array',
            'manifest.plan.work_items.*.name' => 'required|string|max:255',
            'manifest.plan.work_items.*.kind' => 'required|in:deliverable,work_package,task',
            'manifest.plan.work_items.*.status' => 'nullable|in:todo,in_progress,blocked,done,cancelled',
            'manifest.verification' => 'nullable|array',
            'manifest.verification.plans' => 'nullable|array',
            'manifest.verification.plans.*.name' => 'required|string|max:255',
            'manifest.verification.plans.*.level' => 'required|in:master,unit,integration,system,acceptance',
            'manifest.verification.plans.*.cases' => 'nullable|array',
            'manifest.verification.plans.*.cases.*.name' => 'required|string|max:255',
            'manifest.verification.plans.*.cases.*.expected_results' => 'required|string',
            'mode' => 'nullable|in:fail,merge,replace',
            'dry_run' => 'nullable|boolean',
            'confirm' => 'nullable|string',
        ]);

        $raw = $request->all();
        $manifest = $raw['manifest'];
        $mode = $raw['mode'] ?? 'fail';
        $dryRun = (bool) ($raw['dry_run'] ?? false);
        $confirm = $raw['confirm'] ?? null;

        try {
            $report = $this->applier->apply($manifest, $mode, $dryRun, $confirm);
        } catch (RuntimeException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured($report);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'manifest' => $schema->object(fn (JsonSchema $s) => [
                'project' => $s->object(fn (JsonSchema $p) => [
                    'id' => $p->string()->description('Existing project ULID. Omit to create new.'),
                    'name' => $p->string()->description('Project name. Required when creating.'),
                    'description' => $p->string()->description('Optional project description'),
                    'rigor_level' => $p->integer()->description('Project rigor level (1–4, default 2)'),
                    'status' => $p->string()->description('Project lifecycle status')->enum(['draft', 'active', 'archived', 'closed']),
                    '_exported_at' => $p->string()->description('Optional ISO-8601 timestamp; if older than the current `updated_at` the report flags drift.'),
                ])->description('Project record. Required.')->required(),
                'stakeholders' => $s->array()->description('Optional list. Natural key is `name` within the project. Each item may include `_exported_at` for drift reporting.'),
                'concerns' => $s->array()->description('Optional list. Natural key is `text` within the project. `raised_by` may reference a stakeholder slug (declared in this manifest) or an existing stakeholder name.'),
                'requirements' => $s->array()->description('Optional list. Each item requires a `slug` (kebab-case, unique within the project) which is the natural key for upserts.'),
                'architecture' => $s->object(fn (JsonSchema $a) => [
                    'viewpoints' => $a->array()->description('Optional custom viewpoints. Natural key is `name` within the project; built-in viewpoint names (context, logical, …) are reserved.'),
                    'views' => $a->array()->description('Optional architecture views. Natural key is `name` within the project. `viewpoint` references a slug declared in this manifest, a custom viewpoint name, or a built-in viewpoint. May embed `elements` as a child list.'),
                ])->description('Architecture section: viewpoints (custom only — built-ins are referenced by name) and views with optional nested elements.'),
                'plan' => $s->object(fn (JsonSchema $p) => [
                    'status' => $p->string()->description('Plan lifecycle status')->enum(['draft', 'baselined', 'active', 'closed']),
                    'scope_summary' => $p->string(),
                    'objectives' => $p->string(),
                    'deliverables_summary' => $p->string(),
                    'approach' => $p->string(),
                    'organization_summary' => $p->string(),
                    'assumptions' => $p->string(),
                    'constraints' => $p->string(),
                    'budget_summary' => $p->string(),
                    'roles' => $p->array()->description('Optional roles. Natural key is `name` within the project.'),
                    'milestones' => $p->array()->description('Optional milestones. Natural key is `name` within the project.'),
                    'work_items' => $p->array()->description('Optional work items. Natural key is `name` within the project. `responsible_role` may reference a role slug declared here or an existing role name. `parent`, `requirements`, `milestones`, `dependencies` are resolved in a second pass after every work item exists.'),
                ])->description('Plan section: singleton ProjectPlan + roles, milestones, and work items. Work-item cross-references resolve to slugs declared in this manifest first, then by natural key against existing records.'),
                'verification' => $s->object(fn (JsonSchema $v) => [
                    'plans' => $v->array()->description('Optional verification plans. Natural key is `name` within the project. Each plan may embed `cases` as a child list. Case `verifies_requirements` accepts requirement slugs declared in this manifest or existing requirement slugs.'),
                ])->description('Verification section: verification plans (test_plans on the database side) with optional nested cases. Cases link to requirements via `verifies_requirements`.'),
            ])->description('The manifest object. Use export-manifest (future slice) to generate one, or hand-author it.')->required(),
            'mode' => $schema->string()
                ->description('`fail` (default): abort on any difference. `merge`: update matching records by natural key. `replace`: wipe project\'s stakeholders/concerns/requirements and re-create from the manifest. `replace` requires `confirm` to match the existing project name.')
                ->enum(['fail', 'merge', 'replace']),
            'dry_run' => $schema->boolean()
                ->description('When true, runs the apply inside a transaction and rolls back before returning. The report is identical to a real run, including `counts` and `drift`.'),
            'confirm' => $schema->string()
                ->description('Required when `mode=replace` and the project already exists. Must match the existing project\'s exact name to confirm destructive replacement.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'mode' => $schema->string()->required()->description('The mode requested by the caller.'),
            'effective_mode' => $schema->string()->required()->description('The mode actually applied. Differs from `mode` only when `replace` was requested against a non-existent project, in which case it falls back to `fail`.'),
            'dry_run' => $schema->boolean()->required(),
            'counts' => $schema->object(fn (JsonSchema $s) => [
                'project_created' => $s->boolean()->required(),
                'project_updated' => $s->boolean()->required(),
                'stakeholders_created' => $s->integer()->required(),
                'stakeholders_updated' => $s->integer()->required(),
                'stakeholders_deleted' => $s->integer()->required(),
                'concerns_created' => $s->integer()->required(),
                'concerns_updated' => $s->integer()->required(),
                'concerns_deleted' => $s->integer()->required(),
                'requirements_created' => $s->integer()->required(),
                'requirements_updated' => $s->integer()->required(),
                'requirements_deleted' => $s->integer()->required(),
                'viewpoints_created' => $s->integer()->required(),
                'viewpoints_updated' => $s->integer()->required(),
                'viewpoints_deleted' => $s->integer()->required(),
                'views_created' => $s->integer()->required(),
                'views_updated' => $s->integer()->required(),
                'views_deleted' => $s->integer()->required(),
                'elements_created' => $s->integer()->required(),
                'elements_updated' => $s->integer()->required(),
                'elements_deleted' => $s->integer()->required(),
                'plan_created' => $s->boolean()->required(),
                'plan_updated' => $s->boolean()->required(),
                'plan_deleted' => $s->boolean()->required(),
                'roles_created' => $s->integer()->required(),
                'roles_updated' => $s->integer()->required(),
                'roles_deleted' => $s->integer()->required(),
                'milestones_created' => $s->integer()->required(),
                'milestones_updated' => $s->integer()->required(),
                'milestones_deleted' => $s->integer()->required(),
                'work_items_created' => $s->integer()->required(),
                'work_items_updated' => $s->integer()->required(),
                'work_items_deleted' => $s->integer()->required(),
                'verification_plans_created' => $s->integer()->required(),
                'verification_plans_updated' => $s->integer()->required(),
                'verification_plans_deleted' => $s->integer()->required(),
                'verification_cases_created' => $s->integer()->required(),
                'verification_cases_updated' => $s->integer()->required(),
                'verification_cases_deleted' => $s->integer()->required(),
            ])->required(),
            'slugs' => $schema->object()->description('Slug → ULID maps for `requirements`, `stakeholders`, `concerns`, `viewpoints`, `views`, `elements`, `roles`, `milestones`, `work_items`, `verification_plans`, `verification_cases`. Useful for follow-up calls referencing the just-applied records.')->required(),
            'drift' => $schema->array()->description('Entries describing records whose current `updated_at` is newer than the manifest\'s `_exported_at`. Empty when no drift detected.')->required(),
        ];
    }
}
