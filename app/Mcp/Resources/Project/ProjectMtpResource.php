<?php

namespace App\Mcp\Resources\Project;

use App\Mcp\Resources\Support\CitationRenderer;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Verification plan assembled from the project\'s test plans, cases, runs, and anomalies.')]
#[MimeType('text/markdown')]
class ProjectMtpResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/mtp');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'testPlans.cases.runs',
            'testPlans.cases.requirements:id,doc,type',
            'testPlans.cases.citations.source',
            'anomalies.testRun.case:id,name,test_plan_id',
            'anomalies.citations.source',
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        $plans = $project->testPlans;
        $masters = $plans->where('level', 'master')->values();
        $levels = $plans->where('level', '!=', 'master')
            ->sortBy(fn ($p) => array_search($p->level, ['system', 'acceptance', 'integration', 'unit']))
            ->values();

        $md = "# Master Test Plan — {$project->name}\n\n";
        $md .= "_Rigor level {$project->rigor_level}_\n\n";

        $md .= "## 2. Master Test Plan\n\n";
        if ($masters->isEmpty()) {
            $md .= "_No master test plan defined._\n\n";
        } else {
            foreach ($masters as $p) {
                $md .= $this->renderPlan($p);
            }
        }

        $md .= "## 3. Level Test Plans\n\n";
        if ($levels->isEmpty()) {
            $md .= "_None defined._\n\n";
        } else {
            foreach ($levels as $p) {
                $md .= "### {$p->level} — {$p->name}\n\n";
                $md .= $this->renderPlan($p, headingLevel: 4);
            }
        }

        $allCases = $plans->flatMap->cases;
        $allRuns = $allCases->flatMap->runs;

        $md .= "## 4. Run summary\n\n";
        if ($allRuns->isEmpty()) {
            $md .= "_No runs logged._\n\n";
        } else {
            $by = $allRuns->groupBy('status')->map->count();
            foreach (['pass', 'fail', 'blocked', 'skipped'] as $st) {
                $md .= '- **'.ucfirst($st).':** '.($by[$st] ?? 0)."\n";
            }
            $md .= "\n";
        }

        $md .= "## 5. Anomalies\n\n";
        if ($project->anomalies->isEmpty()) {
            $md .= "_None reported._\n\n";
        } else {
            foreach ($project->anomalies->sortBy('severity') as $a) {
                $md .= "- **[{$a->severity}/{$a->status}]** {$a->summary}";
                if ($a->testRun?->case) {
                    $md .= " _(from {$a->testRun->case->name})_";
                }
                $md .= "\n";
                $md .= CitationRenderer::render($a->citations);
            }
            $md .= "\n";
        }

        return Response::text($md);
    }

    protected function renderPlan($plan, int $headingLevel = 3): string
    {
        $h = str_repeat('#', $headingLevel);
        $md = '';
        if ($headingLevel === 3) {
            $md .= "### {$plan->name}\n\n";
        }
        if ($plan->scope) {
            $md .= "{$h}# Scope\n\n{$plan->scope}\n\n";
        }
        if ($plan->approach) {
            $md .= "{$h}# Approach\n\n{$plan->approach}\n\n";
        }
        if ($plan->pass_fail_criteria) {
            $md .= "{$h}# Pass/fail criteria\n\n{$plan->pass_fail_criteria}\n\n";
        }

        if ($plan->cases->isNotEmpty()) {
            $md .= "{$h}# Test cases\n\n";
            foreach ($plan->cases as $c) {
                $md .= "- **{$c->name}** — {$c->objective}\n";
                if ($c->requirements->isNotEmpty()) {
                    $reqs = $c->requirements->map(fn ($r) => "{$r->doc}/{$r->type}#{$r->id}")->implode(', ');
                    $md .= "  - _Covers:_ {$reqs}\n";
                }
                $md .= CitationRenderer::render($c->citations);
            }
            $md .= "\n";
        }

        return $md;
    }
}
