<?php

namespace App\Mcp\Resources\Project;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Project overview index with counts, resource links, and lifecycle evidence pointers.')]
#[MimeType('text/markdown')]
class ProjectIndexResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('project');
        $project = Project::withCount([
            'stakeholders',
            'concerns',
            'requirements',
            'designViews',
            'customViewpoints',
            'testPlans',
            'anomalies',
            'sources',
            'milestones',
            'workItems',
            'roles',
            'changeRequests',
            'reviews',
            'reviewFindings',
        ])->with('projectPlan:id,project_id,status')->find($id);

        if (! $project) {
            return Response::error("Project [{$id}] not found.");
        }

        $reqsByDoc = $project->requirements()
            ->selectRaw('doc, count(*) as n')
            ->groupBy('doc')
            ->pluck('n', 'doc');

        $cases = TestCase::whereIn('test_plan_id', $project->testPlans()->select('id'))->count();
        $runs = TestRun::whereIn(
            'test_case_id',
            TestCase::whereIn('test_plan_id', $project->testPlans()->select('id'))->select('id'),
        )->count();

        $md = "# {$project->name}\n\n";
        if ($project->description) {
            $md .= "{$project->description}\n\n";
        }
        $md .= "- **Project id:** `{$project->id}`\n";
        $md .= "- **Rigor level:** {$project->integrity_level}\n\n";

        $md .= "## Document URIs\n\n";
        $md .= "- Requirements: `growth://projects/{$project->id}/srs`\n";
        $md .= "- Architecture: `growth://projects/{$project->id}/sdd`\n";
        $md .= "- Verification: `growth://projects/{$project->id}/mtp`\n";
        $md .= "- Delivery plan: `growth://projects/{$project->id}/pmp`\n";
        $md .= "- Changes: `growth://projects/{$project->id}/changes`\n";
        $md .= "- Reviews: `growth://projects/{$project->id}/reviews`\n";
        $md .= "- Sources: `growth://projects/{$project->id}/sources`\n\n";

        $md .= "## Counts\n\n";
        $md .= "- Stakeholders: {$project->stakeholders_count}\n";
        $md .= "- Concerns: {$project->concerns_count}\n";
        $md .= "- Requirements: {$project->requirements_count}";
        if ($reqsByDoc->isNotEmpty()) {
            $parts = [];
            foreach (['strs', 'syrs', 'srs'] as $d) {
                if ($reqsByDoc->has($d)) {
                    $parts[] = "{$d}={$reqsByDoc[$d]}";
                }
            }
            if ($parts !== []) {
                $md .= ' ('.implode(', ', $parts).')';
            }
        }
        $md .= "\n";
        $md .= "- Design views: {$project->design_views_count}\n";
        $md .= "- Custom viewpoints: {$project->custom_viewpoints_count}\n";
        $md .= "- Test plans: {$project->test_plans_count}\n";
        $md .= "- Test cases: {$cases}\n";
        $md .= "- Test runs: {$runs}\n";
        $md .= "- Anomalies: {$project->anomalies_count}\n";
        $md .= "- Sources: {$project->sources_count}\n";
        $md .= '- Project plan: '.($project->projectPlan ? "**{$project->projectPlan->status}**" : '_missing_')."\n";
        $md .= "- Milestones: {$project->milestones_count}\n";
        $md .= "- Work items: {$project->work_items_count}\n";
        $md .= "- Roles: {$project->roles_count}\n";
        $md .= "- Change requests: {$project->change_requests_count}\n";
        $md .= "- Reviews: {$project->reviews_count}\n";
        $md .= "- Review findings: {$project->review_findings_count}\n";

        return Response::text($md);
    }
}
