<?php

namespace App\Mcp\Resources;

use App\Models\WorkItem;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Work Item Implementation Brief')]
#[Description('Context bundle for implementing a work item: requirements, architecture, dependencies, RACI, mockups, and delivery evidence.')]
#[MimeType('text/markdown')]
class WorkItemImplementationBriefResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://work-items/{work_item}/implementation-brief');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('work_item');
        $workItem = WorkItem::with([
            'project.designViews.concerns',
            'project.designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
            'requirements',
            'dependencies',
            'dependents',
            'milestones',
            'raciRoles',
            'responsibleRole',
            'mockups',
            'deliveryLinks.checkRuns',
        ])->find($id);

        if (! $workItem) {
            return Response::error("Work item [{$id}] not found.");
        }

        $project = $workItem->project;
        $md = "# Implementation Brief - {$workItem->reference()} {$workItem->name}\n\n";
        $md .= "Use this brief before implementing or completing the work item. It bundles the context most likely to affect the artifact you produce.\n\n";

        $md .= "## Work Item\n\n";
        $md .= "- **Project:** {$project->name}\n";
        $md .= "- **Kind:** {$workItem->kind}\n";
        $md .= "- **Status:** {$workItem->status}\n";
        if ($workItem->description) {
            $md .= "- **Description:** {$workItem->description}\n";
        }
        if ($workItem->responsibleRole) {
            $md .= "- **Responsible role:** {$workItem->responsibleRole->name}\n";
        }
        $md .= "\n";

        $md .= "## Requirements To Preserve\n\n";
        if ($workItem->requirements->isEmpty()) {
            $md .= "_No requirements are linked to this work item yet._\n\n";
        } else {
            foreach ($workItem->requirements->sortBy(['doc', 'number']) as $requirement) {
                $md .= "- **{$requirement->reference()}** ({$requirement->type}, {$requirement->priority}): {$requirement->text}\n";
                if (! empty($requirement->acceptance_criteria)) {
                    foreach ($requirement->acceptance_criteria as $check) {
                        $md .= "  - Acceptance: {$check}\n";
                    }
                }
            }
            $md .= "\n";
        }

        $md .= "## Architecture Context\n\n";
        $views = $project->designViews->sortBy(['viewpoint', 'name'])->values();
        if ($views->isEmpty()) {
            $md .= "_No architecture views are captured for this project yet._\n\n";
        } else {
            foreach ($views as $view) {
                $md .= "### {$view->name}\n\n";
                $md .= "- **Viewpoint:** `{$view->viewpoint}`\n";
                if ($view->description) {
                    $md .= "- **Description:** {$view->description}\n";
                }
                if ($view->concerns->isNotEmpty()) {
                    $md .= '- **Concerns:** '.implode('; ', $view->concerns->pluck('text')->all())."\n";
                }
                if ($view->elements->isNotEmpty()) {
                    $md .= "- **Elements:**\n";
                    foreach ($view->elements as $element) {
                        $md .= "  - [{$element->kind}] {$element->name}";
                        if ($element->type) {
                            $md .= " ({$element->type})";
                        }
                        if ($element->purpose) {
                            $md .= " - {$element->purpose}";
                        }
                        $md .= "\n";
                    }
                }
                $md .= "\n";
            }
        }

        $md .= "## Dependencies And Milestones\n\n";
        $md .= $workItem->dependencies->isEmpty()
            ? "- **Dependencies:** none recorded.\n"
            : '- **Dependencies:** '.$workItem->dependencies->map(fn (WorkItem $item): string => "{$item->reference()} {$item->name}")->implode('; ')."\n";
        $md .= $workItem->dependents->isEmpty()
            ? "- **Dependents:** none recorded.\n"
            : '- **Dependents:** '.$workItem->dependents->map(fn (WorkItem $item): string => "{$item->reference()} {$item->name}")->implode('; ')."\n";
        $md .= $workItem->milestones->isEmpty()
            ? "- **Milestones:** none linked.\n\n"
            : '- **Milestones:** '.$workItem->milestones->pluck('name')->implode('; ')."\n\n";

        $md .= "## RACI And Mockups\n\n";
        if ($workItem->raciRoles->isEmpty()) {
            $md .= "- **RACI:** none assigned.\n";
        } else {
            foreach ($workItem->raciRoles as $role) {
                $md .= '- **'.strtoupper((string) $role->pivot->raci).":** {$role->name}\n";
            }
        }
        if ($workItem->mockups->isEmpty()) {
            $md .= "- **Mockups:** none captured.\n\n";
        } else {
            foreach ($workItem->mockups as $mockup) {
                $md .= "- **Mockup:** {$mockup->name} (`growth://mockups/{$mockup->id}`)\n";
            }
            $md .= "\n";
        }

        $md .= "## Delivery Evidence\n\n";
        if ($workItem->deliveryLinks->isEmpty()) {
            $md .= "_No delivery evidence is linked yet._\n\n";
        } else {
            foreach ($workItem->deliveryLinks as $link) {
                $md .= "- **{$link->type}:** {$link->ref}";
                if ($link->url) {
                    $md .= " ({$link->url})";
                }
                $md .= "\n";
                foreach ($link->checkRuns as $check) {
                    $md .= "  - Check {$check->name}: {$check->status}";
                    if ($check->conclusion) {
                        $md .= " / {$check->conclusion}";
                    }
                    $md .= "\n";
                }
            }
            $md .= "\n";
        }

        $md .= "## Implementation Guidance\n\n";
        $md .= "- Preserve linked requirement behavior and acceptance checks.\n";
        $md .= "- Respect architecture context where it affects component boundaries, data flow, or user-facing behavior.\n";
        $md .= "- Consider dependencies, RACI, mockups, and existing delivery evidence before changing code.\n";

        return Response::text($md);
    }
}
