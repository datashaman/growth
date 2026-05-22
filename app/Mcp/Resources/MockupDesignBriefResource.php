<?php

namespace App\Mcp\Resources;

use App\Models\Requirement;
use App\Models\SpecMockup;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Mockup Design Brief')]
#[Description('Context bundle for generating or refining a mockup: owner details, linked requirements, existing mockups, and architecture views/elements.')]
#[MimeType('text/markdown')]
class MockupDesignBriefResource extends Resource implements HasUriTemplate
{
    /** @var array<string, class-string<Model>> */
    private const OWNER_MODELS = [
        'work_item' => WorkItem::class,
        'requirement' => Requirement::class,
    ];

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://owners/{owner_type}/{owner_id}/mockup-design-brief');
    }

    public function handle(Request $request): Response
    {
        $ownerType = $request->get('owner_type');
        $ownerId = $request->get('owner_id');
        $model = self::OWNER_MODELS[$ownerType] ?? null;

        if ($model === null) {
            return Response::error("Unsupported mockup owner type [{$ownerType}].");
        }

        $owner = $model::find($ownerId);
        if (! $owner) {
            return Response::error("Mockup owner {$ownerType} [{$ownerId}] not found.");
        }

        $project = $owner->project()
            ->with([
                'designViews.concerns',
                'designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
            ])
            ->firstOrFail();

        $requirements = $owner instanceof WorkItem
            ? $owner->requirements()->orderBy('doc')->orderBy('number')->get()
            : collect([$owner]);

        $mockups = SpecMockup::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->get();

        $md = "# Mockup Design Brief - {$project->name}\n\n";
        $md .= "Use this brief before generating or refining the mockup. It bundles the most relevant context so the artifact reflects captured project intent and architecture.\n\n";

        $md .= "## Owner\n\n";
        if ($owner instanceof WorkItem) {
            $md .= "- **Type:** work item\n";
            $md .= "- **Reference:** {$owner->reference()}\n";
            $md .= "- **Name:** {$owner->name}\n";
            $md .= "- **Kind:** {$owner->kind}\n";
            $md .= "- **Status:** {$owner->status}\n";
            if ($owner->description) {
                $md .= "- **Description:** {$owner->description}\n";
            }
        } else {
            $md .= "- **Type:** requirement\n";
            $md .= "- **Reference:** {$owner->reference()}\n";
            $md .= "- **Layer:** {$owner->doc}\n";
            $md .= "- **Requirement type:** {$owner->type}\n";
            $md .= "- **Text:** {$owner->text}\n";
            if ($owner->rationale) {
                $md .= "- **Rationale:** {$owner->rationale}\n";
            }
        }
        $md .= "\n";

        $md .= "## Requirements To Preserve\n\n";
        if ($requirements->isEmpty()) {
            $md .= "_No requirements are linked to this work item yet._\n\n";
        } else {
            foreach ($requirements as $requirement) {
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
                    $md .= "- **Concerns addressed:**\n";
                    foreach ($view->concerns as $concern) {
                        $md .= "  - {$concern->text}\n";
                    }
                }
                if ($view->elements->isNotEmpty()) {
                    $md .= "- **Elements to consider in the mockup:**\n";
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

        $md .= "## Existing Mockups\n\n";
        if ($mockups->isEmpty()) {
            $md .= "_No mockups exist for this owner yet._\n\n";
        } else {
            foreach ($mockups as $mockup) {
                $md .= "- {$mockup->name} (`growth://mockups/{$mockup->id}`)\n";
            }
            $md .= "\n";
        }

        $md .= "## Generation Guidance\n\n";
        $md .= "- Represent relevant architecture views/elements in the mockup when they affect layout, states, flows, or component boundaries.\n";
        $md .= "- Preserve linked requirement behavior and acceptance checks.\n";
        $md .= "- If the mockup intentionally diverges from architecture context, make the mismatch visible in the artifact or its notes.\n";

        return Response::text($md);
    }
}
